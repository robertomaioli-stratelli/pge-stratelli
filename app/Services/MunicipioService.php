<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use RuntimeException;

final class MunicipioService
{
    public function createWithManager(array $data): int
    {
        $nome = trim((string)($data['nome'] ?? ''));
        $uf = strtoupper(trim((string)($data['uf'] ?? '')));
        $slug = $this->slug((string)($data['slug'] ?? ''), $nome);
        if ($nome==='' || strlen($uf)!==2 || $slug==='') throw new RuntimeException('Informe nome, UF e identificador do município.');

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt=$pdo->prepare('INSERT INTO municipios
                (nome,uf,slug,codigo_ibge,populacao,area_km2,site_oficial,latitude,longitude,status,ativo,criado_em,atualizado_em)
                VALUES (?,?,?,?,?,?,?,?,?,"IMPLANTACAO",1,NOW(),NOW())');
            $stmt->execute([
                $nome,$uf,$slug,
                $this->nullableString($data['codigo_ibge']??null),
                $this->nullableInt($data['populacao']??null),
                $this->nullableFloat($data['area_km2']??null),
                $this->nullableString($data['site_oficial']??null),
                $this->nullableFloat($data['latitude']??null),
                $this->nullableFloat($data['longitude']??null),
            ]);
            $id=(int)$pdo->lastInsertId();
            $primary=$this->color((string)($data['cor_primaria']??'#082A55'),'#082A55');
            $secondary=$this->color((string)($data['cor_secundaria']??'#176FDD'),'#176FDD');
            $headerDecoration=in_array((string)($data['estilo_decoracao_cabecalho']??'semicirculo'),['semicirculo','nenhuma'],true)?(string)$data['estilo_decoracao_cabecalho']:'semicirculo';
            $pdo->prepare('INSERT INTO parametros_instancia(municipio_id,cor_primaria,cor_secundaria,estilo_decoracao_cabecalho,criado_em,atualizado_em) VALUES(?,?,?,?,NOW(),NOW())')->execute([$id,$primary,$secondary,$headerDecoration]);
            (new UserService())->create([
                'nome'=>$data['gestor_nome']??'',
                'email'=>$data['gestor_email']??'',
                'senha'=>$data['gestor_senha']??'',
                'grupo'=>'GESTOR',
            ],$id,false);
            $this->applyIdentityFiles($id);
            $pdo->commit();
            Audit::log('municipio_criado', $nome.' / '.$uf, $id);
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function updateDetails(int $id,array $data): void
    {
        $pdo=Database::connection();
        EtapaArchiveSchema::ensure($pdo);
        $this->assertEtapa1Aberta($id);
        $st=$pdo->prepare('SELECT * FROM municipios WHERE id=? LIMIT 1');$st->execute([$id]);$current=$st->fetch();
        if(!$current) throw new RuntimeException('Município não encontrado.');
        $nome=trim((string)($data['nome']??''));$uf=strtoupper(trim((string)($data['uf']??'')));$slug=$this->slug((string)($data['slug']??''),$nome);
        if($nome===''||strlen($uf)!==2||$slug==='')throw new RuntimeException('Informe nome, UF e identificador do município.');
        $status=strtoupper((string)($data['status']??'IMPLANTACAO'));
        $allowedStatus=['NEGOCIACAO','APRESENTACAO','IMPLANTACAO','ATIVO','SUSPENSO','DESATIVADO'];
        if(!in_array($status,$allowedStatus,true))$status='IMPLANTACAO';
        $ativo=$status==='DESATIVADO'?0:1;
        $pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE municipios SET nome=?,uf=?,slug=?,codigo_ibge=?,populacao=?,area_km2=?,site_oficial=?,latitude=?,longitude=?,status=?,ativo=?,atualizado_em=NOW() WHERE id=?')->execute([
                $nome,$uf,$slug,$this->nullableString($data['codigo_ibge']??null),$this->nullableInt($data['populacao']??null),$this->nullableFloat($data['area_km2']??null),$this->nullableString($data['site_oficial']??null),$this->nullableFloat($data['latitude']??null),$this->nullableFloat($data['longitude']??null),$status,$ativo,$id
            ]);
            if(!empty($data['remover_brasao']))$pdo->prepare('UPDATE municipios SET brasao_path=NULL WHERE id=?')->execute([$id]);
            if(!empty($data['remover_geojson']))$pdo->prepare('UPDATE municipios SET geojson_delimitacao=NULL WHERE id=?')->execute([$id]);
            $this->applyIdentityFiles($id);
            $pdo->commit();
            Audit::log('MUNICIPIO_ATUALIZADO','Cadastro municipal atualizado: '.$nome.' / '.$uf,$id,['categoria'=>'ADMINISTRACAO','severidade'=>'ATENCAO','antes'=>['nome'=>$current['nome'],'uf'=>$current['uf'],'slug'=>$current['slug'],'status'=>$current['status'],'codigo_ibge'=>$current['codigo_ibge'],'populacao'=>$current['populacao'],'area_km2'=>$current['area_km2']],'depois'=>['nome'=>$nome,'uf'=>$uf,'slug'=>$slug,'status'=>$status,'codigo_ibge'=>$this->nullableString($data['codigo_ibge']??null),'populacao'=>$this->nullableInt($data['populacao']??null),'area_km2'=>$this->nullableFloat($data['area_km2']??null)]]);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function setTerritorialModule(int $id,bool $enabled): void
    {
        $pdo=Database::connection();
        EtapaArchiveSchema::ensure($pdo);
        $this->assertEtapa1Aberta($id);
        $st=$pdo->prepare('SELECT id,nome,uf,inteligencia_territorial_ativa FROM municipios WHERE id=? LIMIT 1');
        $st->execute([$id]);$municipio=$st->fetch();
        if(!$municipio)throw new RuntimeException('Município não encontrado.');
        $value=$enabled?1:0;
        $pdo->prepare('UPDATE municipios SET inteligencia_territorial_ativa=?,atualizado_em=NOW() WHERE id=?')->execute([$value,$id]);
        Audit::log($enabled?'inteligencia_territorial_ativada':'inteligencia_territorial_desativada',($enabled?'Inteligência Territorial ativada para ':'Inteligência Territorial desativada para ').$municipio['nome'].' / '.$municipio['uf'],$id);
        if($enabled){try{(new NotificationService())->territorialActivated($id);}catch(\Throwable){}}
    }

    private function applyIdentityFiles(int $id): void
    {
        $pdo=Database::connection();
        if(isset($_FILES['brasao'])&&($_FILES['brasao']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            $up=(new FileStorage())->storeUpload('brasao','municipios/'.$id,'brasao-'.$id,['png','jpg','jpeg','webp'],2*1024*1024);
            $pdo->prepare('UPDATE municipios SET brasao_path=?,atualizado_em=NOW() WHERE id=?')->execute([$up['path'],$id]);
        }
        if(isset($_FILES['geojson'])&&($_FILES['geojson']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            if((int)($_FILES['geojson']['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new RuntimeException('Não foi possível receber o arquivo GeoJSON.');
            if((int)($_FILES['geojson']['size']??0)>8*1024*1024)throw new RuntimeException('O GeoJSON deve ter no máximo 8 MB.');
            $ext=strtolower(pathinfo((string)($_FILES['geojson']['name']??''),PATHINFO_EXTENSION));
            if(!in_array($ext,['geojson','json'],true))throw new RuntimeException('Envie a delimitação em arquivo .geojson ou .json.');
            $raw=file_get_contents((string)$_FILES['geojson']['tmp_name']);if($raw===false)throw new RuntimeException('Não foi possível ler o GeoJSON.');
            $decoded=json_decode($raw,true);if(!is_array($decoded)||!in_array((string)($decoded['type']??''),['FeatureCollection','Feature','Polygon','MultiPolygon','GeometryCollection'],true))throw new RuntimeException('O arquivo informado não contém uma geometria GeoJSON válida.');
            $normalized=json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($normalized===false)throw new RuntimeException('Não foi possível normalizar a delimitação territorial.');
            $pdo->prepare('UPDATE municipios SET geojson_delimitacao=?,atualizado_em=NOW() WHERE id=?')->execute([$normalized,$id]);
        }
    }

    private function slug(string $value,string $fallback): string
    {
        $raw=$value!==''?$value:$fallback;$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$raw);$ascii=$ascii!==false?$ascii:$raw;
        return trim(strtolower((string)preg_replace('/[^a-zA-Z0-9]+/','-',$ascii)),'-');
    }
    private function nullableString(mixed $v): ?string{$v=trim((string)$v);return$v===''?null:$v;}
    private function nullableInt(mixed $v): ?int{$v=trim((string)$v);return$v===''?null:max(0,(int)preg_replace('/\D+/','',$v));}
    private function nullableFloat(mixed $v): ?float{$v=trim(str_replace(',','.',(string)$v));return$v===''?null:(float)$v;}
    private function assertEtapa1Aberta(int $id): void { $pdo=Database::connection(); EtapaArchiveSchema::ensure($pdo); $st=$pdo->prepare('SELECT id FROM arquivamentos_etapa WHERE municipio_id=? AND etapa_numero=1 LIMIT 1'); $st->execute([$id]); if($st->fetchColumn())throw new RuntimeException('A Etapa 1 deste município está encerrada e arquivada. Alterações cadastrais que alterem o estado da instância estão bloqueadas.'); }
    private function color(string $value,string $fallback): string{$v=strtoupper(trim($value));return preg_match('/^#[0-9A-F]{6}$/',$v)?$v:$fallback;}
}

