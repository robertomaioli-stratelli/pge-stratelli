<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use PDO;
use RuntimeException;

final class TerritorialService
{
    private PDO $pdo;
    private int $municipioId;
    private array $user;
    private string $scope;

    public function __construct()
    {
        $this->pdo=Database::connection();
        $this->municipioId=(int)(Tenant::id()??0);
        $this->user=Auth::user()??[];
        $this->scope=Auth::isPlatformAdmin()?'stratelli':(($this->user['grupo']??'')==='USUARIO'?'secretaria':'municipio');
        if(!$this->municipioId) throw new RuntimeException('Instância municipal não definida.');
    }

    public function load(): array
    {
        $canManage=Auth::isPlatformAdmin();
        $secretarias=$this->all('SELECT id,nome,sigla,ativo FROM secretarias WHERE municipio_id=? AND ativo=1 ORDER BY nome',[$this->municipioId]);
        $departamentos=$this->all('SELECT d.id,d.secretaria_id,d.nome,d.sigla,s.nome secretaria_nome,s.sigla secretaria_sigla FROM departamentos d JOIN secretarias s ON s.id=d.secretaria_id AND s.municipio_id=d.municipio_id WHERE d.municipio_id=? AND d.ativo=1 ORDER BY s.nome,d.nome',[$this->municipioId]);
        $fases=$this->visiblePhases();
        $camadas=$this->layers();
        $objetos=$this->objects();
        $summary=$this->summarize($camadas,$objetos);
        $categorias=array_values(array_unique(array_filter(array_map(fn($c)=>trim((string)$c['categoria']),$camadas))));
        sort($categorias,SORT_NATURAL|SORT_FLAG_CASE);
        return compact('canManage','secretarias','departamentos','fases','camadas','objetos','summary','categorias')+['scope'=>$this->scope];
    }

    public function summaryOnly(): array
    {
        return $this->summarize($this->layers(),$this->objects());
    }

    public function saveLayer(array $data): int
    {
        $this->assertManage();
        $id=max(0,(int)($data['id']??0));
        $nome=trim((string)($data['nome']??''));
        $descricao=trim((string)($data['descricao']??''));
        $categoria=trim((string)($data['categoria']??''));
        $tipo=strtoupper(trim((string)($data['tipo_geometria']??'POINT')));
        $secretariaId=(int)($data['secretaria_id']??0)?:null;
        $icone=trim((string)($data['icone']??'pin'))?:'pin';
        $cor=trim((string)($data['cor']??'#176fdd'))?:'#176fdd';
        $ordem=max(1,(int)($data['ordem']??1));
        if($nome==='') throw new RuntimeException('Informe o nome da camada.');
        if(!in_array($tipo,['POINT','LINESTRING','POLYGON','MIXED'],true)) throw new RuntimeException('Tipo de geometria inválido.');
        if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$cor)) $cor='#176fdd';
        if($secretariaId)$this->assertSecretaria($secretariaId);
        if($id){
            $st=$this->pdo->prepare('UPDATE camadas_territoriais SET nome=?,descricao=?,categoria=?,tipo_geometria=?,secretaria_id=?,icone=?,cor=?,ordem=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?');
            $st->execute([$nome,$descricao,$categoria,$tipo,$secretariaId,$icone,$cor,$ordem,$id,$this->municipioId]);
            if(!$st->rowCount()&&!$this->exists('camadas_territoriais',$id))throw new RuntimeException('Camada não encontrada.');
            Audit::log('camada_territorial_atualizada','Camada territorial atualizada: '.$nome,$this->municipioId);
            return $id;
        }
        $st=$this->pdo->prepare('INSERT INTO camadas_territoriais(municipio_id,nome,descricao,categoria,tipo_geometria,secretaria_id,icone,cor,ordem,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,1,NOW(),NOW())');
        $st->execute([$this->municipioId,$nome,$descricao,$categoria,$tipo,$secretariaId,$icone,$cor,$ordem]);
        $id=(int)$this->pdo->lastInsertId();
        Audit::log('camada_territorial_criada','Camada territorial criada: '.$nome,$this->municipioId);
        return $id;
    }

    public function toggleLayer(int $id): void
    {
        $this->assertManage();
        $st=$this->pdo->prepare('UPDATE camadas_territoriais SET ativo=IF(ativo=1,0,1),atualizado_em=NOW() WHERE id=? AND municipio_id=?');
        $st->execute([$id,$this->municipioId]);
        if(!$st->rowCount())throw new RuntimeException('Camada não encontrada.');
        Audit::log('camada_territorial_status','Status de camada territorial alterado.',$this->municipioId);
    }

    public function saveObject(array $data): int
    {
        $this->assertManage();
        $id=max(0,(int)($data['id']??0));
        $camadaId=(int)($data['camada_id']??0);
        $layer=$this->one('SELECT * FROM camadas_territoriais WHERE id=? AND municipio_id=?',[$camadaId,$this->municipioId]);
        if(!$layer)throw new RuntimeException('Selecione uma camada válida.');
        $nome=trim((string)($data['nome']??''));
        $descricao=trim((string)($data['descricao']??''));
        $endereco=trim((string)($data['endereco']??''));
        $status=strtoupper(trim((string)($data['status']??'ATIVO')));
        $secretariaId=(int)($data['secretaria_id']??0)?:null;
        $departamentoId=(int)($data['departamento_id']??0)?:null;
        $faseId=(int)($data['fase_id']??0)?:null;
        $geometryRaw=trim((string)($data['geometria_geojson']??''));
        $atributosRaw=trim((string)($data['atributos_json']??''));
        if($geometryRaw==='' && $endereco!=='' && in_array((string)($layer['tipo_geometria']??''),['POINT','MIXED'],true)){
            $match=(new GeocodingService())->bestMatch($endereco);
            if(!$match) throw new RuntimeException('Não foi possível localizar este endereço. Revise o endereço ou marque o ponto manualmente no mapa.');
            if(empty($match['inside_municipio']) && !empty($this->one('SELECT geojson_delimitacao FROM municipios WHERE id=?',[$this->municipioId])['geojson_delimitacao'])){
                throw new RuntimeException('O endereço localizado parece estar fora do perímetro municipal. Revise o endereço ou marque o ponto manualmente.');
            }
            $geometryRaw=json_encode(['type'=>'Point','coordinates'=>[(float)$match['lng'],(float)$match['lat']]],JSON_UNESCAPED_SLASHES);
        }
        if($nome==='')throw new RuntimeException('Informe o nome do objeto territorial.');
        if(!in_array($status,['ATIVO','ATENCAO','CRITICO','CONCLUIDO','INATIVO'],true))throw new RuntimeException('Status territorial inválido.');
        if($secretariaId)$this->assertSecretaria($secretariaId);
        if($departamentoId&&!$secretariaId)throw new RuntimeException('Selecione a Secretaria antes de atribuir um Departamento.');
        if($departamentoId)$this->assertDepartamento($departamentoId,$secretariaId);
        if($faseId)$this->assertPhase($faseId);
        [$geometryJson,$geometryType,$lat,$lng]=$this->normalizeGeometry($geometryRaw);
        if(($layer['tipo_geometria']??'MIXED')!=='MIXED' && !$this->geometryMatches((string)$layer['tipo_geometria'],$geometryType)){
            throw new RuntimeException('A geometria desenhada não corresponde ao tipo configurado para esta camada.');
        }
        $attrs=null;
        if($atributosRaw!==''){
            $decoded=json_decode($atributosRaw,true);
            if(!is_array($decoded))throw new RuntimeException('Os atributos adicionais devem estar em JSON válido.');
            $attrs=json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        $isNew=$id===0;
        if($id){
            $st=$this->pdo->prepare('UPDATE objetos_territoriais SET camada_id=?,secretaria_id=?,departamento_id=?,nome=?,descricao=?,endereco=?,status=?,geometria_geojson=?,latitude=?,longitude=?,atributos_json=?,atualizado_por_usuario_id=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?');
            $st->execute([$camadaId,$secretariaId,$departamentoId,$nome,$descricao,$endereco,$status,$geometryJson,$lat,$lng,$attrs,Auth::id(),$id,$this->municipioId]);
            if(!$st->rowCount()&&!$this->exists('objetos_territoriais',$id))throw new RuntimeException('Objeto territorial não encontrado.');
        }else{
            $st=$this->pdo->prepare('INSERT INTO objetos_territoriais(municipio_id,camada_id,secretaria_id,departamento_id,nome,descricao,endereco,status,geometria_geojson,latitude,longitude,atributos_json,ativo,criado_por_usuario_id,atualizado_por_usuario_id,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,NOW(),NOW())');
            $st->execute([$this->municipioId,$camadaId,$secretariaId,$departamentoId,$nome,$descricao,$endereco,$status,$geometryJson,$lat,$lng,$attrs,Auth::id(),Auth::id()]);
            $id=(int)$this->pdo->lastInsertId();
        }
        $this->replacePhaseLink($id,$faseId,(string)($data['observacao_vinculo']??''));
        Audit::log($isNew?'objeto_territorial_criado':'objeto_territorial_atualizado','Objeto territorial salvo: '.$nome,$this->municipioId);
        return $id;
    }

    public function toggleObject(int $id): void
    {
        $this->assertManage();
        $st=$this->pdo->prepare('UPDATE objetos_territoriais SET ativo=IF(ativo=1,0,1),atualizado_por_usuario_id=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?');
        $st->execute([Auth::id(),$id,$this->municipioId]);
        if(!$st->rowCount())throw new RuntimeException('Objeto territorial não encontrado.');
        Audit::log('objeto_territorial_status','Status de objeto territorial alterado.',$this->municipioId);
    }

    public function importGeoJson(array $data,array $file): int
    {
        $this->assertManage();
        $camadaId=(int)($data['camada_id']??0);
        $layer=$this->one('SELECT * FROM camadas_territoriais WHERE id=? AND municipio_id=? AND ativo=1',[$camadaId,$this->municipioId]);
        if(!$layer)throw new RuntimeException('Selecione uma camada ativa.');
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Selecione um arquivo GeoJSON válido.');
        if((int)($file['size']??0)>12*1024*1024)throw new RuntimeException('O GeoJSON deve ter no máximo 12 MB.');
        $name=(string)($file['name']??'');$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($ext,['geojson','json'],true))throw new RuntimeException('Envie um arquivo .geojson ou .json.');
        $raw=file_get_contents((string)$file['tmp_name']);
        $json=json_decode((string)$raw,true);
        if(!is_array($json))throw new RuntimeException('O arquivo não contém JSON válido.');
        $features=[];
        if(($json['type']??'')==='FeatureCollection')$features=is_array($json['features']??null)?$json['features']:[];
        elseif(($json['type']??'')==='Feature')$features=[$json];
        elseif(isset($json['type'],$json['coordinates']))$features=[['type'=>'Feature','properties'=>[],'geometry'=>$json]];
        if(!$features)throw new RuntimeException('Nenhuma geometria foi encontrada no arquivo.');
        $secretariaId=(int)($data['secretaria_id']??0)?:null;$departamentoId=(int)($data['departamento_id']??0)?:null;$faseId=(int)($data['fase_id']??0)?:null;
        if($secretariaId)$this->assertSecretaria($secretariaId);if($departamentoId&&!$secretariaId)throw new RuntimeException('Selecione a Secretaria antes de atribuir um Departamento.');if($departamentoId)$this->assertDepartamento($departamentoId,$secretariaId);if($faseId)$this->assertPhase($faseId);
        $count=0;$this->pdo->beginTransaction();
        try{
            foreach($features as $i=>$feature){
                if(!is_array($feature)||!is_array($feature['geometry']??null))continue;
                [$geometryJson,$geometryType,$lat,$lng]=$this->normalizeGeometry(json_encode($feature['geometry'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                if(($layer['tipo_geometria']??'MIXED')!=='MIXED'&&!$this->geometryMatches((string)$layer['tipo_geometria'],$geometryType))continue;
                $props=is_array($feature['properties']??null)?$feature['properties']:[];
                $objName='Objeto importado '.($i+1);foreach(['nome','name','NOME','NAME','NM_MUN','label','LABEL','descricao'] as $k){if(isset($props[$k])&&trim((string)$props[$k])!==''){$objName=trim((string)$props[$k]);break;}}
                $attrs=$props?json_encode($props,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;
                $st=$this->pdo->prepare('INSERT INTO objetos_territoriais(municipio_id,camada_id,secretaria_id,departamento_id,nome,descricao,endereco,status,geometria_geojson,latitude,longitude,atributos_json,ativo,criado_por_usuario_id,atualizado_por_usuario_id,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,NOW(),NOW())');
                $st->execute([$this->municipioId,$camadaId,$secretariaId,$departamentoId,$objName,'Importado de '.$name,'','ATIVO',$geometryJson,$lat,$lng,$attrs,Auth::id(),Auth::id()]);
                $oid=(int)$this->pdo->lastInsertId();if($faseId)$this->insertPhaseLink($oid,$faseId,'Vínculo criado durante importação GeoJSON.');$count++;
            }
            $this->pdo->commit();
        }catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
        if(!$count)throw new RuntimeException('Nenhuma feição compatível com a camada foi importada.');
        Audit::log('geojson_territorial_importado',$count.' objeto(s) importados na camada '.$layer['nome'].'.',$this->municipioId);
        return $count;
    }

    private function layers(): array
    {
        $sql='SELECT c.*,s.nome secretaria_nome,s.sigla secretaria_sigla,(SELECT COUNT(*) FROM objetos_territoriais o WHERE o.municipio_id=c.municipio_id AND o.camada_id=c.id AND o.ativo=1) objetos_ativos FROM camadas_territoriais c LEFT JOIN secretarias s ON s.id=c.secretaria_id AND s.municipio_id=c.municipio_id WHERE c.municipio_id=?';$params=[$this->municipioId];
        if(!Auth::isPlatformAdmin())$sql.=' AND c.ativo=1';
        if($this->scope==='secretaria'){$sql.=' AND (c.secretaria_id IS NULL OR c.secretaria_id=?)';$params[]=(int)($this->user['secretaria_id']??0);}
        $sql.=' ORDER BY c.ordem,c.nome';return $this->all($sql,$params);
    }

    private function objects(): array
    {
        $sql='SELECT o.*,c.nome camada_nome,c.categoria camada_categoria,c.tipo_geometria camada_tipo,c.cor camada_cor,c.icone camada_icone,c.secretaria_id camada_secretaria_id,COALESCE(o.secretaria_id,c.secretaria_id) secretaria_efetiva_id,s.nome secretaria_nome,s.sigla secretaria_sigla,cs.nome camada_secretaria_nome,cs.sigla camada_secretaria_sigla,d.nome departamento_nome,d.sigla departamento_sigla,
            (SELECT v.fase_id FROM vinculos_territoriais v WHERE v.municipio_id=o.municipio_id AND v.objeto_territorial_id=o.id AND v.tipo_vinculo="FASE" ORDER BY v.id DESC LIMIT 1) fase_id,
            (SELECT f.ordem FROM vinculos_territoriais v JOIN fases f ON f.id=v.fase_id AND f.municipio_id=v.municipio_id WHERE v.municipio_id=o.municipio_id AND v.objeto_territorial_id=o.id AND v.tipo_vinculo="FASE" ORDER BY v.id DESC LIMIT 1) fase_ordem,
            (SELECT f.aba FROM vinculos_territoriais v JOIN fases f ON f.id=v.fase_id AND f.municipio_id=v.municipio_id WHERE v.municipio_id=o.municipio_id AND v.objeto_territorial_id=o.id AND v.tipo_vinculo="FASE" ORDER BY v.id DESC LIMIT 1) fase_aba
            FROM objetos_territoriais o JOIN camadas_territoriais c ON c.id=o.camada_id AND c.municipio_id=o.municipio_id LEFT JOIN secretarias s ON s.id=o.secretaria_id AND s.municipio_id=o.municipio_id LEFT JOIN secretarias cs ON cs.id=c.secretaria_id AND cs.municipio_id=c.municipio_id LEFT JOIN departamentos d ON d.id=o.departamento_id AND d.municipio_id=o.municipio_id WHERE o.municipio_id=?';$params=[$this->municipioId];
        if(!Auth::isPlatformAdmin())$sql.=' AND o.ativo=1 AND c.ativo=1';
        if($this->scope==='secretaria'){
            $sid=(int)($this->user['secretaria_id']??0);$did=(int)($this->user['departamento_id']??0);
            $sql.=' AND (COALESCE(o.secretaria_id,c.secretaria_id) IS NULL OR COALESCE(o.secretaria_id,c.secretaria_id)=?)';$params[]=$sid;
            if($did){$sql.=' AND (o.departamento_id IS NULL OR o.departamento_id=?)';$params[]=$did;}
        }
        $sql.=' ORDER BY c.ordem,c.nome,o.nome';return $this->all($sql,$params);
    }

    private function visiblePhases(): array
    {
        if($this->scope!=='secretaria')return $this->all('SELECT id,ordem,aba,titulo FROM fases WHERE municipio_id=? AND ativo=1 ORDER BY ordem,id',[$this->municipioId]);
        $sid=(int)($this->user['secretaria_id']??0);$did=(int)($this->user['departamento_id']??0);
        $sql='SELECT DISTINCT f.id,f.ordem,f.aba,f.titulo FROM fases f JOIN requisitos_documentais r ON r.fase_id=f.id AND r.municipio_id=f.municipio_id WHERE f.municipio_id=? AND f.ativo=1 AND r.ativo=1 AND r.secretaria_id=?';$params=[$this->municipioId,$sid];
        if($did){$sql.=' AND (r.departamento_id IS NULL OR r.departamento_id=?)';$params[]=$did;}
        $sql.=' ORDER BY f.ordem,f.id';return $this->all($sql,$params);
    }

    private function summarize(array $layers,array $objects): array
    {
        $active=array_values(array_filter($objects,fn($o)=>(int)$o['ativo']===1));
        $phaseIds=[];$attention=$critical=$completed=0;
        foreach($active as $o){if(!empty($o['fase_id']))$phaseIds[(int)$o['fase_id']]=true;$status=(string)$o['status'];if($status==='ATENCAO')$attention++;elseif($status==='CRITICO')$critical++;elseif($status==='CONCLUIDO')$completed++;}
        return ['camadas'=>count(array_filter($layers,fn($c)=>(int)$c['ativo']===1)),'objetos'=>count($active),'processos_vinculados'=>count($phaseIds),'atencao'=>$attention,'criticos'=>$critical,'concluidos'=>$completed];
    }

    private function normalizeGeometry(string $raw): array
    {
        if($raw==='')throw new RuntimeException('Desenhe ou informe a geometria do objeto.');
        $g=json_decode($raw,true);if(!is_array($g))throw new RuntimeException('Geometria GeoJSON inválida.');
        if(($g['type']??'')==='Feature')$g=$g['geometry']??null;
        if(!is_array($g)||empty($g['type'])||!array_key_exists('coordinates',$g))throw new RuntimeException('A geometria precisa estar no padrão GeoJSON.');
        $type=(string)$g['type'];$allowed=['Point','LineString','Polygon','MultiPoint','MultiLineString','MultiPolygon'];if(!in_array($type,$allowed,true))throw new RuntimeException('Tipo de geometria não suportado.');
        $coords=$g['coordinates'];if(!is_array($coords))throw new RuntimeException('Coordenadas inválidas.');
        [$lat,$lng]=$this->representativeCoordinate($type,$coords);
        return [json_encode($g,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$type,$lat,$lng];
    }

    private function representativeCoordinate(string $type,array $coords): array
    {
        if($type==='Point'&&count($coords)>=2)return [(float)$coords[1],(float)$coords[0]];
        $points=[];$walk=function($node)use(&$walk,&$points){if(is_array($node)&&count($node)>=2&&is_numeric($node[0])&&is_numeric($node[1])){$points[]=[(float)$node[1],(float)$node[0]];return;}if(is_array($node))foreach($node as $n)$walk($n);};$walk($coords);
        if(!$points)return [null,null];$lat=array_sum(array_column($points,0))/count($points);$lng=array_sum(array_column($points,1))/count($points);return [round($lat,7),round($lng,7)];
    }

    private function geometryMatches(string $layerType,string $geometryType): bool
    {
        return match($layerType){'POINT'=>in_array($geometryType,['Point','MultiPoint'],true),'LINESTRING'=>in_array($geometryType,['LineString','MultiLineString'],true),'POLYGON'=>in_array($geometryType,['Polygon','MultiPolygon'],true),default=>true};
    }

    private function replacePhaseLink(int $objectId,?int $phaseId,string $observation=''): void
    {
        $this->pdo->prepare('DELETE FROM vinculos_territoriais WHERE municipio_id=? AND objeto_territorial_id=? AND tipo_vinculo="FASE"')->execute([$this->municipioId,$objectId]);
        if($phaseId)$this->insertPhaseLink($objectId,$phaseId,$observation);
    }
    private function insertPhaseLink(int $objectId,int $phaseId,string $observation=''): void
    {
        $st=$this->pdo->prepare('INSERT INTO vinculos_territoriais(municipio_id,objeto_territorial_id,fase_id,requisito_id,documento_id,tipo_vinculo,observacao,criado_por_usuario_id,criado_em) VALUES(?,?,?,NULL,NULL,"FASE",?,?,NOW())');$st->execute([$this->municipioId,$objectId,$phaseId,$observation,Auth::id()]);
    }
    private function assertManage(): void {if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode administrar dados territoriais nesta versão.');(new EtapaArchiveService())->assertOpen();}
    private function assertSecretaria(int $id): void {if(!$this->one('SELECT id FROM secretarias WHERE id=? AND municipio_id=? AND ativo=1',[$id,$this->municipioId]))throw new RuntimeException('A Secretaria selecionada não pertence ao município.');}
    private function assertDepartamento(int $id,?int $secretariaId): void {$r=$this->one('SELECT secretaria_id FROM departamentos WHERE id=? AND municipio_id=? AND ativo=1',[$id,$this->municipioId]);if(!$r)throw new RuntimeException('Departamento inválido.');if($secretariaId&&(int)$r['secretaria_id']!==$secretariaId)throw new RuntimeException('O Departamento selecionado não pertence à Secretaria informada.');}
    private function assertPhase(int $id): void {if(!$this->one('SELECT id FROM fases WHERE id=? AND municipio_id=? AND ativo=1',[$id,$this->municipioId]))throw new RuntimeException('Fase inválida.');}
    private function exists(string $table,int $id): bool {$st=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id=? AND municipio_id=?");$st->execute([$id,$this->municipioId]);return (bool)$st->fetchColumn();}
    private function all(string $sql,array $params=[]): array {$st=$this->pdo->prepare($sql);$st->execute($params);return $st->fetchAll();}
    private function one(string $sql,array $params=[]): ?array {$st=$this->pdo->prepare($sql);$st->execute($params);$r=$st->fetch();return $r?:null;}
}
