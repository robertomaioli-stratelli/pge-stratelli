<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;

final class GlobalSearchService
{
    private PDO $pdo;
    private array $user;
    private bool $platform;
    private string $role;
    private int $municipioId;
    private int $secretariaId;
    private int $departamentoId;

    public function __construct()
    {
        $this->pdo=Database::connection();
        $this->user=Auth::user()??[];
        $this->platform=Auth::isPlatformAdmin();
        $this->role=(string)($this->user['grupo']??'');
        $this->municipioId=(int)($this->user['municipio_id']??0);
        $this->secretariaId=(int)($this->user['secretaria_id']??0);
        $this->departamentoId=(int)($this->user['departamento_id']??0);
    }

    public function search(string $query,int $limitPerGroup=20): array
    {
        $query=trim(preg_replace('/\s+/u',' ',$query)??'');
        $len=function_exists('mb_strlen')?mb_strlen($query,'UTF-8'):strlen($query);
        if($len<2){
            return ['query'=>$query,'groups'=>[],'displayed'=>0,'groupCount'=>0,'minimum'=>2];
        }
        $limit=max(1,min(50,$limitPerGroup));
        $groups=[];
        $this->pushGroup($groups,'municipios','Municípios','▥',$this->municipios($query,$limit));
        if($this->platform)$this->pushGroup($groups,'usuarios','Usuários','◉',$this->usuarios($query,$limit));
        $this->pushGroup($groups,'secretarias','Secretarias','▤',$this->secretarias($query,$limit));
        $this->pushGroup($groups,'documentos','Documentos','▣',$this->documentos($query,$limit));
        $this->pushGroup($groups,'fases','Fases','◇',$this->fases($query,$limit));
        $this->pushGroup($groups,'arquivos','Arquivos','⌁',$this->arquivos($query,$limit));
        if($this->territorialAllowed())$this->pushGroup($groups,'territorio','Objetos territoriais','⌖',$this->territorio($query,$limit));
        $displayed=array_sum(array_map(fn($g)=>count($g['items']),$groups));
        return ['query'=>$query,'groups'=>$groups,'displayed'=>$displayed,'groupCount'=>count($groups),'minimum'=>2];
    }

    private function municipios(string $q,int $limit): array
    {
        $sql='SELECT m.id,m.nome,m.uf,m.slug,m.codigo_ibge,m.status,m.ativo FROM municipios m WHERE LOCATE(LOWER(?),LOWER(CONCAT_WS(" ",m.nome,m.uf,m.slug,m.codigo_ibge,m.status)))>0';$params=[$q];
        if(!$this->platform){$sql.=' AND m.id=?';$params[]=$this->municipioId;}
        $sql.=' ORDER BY m.ativo DESC,m.nome LIMIT '.$limit;
        $rows=$this->all($sql,$params);$items=[];
        foreach($rows as $r){$items[]=$this->item('municipio',$r['nome'].' - '.$r['uf'],'Município',trim('IBGE '.($r['codigo_ibge']?:'não informado').' · '.str_replace('_',' ',$r['status'])),$this->platform?'/admin/municipios/'.(int)$r['id']:'/'.rawurlencode($r['slug']).'/dashboard',$r['nome'].' - '.$r['uf'],(int)$r['ativo']===1?'ATIVO':'INATIVO');}
        return $items;
    }

    private function usuarios(string $q,int $limit): array
    {
        $rows=$this->all('SELECT u.id,u.nome,u.email,u.grupo,u.administrador_plataforma,u.ativo,m.nome municipio_nome,m.uf municipio_uf FROM usuarios u LEFT JOIN municipios m ON m.id=u.municipio_id WHERE LOCATE(LOWER(?),LOWER(CONCAT_WS(" ",u.nome,u.email,u.grupo,m.nome,m.uf)))>0 ORDER BY u.administrador_plataforma DESC,u.ativo DESC,u.nome LIMIT '.$limit,[$q]);$items=[];
        foreach($rows as $r){$municipio=(int)$r['administrador_plataforma']===1?'STRATELLI':trim(($r['municipio_nome']??'').' - '.($r['municipio_uf']??''));$items[]=$this->item('usuario',$r['nome'],$r['email'],($r['administrador_plataforma']?'Administrador Stratelli':$r['grupo']).' · '.((int)$r['ativo']===1?'Ativo':'Inativo'),'/admin/usuarios/'.(int)$r['id'],$municipio,'USUÁRIO');}
        return $items;
    }

    private function secretarias(string $q,int $limit): array
    {
        $sql='SELECT s.id,s.nome,s.sigla,s.ativo,m.nome municipio_nome,m.uf,m.slug FROM secretarias s JOIN municipios m ON m.id=s.municipio_id WHERE LOCATE(LOWER(?),LOWER(CONCAT_WS(" ",s.nome,s.sigla,m.nome,m.uf)))>0';$params=[$q];
        if(!$this->platform){$sql.=' AND s.municipio_id=?';$params[]=$this->municipioId;if($this->role==='USUARIO'){$sql.=' AND s.id=?';$params[]=$this->secretariaId;}}
        $sql.=' ORDER BY s.ativo DESC,m.nome,s.nome LIMIT '.$limit;$rows=$this->all($sql,$params);$items=[];
        foreach($rows as $r){$url=$this->platform?'/'.rawurlencode($r['slug']).'/configuracoes?secao=secretarias&editar_secretaria='.(int)$r['id']:'/'.rawurlencode($r['slug']).'/workflow';$items[]=$this->item('secretaria',($r['sigla']?$r['sigla'].' — ':'').$r['nome'],'Secretaria municipal',(int)$r['ativo']===1?'Ativa':'Inativa',$url,$r['municipio_nome'].' - '.$r['uf'],'SECRETARIA');}
        return $items;
    }

    private function fases(string $q,int $limit): array
    {
        $sql='SELECT f.id,f.ordem,f.codigo,f.aba,f.titulo,f.descricao,f.entregavel,f.exclusivo_stratelli,m.nome municipio_nome,m.uf,m.slug,
            CASE WHEN EXISTS(SELECT 1 FROM fases fp LEFT JOIN cronograma_fases cf ON cf.municipio_id=fp.municipio_id AND cf.fase_id=fp.id WHERE fp.municipio_id=f.municipio_id AND fp.ativo=1 AND fp.ordem<f.ordem AND COALESCE(cf.status,"")<>"ENCERRADA") THEN 1 ELSE 0 END bloqueada
            FROM fases f JOIN municipios m ON m.id=f.municipio_id WHERE f.ativo=1 AND LOCATE(LOWER(?),LOWER(CONCAT_WS(" ",f.codigo,f.aba,f.titulo,f.descricao,f.entregavel,m.nome,m.uf)))>0';$params=[$q];
        if(!$this->platform){$sql.=' AND f.municipio_id=? AND f.exclusivo_stratelli=0';$params[]=$this->municipioId;if($this->role==='USUARIO'){$sql.=' AND EXISTS(SELECT 1 FROM requisitos_documentais rr WHERE rr.municipio_id=f.municipio_id AND rr.fase_id=f.id AND rr.ativo=1 AND rr.perfil_envio="MUNICIPIO" AND rr.secretaria_id=?';$params[]=$this->secretariaId;if($this->departamentoId){$sql.=' AND (rr.departamento_id IS NULL OR rr.departamento_id=?)';$params[]=$this->departamentoId;}$sql.=')';}}
        $sql.=' ORDER BY m.nome,f.ordem LIMIT '.$limit;$rows=$this->all($sql,$params);$items=[];
        foreach($rows as $r){$blocked=!$this->platform&&(int)$r['bloqueada']===1;$url='/'.rawurlencode($r['slug']).'/workflow'.($blocked?'':'/fase/'.(int)$r['id']);$meta='Fase '.$r['ordem'].' · '.$r['aba'].($blocked?' · Aguardando encerramento da fase anterior':'');$items[]=$this->item('fase',$r['titulo'],$meta,$r['entregavel']?:'Sem entregável informado',$url,$r['municipio_nome'].' - '.$r['uf'],$blocked?'BLOQUEADA':'FASE');}
        return $items;
    }

    private function documentos(string $q,int $limit): array
    {
        $sql='SELECT r.id,r.nome,r.descricao,r.perfil_envio,f.id fase_id,f.ordem fase_ordem,f.aba fase_aba,f.exclusivo_stratelli,s.nome secretaria_nome,s.sigla secretaria_sigla,d.nome departamento_nome,t.nome tipo_nome,m.nome municipio_nome,m.uf,m.slug,
            CASE WHEN EXISTS(SELECT 1 FROM fases fp LEFT JOIN cronograma_fases cf ON cf.municipio_id=fp.municipio_id AND cf.fase_id=fp.id WHERE fp.municipio_id=f.municipio_id AND fp.ativo=1 AND fp.ordem<f.ordem AND COALESCE(cf.status,"")<>"ENCERRADA") THEN 1 ELSE 0 END bloqueada
            FROM requisitos_documentais r JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id LEFT JOIN departamentos d ON d.id=r.departamento_id AND d.municipio_id=r.municipio_id JOIN tipos_documento t ON t.id=r.tipo_documento_id AND t.municipio_id=r.municipio_id JOIN municipios m ON m.id=r.municipio_id WHERE r.ativo=1 AND f.ativo=1 AND LOCATE(LOWER(?),LOWER(CONCAT_WS(" ",r.nome,r.descricao,t.nome,s.nome,s.sigla,d.nome,f.aba,f.titulo,m.nome,m.uf)))>0';$params=[$q];
        if(!$this->platform){$sql.=' AND r.municipio_id=? AND r.perfil_envio="MUNICIPIO" AND f.exclusivo_stratelli=0';$params[]=$this->municipioId;if($this->role==='USUARIO'){$sql.=' AND r.secretaria_id=?';$params[]=$this->secretariaId;if($this->departamentoId){$sql.=' AND (r.departamento_id IS NULL OR r.departamento_id=?)';$params[]=$this->departamentoId;}}}
        $sql.=' ORDER BY m.nome,f.ordem,r.ordem,r.nome LIMIT '.$limit;$rows=$this->all($sql,$params);$items=[];
        foreach($rows as $r){$blocked=!$this->platform&&(int)$r['bloqueada']===1;$url='/'.rawurlencode($r['slug']).'/workflow'.($blocked?'':'/fase/'.(int)$r['fase_id']);$owner=($r['secretaria_sigla']?:$r['secretaria_nome']).($r['departamento_nome']?' · '.$r['departamento_nome']:'');$items[]=$this->item('documento',$r['nome'],'Fase '.$r['fase_ordem'].' · '.$r['tipo_nome'],$owner.($blocked?' · Fase bloqueada':''),$url,$r['municipio_nome'].' - '.$r['uf'],$r['perfil_envio']);}
        return $items;
    }

    private function arquivos(string $q,int $limit): array
    {
        $filters=$this->requirementScope('r','f');
        $docSql='SELECT "DOCUMENTO" origem,d.id,d.requisito_id,d.versao,d.arquivo_original,d.status,d.enviado_em data_arquivo,r.nome requisito_nome,f.id fase_id,f.ordem fase_ordem,f.aba fase_aba,m.nome municipio_nome,m.uf,m.slug,
            CASE WHEN EXISTS(SELECT 1 FROM fases fp LEFT JOIN cronograma_fases cf ON cf.municipio_id=fp.municipio_id AND cf.fase_id=fp.id WHERE fp.municipio_id=f.municipio_id AND fp.ativo=1 AND fp.ordem<f.ordem AND COALESCE(cf.status,"")<>"ENCERRADA") THEN 1 ELSE 0 END bloqueada
            FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id JOIN municipios m ON m.id=d.municipio_id WHERE r.ativo=1 AND f.ativo=1 AND LOCATE(LOWER(?),LOWER(CONCAT_WS(" ",d.arquivo_original,r.nome,f.aba,m.nome,m.uf)))>0'.$filters['sql'].' ORDER BY d.enviado_em DESC LIMIT '.$limit;
        $docParams=array_merge([$q],$filters['params']);$docs=$this->all($docSql,$docParams);
        $modelSql='SELECT "MODELO" origem,md.id,md.requisito_id,md.versao,md.arquivo_original,"MODELO" status,md.criado_em data_arquivo,r.nome requisito_nome,f.id fase_id,f.ordem fase_ordem,f.aba fase_aba,m.nome municipio_nome,m.uf,m.slug,
            CASE WHEN EXISTS(SELECT 1 FROM fases fp LEFT JOIN cronograma_fases cf ON cf.municipio_id=fp.municipio_id AND cf.fase_id=fp.id WHERE fp.municipio_id=f.municipio_id AND fp.ativo=1 AND fp.ordem<f.ordem AND COALESCE(cf.status,"")<>"ENCERRADA") THEN 1 ELSE 0 END bloqueada
            FROM modelos_documentos md JOIN requisitos_documentais r ON r.id=md.requisito_id AND r.municipio_id=md.municipio_id JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id JOIN municipios m ON m.id=md.municipio_id WHERE r.ativo=1 AND f.ativo=1 AND LOCATE(LOWER(?),LOWER(CONCAT_WS(" ",md.arquivo_original,r.nome,f.aba,m.nome,m.uf)))>0'.$filters['sql'].' ORDER BY md.criado_em DESC LIMIT '.$limit;
        $models=$this->all($modelSql,array_merge([$q],$filters['params']));$rows=array_merge($docs,$models);usort($rows,fn($a,$b)=>strcmp((string)$b['data_arquivo'],(string)$a['data_arquivo']));$rows=array_slice($rows,0,$limit);$items=[];
        foreach($rows as $r){$blocked=!$this->platform&&(int)$r['bloqueada']===1;$url=$blocked?'/'.rawurlencode($r['slug']).'/workflow':'/'.rawurlencode($r['slug']).'/documentos/'.(int)$r['requisito_id'].'/auditoria';$sub=$r['origem']==='MODELO'?'Modelo documental':'Arquivo enviado';$items[]=$this->item('arquivo',$r['arquivo_original'],$sub.' · versão '.$r['versao'],$r['requisito_nome'].' · Fase '.$r['fase_ordem'].($blocked?' · Fase bloqueada':''),$url,$r['municipio_nome'].' - '.$r['uf'],$r['origem']);}
        return $items;
    }

    private function territorio(string $q,int $limit): array
    {
        $sql='SELECT o.id,o.nome,o.descricao,o.endereco,o.status,o.ativo,c.nome camada_nome,COALESCE(o.secretaria_id,c.secretaria_id) secretaria_efetiva_id,o.departamento_id,s.nome secretaria_nome,s.sigla secretaria_sigla,m.nome municipio_nome,m.uf,m.slug FROM objetos_territoriais o JOIN camadas_territoriais c ON c.id=o.camada_id AND c.municipio_id=o.municipio_id LEFT JOIN secretarias s ON s.id=COALESCE(o.secretaria_id,c.secretaria_id) AND s.municipio_id=o.municipio_id JOIN municipios m ON m.id=o.municipio_id WHERE LOCATE(LOWER(?),LOWER(CONCAT_WS(" ",o.nome,o.descricao,o.endereco,o.status,c.nome,s.nome,s.sigla,m.nome,m.uf)))>0';$params=[$q];
        if(!$this->platform){$sql.=' AND o.municipio_id=? AND o.ativo=1 AND c.ativo=1';$params[]=$this->municipioId;if($this->role==='USUARIO'){$sql.=' AND (COALESCE(o.secretaria_id,c.secretaria_id) IS NULL OR COALESCE(o.secretaria_id,c.secretaria_id)=?)';$params[]=$this->secretariaId;if($this->departamentoId){$sql.=' AND (o.departamento_id IS NULL OR o.departamento_id=?)';$params[]=$this->departamentoId;}}}
        $sql.=' ORDER BY m.nome,c.nome,o.nome LIMIT '.$limit;$rows=$this->all($sql,$params);$items=[];
        foreach($rows as $r){$items[]=$this->item('territorio',$r['nome'],$r['camada_nome'].' · '.$r['status'],$r['endereco']?:($r['descricao']?:'Objeto territorial'),'/'.rawurlencode($r['slug']).'/territorio?objeto='.(int)$r['id'],$r['municipio_nome'].' - '.$r['uf'],'TERRITÓRIO');}
        return $items;
    }

    private function requirementScope(string $r,string $f): array
    {
        if($this->platform)return ['sql'=>'','params'=>[]];
        $sql=' AND '.$r.'.municipio_id=? AND '.$r.'.perfil_envio="MUNICIPIO" AND '.$f.'.exclusivo_stratelli=0';$params=[$this->municipioId];
        if($this->role==='USUARIO'){$sql.=' AND '.$r.'.secretaria_id=?';$params[]=$this->secretariaId;if($this->departamentoId){$sql.=' AND ('.$r.'.departamento_id IS NULL OR '.$r.'.departamento_id=?)';$params[]=$this->departamentoId;}}
        return ['sql'=>$sql,'params'=>$params];
    }

    private function territorialAllowed(): bool
    {
        if($this->platform)return true;
        return (int)($this->user['municipio_territorial_ativa']??0)===1;
    }

    private function pushGroup(array &$groups,string $key,string $label,string $icon,array $items): void
    {
        if($items)$groups[]=['key'=>$key,'label'=>$label,'icon'=>$icon,'items'=>$items,'count'=>count($items)];
    }

    private function item(string $type,string $title,string $subtitle,string $meta,string $url,string $municipio,string $badge): array
    {
        return ['type'=>$type,'title'=>$title,'subtitle'=>$subtitle,'meta'=>$meta,'url'=>$url,'municipio'=>$municipio,'badge'=>$badge];
    }

    private function all(string $sql,array $params=[]): array
    {
        $st=$this->pdo->prepare($sql);$st->execute($params);return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
