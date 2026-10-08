<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use PDO;
use RuntimeException;

final class ConfigurationService
{
    private PDO $pdo; private int $mid;
    public function __construct(){if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode alterar as configurações.');$this->pdo=Database::connection();$this->mid=(int)Tenant::id();(new EtapaArchiveService())->assertOpen();}

    public function savePhase(array $d): void
    {
        $id=(int)($d['id']??0);if($id)(new PhaseClosureService())->assertOpen($id);$order=max(0,(int)($d['ordem']??0));$code=trim((string)($d['codigo']??''))?:'FASE-'.str_pad((string)$order,2,'0',STR_PAD_LEFT);$tab=trim((string)($d['aba']??''));$title=trim((string)($d['titulo']??''));$start=max(1,(int)($d['dia_inicio']??1));$end=max(1,(int)($d['dia_fim']??1));if($tab===''||$title==='')throw new RuntimeException('Informe o nome curto e o título da fase.');if($end<$start)throw new RuntimeException('O dia final não pode ser menor que o inicial.');$p=[$order,$code,$tab,$title,trim((string)($d['descricao']??'')),trim((string)($d['responsavel']??'')),$start,$end,trim((string)($d['entregavel']??'')),trim((string)($d['criterio']??'')),isset($d['exclusivo_stratelli'])?1:0];if($id){$p[]=$id;$p[]=$this->mid;$this->pdo->prepare('UPDATE fases SET ordem=?,codigo=?,aba=?,titulo=?,descricao=?,responsavel=?,dia_inicio=?,dia_fim=?,entregavel=?,criterio=?,exclusivo_stratelli=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute($p);}else{$this->pdo->prepare('INSERT INTO fases(municipio_id,ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,exclusivo_stratelli,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())')->execute(array_merge([$this->mid],$p));}
    }
    public function togglePhase(int $id): void{(new PhaseClosureService())->assertOpen($id);$this->pdo->prepare('UPDATE fases SET ativo=IF(ativo=1,0,1),atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$id,$this->mid]);}

    public function saveActivity(array $d): void
    {
        $id=(int)($d['id']??0);$fid=(int)($d['fase_id']??0);$order=max(1,(int)($d['ordem']??1));
        $code=trim((string)($d['codigo']??''));$title=trim((string)($d['titulo']??''));
        $description=trim((string)($d['descricao']??''));$responsible=trim((string)($d['responsavel']??''));
        $qty=trim((string)($d['prazo_quantidade']??''));$qty=$qty===''?null:max(1,(int)$qty);
        $allowed=['DIAS_CORRIDOS','DIAS_UTEIS','DATA_FIXADA','DURANTE_FASE','IMEDIATO','CONFORME_CRONOGRAMA','SEM_PRAZO'];
        $type=(string)($d['prazo_tipo']??'SEM_PRAZO');if(!in_array($type,$allowed,true))$type='SEM_PRAZO';
        if(!$fid||$title==='')throw new RuntimeException('Informe a fase e o nome do ato/atividade.');
        (new PhaseClosureService())->assertOpen($fid);
        $q=$this->pdo->prepare('SELECT id FROM fases WHERE id=? AND municipio_id=?');$q->execute([$fid,$this->mid]);if(!$q->fetchColumn())throw new RuntimeException('Fase inválida.');
        if($code==='')$code='ATO-'.$fid.'-'.str_pad((string)$order,2,'0',STR_PAD_LEFT);
        $args=[$fid,$order,$code,$title,$description,$responsible,$qty,$type,trim((string)($d['prazo_referencia']??'')),trim((string)($d['fundamento_normativo']??'')),trim((string)($d['entregavel']??'')),trim((string)($d['criterio']??'')),isset($d['obrigatoria'])?1:0];
        if($id){$this->pdo->prepare('UPDATE atividades_fase SET fase_id=?,ordem=?,codigo=?,titulo=?,descricao=?,responsavel=?,prazo_quantidade=?,prazo_tipo=?,prazo_referencia=?,fundamento_normativo=?,entregavel=?,criterio=?,obrigatoria=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute(array_merge($args,[$id,$this->mid]));}
        else{$this->pdo->prepare('INSERT INTO atividades_fase(municipio_id,fase_id,ordem,codigo,titulo,descricao,responsavel,prazo_quantidade,prazo_tipo,prazo_referencia,fundamento_normativo,entregavel,criterio,obrigatoria,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())')->execute(array_merge([$this->mid],$args));}
    }
    public function toggleActivity(int $id): void
    {
        $q=$this->pdo->prepare('SELECT fase_id FROM atividades_fase WHERE id=? AND municipio_id=?');$q->execute([$id,$this->mid]);$fid=(int)$q->fetchColumn();
        if(!$fid)throw new RuntimeException('Ato/atividade não localizado.');
        (new PhaseClosureService())->assertOpen($fid);
        $this->pdo->prepare('UPDATE atividades_fase SET ativo=IF(ativo=1,0,1),atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$id,$this->mid]);
    }

    public function saveSecretaria(array $d): void
    {
        $id=(int)($d['id']??0);$name=trim((string)($d['nome']??''));$sigla=strtoupper(trim((string)($d['sigla']??'')));
        if($name==='')throw new RuntimeException('Informe o nome da secretaria.');
        $activityIds=array_values(array_unique(array_filter(array_map('intval',(array)($d['atividade_ids']??[])))));
        $phaseIds=array_values(array_unique(array_filter(array_map('intval',(array)($d['fase_ids']??[])))));
        if($activityIds){$marks=implode(',',array_fill(0,count($activityIds),'?'));$st=$this->pdo->prepare("SELECT id,fase_id FROM atividades_fase WHERE municipio_id=? AND ativo=1 AND id IN ($marks)");$st->execute(array_merge([$this->mid],$activityIds));$valid=[];foreach($st->fetchAll()as$a){$valid[]=(int)$a['id'];$phaseIds[]=(int)$a['fase_id'];}if(count($valid)!==count($activityIds))throw new RuntimeException('Há ato/atividade inválido no vínculo da secretaria.');$activityIds=$valid;$phaseIds=array_values(array_unique($phaseIds));}
        $this->pdo->beginTransaction();try{
            if($id)$this->pdo->prepare('UPDATE secretarias SET nome=?,sigla=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$name,$sigla,$id,$this->mid]);
            else{$this->pdo->prepare('INSERT INTO secretarias(municipio_id,nome,sigla,ativo,criado_em,atualizado_em) VALUES(?,?,?,1,NOW(),NOW())')->execute([$this->mid,$name,$sigla]);$id=(int)$this->pdo->lastInsertId();}
            $this->pdo->prepare('DELETE FROM atividade_secretarias WHERE municipio_id=? AND secretaria_id=?')->execute([$this->mid,$id]);
            $this->pdo->prepare('DELETE FROM fase_secretarias WHERE municipio_id=? AND secretaria_id=?')->execute([$this->mid,$id]);
            foreach($phaseIds as$fid)$this->pdo->prepare('INSERT IGNORE INTO fase_secretarias(municipio_id,fase_id,secretaria_id) VALUES(?,?,?)')->execute([$this->mid,$fid,$id]);
            foreach($activityIds as$aid)$this->pdo->prepare('INSERT IGNORE INTO atividade_secretarias(municipio_id,atividade_id,secretaria_id) VALUES(?,?,?)')->execute([$this->mid,$aid,$id]);
            $this->pdo->commit();
        }catch(\Throwable$e){$this->pdo->rollBack();throw$e;}
    }
    public function toggleSecretaria(int $id): void{$this->pdo->prepare('UPDATE secretarias SET ativo=IF(ativo=1,0,1),atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$id,$this->mid]);}
    public function saveDepartamento(array $d): void{$id=(int)($d['id']??0);$sid=(int)($d['secretaria_id']??0);$name=trim((string)($d['nome']??''));$sigla=strtoupper(trim((string)($d['sigla']??'')));if(!$sid||$name==='')throw new RuntimeException('Informe a secretaria e o departamento.');if($id)$this->pdo->prepare('UPDATE departamentos SET secretaria_id=?,nome=?,sigla=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$sid,$name,$sigla,$id,$this->mid]);else$this->pdo->prepare('INSERT INTO departamentos(municipio_id,secretaria_id,nome,sigla,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,1,NOW(),NOW())')->execute([$this->mid,$sid,$name,$sigla]);}
    public function toggleDepartamento(int $id): void{$this->pdo->prepare('UPDATE departamentos SET ativo=IF(ativo=1,0,1),atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$id,$this->mid]);}
    public function saveTipo(array $d): void{$id=(int)($d['id']??0);$name=trim((string)($d['nome']??''));$desc=trim((string)($d['descricao']??''));$ext=$this->normalizeExtensions((string)($d['extensoes']??''));if($name===''||$ext==='')throw new RuntimeException('Informe o nome e as extensões permitidas.');if($id)$this->pdo->prepare('UPDATE tipos_documento SET nome=?,descricao=?,extensoes=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$name,$desc,$ext,$id,$this->mid]);else$this->pdo->prepare('INSERT INTO tipos_documento(municipio_id,nome,descricao,extensoes,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,1,NOW(),NOW())')->execute([$this->mid,$name,$desc,$ext]);}
    public function toggleTipo(int $id): void{$this->pdo->prepare('UPDATE tipos_documento SET ativo=IF(ativo=1,0,1),atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$id,$this->mid]);}
    public function saveRequirement(array $d): int
    {
        $id=(int)($d['id']??0);$aid=(int)($d['atividade_id']??0);$sid=(int)($d['secretaria_id']??0);$did=(int)($d['departamento_id']??0);$tid=(int)($d['tipo_documento_id']??0);$name=trim((string)($d['nome']??''));$profile=(($d['perfil_envio']??'MUNICIPIO')==='STRATELLI'?'STRATELLI':'MUNICIPIO');$order=max(1,(int)($d['ordem']??1));$required=isset($d['obrigatorio'])?1:0;
        if(!$aid||!$sid||!$tid||$name==='')throw new RuntimeException('Preencha ato/atividade, secretaria, tipo e nome do documento.');
        $st=$this->pdo->prepare('SELECT a.fase_id FROM atividades_fase a JOIN fases f ON f.id=a.fase_id AND f.municipio_id=a.municipio_id WHERE a.id=? AND a.municipio_id=? AND a.ativo=1 AND f.ativo=1');$st->execute([$aid,$this->mid]);$fid=(int)$st->fetchColumn();
        if(!$fid)throw new RuntimeException('O ato/atividade selecionado não é válido ou está inativo.');
        $postedFid=(int)($d['fase_id']??0);if($postedFid&&$postedFid!==$fid)throw new RuntimeException('O ato/atividade selecionado não pertence à fase informada.');
        $closure=new PhaseClosureService();$closure->assertOpen($fid);
        if($id){$q=$this->pdo->prepare('SELECT fase_id FROM requisitos_documentais WHERE id=? AND municipio_id=?');$q->execute([$id,$this->mid]);$oldFid=(int)$q->fetchColumn();if($oldFid&&$oldFid!==$fid)$closure->assertOpen($oldFid);}
        if($did){$st=$this->pdo->prepare('SELECT secretaria_id FROM departamentos WHERE id=? AND municipio_id=?');$st->execute([$did,$this->mid]);if((int)$st->fetchColumn()!==$sid)throw new RuntimeException('O departamento não pertence à secretaria informada.');}
        $this->pdo->prepare('INSERT IGNORE INTO fase_secretarias(municipio_id,fase_id,secretaria_id) VALUES(?,?,?)')->execute([$this->mid,$fid,$sid]);
        $this->pdo->prepare('INSERT IGNORE INTO atividade_secretarias(municipio_id,atividade_id,secretaria_id) VALUES(?,?,?)')->execute([$this->mid,$aid,$sid]);
        $args=[$fid,$aid,$sid,$did?:null,$tid,$name,trim((string)($d['descricao']??'')),$profile,$required,$order];
        if($id){$this->pdo->prepare('UPDATE requisitos_documentais SET fase_id=?,atividade_id=?,secretaria_id=?,departamento_id=?,tipo_documento_id=?,nome=?,descricao=?,perfil_envio=?,obrigatorio=?,ordem=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute(array_merge($args,[$id,$this->mid]));}
        else{$this->pdo->prepare('INSERT INTO requisitos_documentais(municipio_id,fase_id,atividade_id,secretaria_id,departamento_id,tipo_documento_id,nome,descricao,perfil_envio,obrigatorio,ativo,ordem,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,1,?,NOW(),NOW())')->execute(array_merge([$this->mid],$args));$id=(int)$this->pdo->lastInsertId();}
        if(isset($_FILES['arquivo_modelo'])&&($_FILES['arquivo_modelo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){(new DocumentService())->uploadModel($id,[$id]);}
        return $id;
    }
    public function toggleRequirement(int $id): void{$q=$this->pdo->prepare('SELECT fase_id FROM requisitos_documentais WHERE id=? AND municipio_id=?');$q->execute([$id,$this->mid]);$fid=(int)$q->fetchColumn();if($fid)(new PhaseClosureService())->assertOpen($fid);$this->pdo->prepare('UPDATE requisitos_documentais SET ativo=IF(ativo=1,0,1),atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$id,$this->mid]);}
    private function normalizeExtensions(string$v):string{$items=preg_split('/[,;\s]+/',strtolower($v),-1,PREG_SPLIT_NO_EMPTY)?:[];$items=array_values(array_unique(array_map(fn($x)=>ltrim(trim($x),'.'),$items)));return implode(',',$items);}
}
