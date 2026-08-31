<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use PDO;
use RuntimeException;

final class InstanceParameterService
{
    private PDO $pdo;
    private static array $cache=[];

    public function __construct(){ $this->pdo=Database::connection(); }

    public function defaults(): array
    {
        return [
            'prazo_alerta_dias'=>3,
            'tamanho_maximo_arquivo_mb'=>20,
            'extensoes_permitidas'=>'pdf,doc,docx,xls,xlsx,odt,ods,rtf,txt,png,jpg,jpeg,webp,csv,zip',
            'notificacoes_ativas'=>1,
            'observacao_envio_obrigatoria'=>0,
            'observacao_aprovacao_obrigatoria'=>0,
            'observacao_encerramento_obrigatoria'=>1,
            'observacao_reabertura_obrigatoria'=>1,
            'nome_etapa_atual'=>'Etapa 1',
            'cor_primaria'=>'#082A55',
            'cor_secundaria'=>'#176FDD',
            'estilo_decoracao_cabecalho'=>'semicirculo',
            'inteligencia_territorial_ativa'=>0,
            'brasao_path'=>null,
        ];
    }

    public function current(): array
    {
        $mid=(int)(Tenant::id()??0);
        return $mid?$this->forMunicipio($mid):$this->defaults();
    }

    public function forMunicipio(int $mid,bool $fresh=false): array
    {
        if($mid<=0)return $this->defaults();
        if(!$fresh&&isset(self::$cache[$mid]))return self::$cache[$mid];
        $data=$this->defaults();
        try{
            $st=$this->pdo->prepare('SELECT inteligencia_territorial_ativa,brasao_path FROM municipios WHERE id=? LIMIT 1');
            $st->execute([$mid]);$m=$st->fetch(PDO::FETCH_ASSOC)?:[];
            if($m){$data['inteligencia_territorial_ativa']=(int)($m['inteligencia_territorial_ativa']??0);$data['brasao_path']=$m['brasao_path']??null;}
        }catch(\Throwable){}
        if($this->tableReady()){
            $st=$this->pdo->prepare('SELECT * FROM parametros_instancia WHERE municipio_id=? LIMIT 1');$st->execute([$mid]);$row=$st->fetch(PDO::FETCH_ASSOC)?:[];
            if($row)$data=array_merge($data,$row);
        }
        $data['prazo_alerta_dias']=max(1,(int)$data['prazo_alerta_dias']);
        $data['tamanho_maximo_arquivo_mb']=max(1,(int)$data['tamanho_maximo_arquivo_mb']);
        foreach(['notificacoes_ativas','observacao_envio_obrigatoria','observacao_aprovacao_obrigatoria','observacao_encerramento_obrigatoria','observacao_reabertura_obrigatoria','inteligencia_territorial_ativa'] as $key)$data[$key]=(int)!empty($data[$key]);
        $data['estilo_decoracao_cabecalho']=in_array((string)($data['estilo_decoracao_cabecalho']??'semicirculo'),['semicirculo','nenhuma'],true)?(string)$data['estilo_decoracao_cabecalho']:'semicirculo';
        return self::$cache[$mid]=$data;
    }

    public function saveForCurrent(array $data): array
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode alterar os Parâmetros da Instância.');
        $mid=(int)(Tenant::id()??0);if(!$mid)throw new RuntimeException('Município não resolvido.');
        return $this->save($mid,$data);
    }

    public function save(int $mid,array $data): array
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode alterar os Parâmetros da Instância.');
        if(!$this->tableReady())throw new RuntimeException('A estrutura de Parâmetros da Instância ainda não foi instalada. Execute bin\\upgrade_v4_20_7.php.');
        $before=$this->forMunicipio($mid,true);
        $alert=max(1,min(30,(int)($data['prazo_alerta_dias']??3)));
        $maxMb=max(1,min(100,(int)($data['tamanho_maximo_arquivo_mb']??20)));
        $extensions=$this->normalizeExtensions((string)($data['extensoes_permitidas']??''));
        if($extensions==='')throw new RuntimeException('Informe ao menos uma extensão global permitida.');
        $stage=trim((string)($data['nome_etapa_atual']??''));if($stage==='')$stage='Etapa 1';if(function_exists('mb_substr'))$stage=mb_substr($stage,0,120,'UTF-8');else$stage=substr($stage,0,120);
        $primary=$this->color((string)($data['cor_primaria']??'#082A55'),'#082A55');
        $secondary=$this->color((string)($data['cor_secundaria']??'#176FDD'),'#176FDD');
        $headerDecoration=in_array((string)($data['estilo_decoracao_cabecalho']??'semicirculo'),['semicirculo','nenhuma'],true)?(string)$data['estilo_decoracao_cabecalho']:'semicirculo';
        $flags=[
            'notificacoes_ativas'=>isset($data['notificacoes_ativas'])?1:0,
            'observacao_envio_obrigatoria'=>isset($data['observacao_envio_obrigatoria'])?1:0,
            'observacao_aprovacao_obrigatoria'=>isset($data['observacao_aprovacao_obrigatoria'])?1:0,
            'observacao_encerramento_obrigatoria'=>isset($data['observacao_encerramento_obrigatoria'])?1:0,
            'observacao_reabertura_obrigatoria'=>isset($data['observacao_reabertura_obrigatoria'])?1:0,
        ];
        $this->pdo->prepare('INSERT INTO parametros_instancia(municipio_id,prazo_alerta_dias,tamanho_maximo_arquivo_mb,extensoes_permitidas,notificacoes_ativas,observacao_envio_obrigatoria,observacao_aprovacao_obrigatoria,observacao_encerramento_obrigatoria,observacao_reabertura_obrigatoria,nome_etapa_atual,cor_primaria,cor_secundaria,estilo_decoracao_cabecalho,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE prazo_alerta_dias=VALUES(prazo_alerta_dias),tamanho_maximo_arquivo_mb=VALUES(tamanho_maximo_arquivo_mb),extensoes_permitidas=VALUES(extensoes_permitidas),notificacoes_ativas=VALUES(notificacoes_ativas),observacao_envio_obrigatoria=VALUES(observacao_envio_obrigatoria),observacao_aprovacao_obrigatoria=VALUES(observacao_aprovacao_obrigatoria),observacao_encerramento_obrigatoria=VALUES(observacao_encerramento_obrigatoria),observacao_reabertura_obrigatoria=VALUES(observacao_reabertura_obrigatoria),nome_etapa_atual=VALUES(nome_etapa_atual),cor_primaria=VALUES(cor_primaria),cor_secundaria=VALUES(cor_secundaria),estilo_decoracao_cabecalho=VALUES(estilo_decoracao_cabecalho),atualizado_em=NOW()')
            ->execute([$mid,$alert,$maxMb,$extensions,$flags['notificacoes_ativas'],$flags['observacao_envio_obrigatoria'],$flags['observacao_aprovacao_obrigatoria'],$flags['observacao_encerramento_obrigatoria'],$flags['observacao_reabertura_obrigatoria'],$stage,$primary,$secondary,$headerDecoration]);
        unset(self::$cache[$mid]);

        $territorial=isset($data['inteligencia_territorial_ativa']);
        if((int)$before['inteligencia_territorial_ativa']!==($territorial?1:0)) (new MunicipioService())->setTerritorialModule($mid,$territorial);

        $this->saveIdentity($mid,$data,$before);
        unset(self::$cache[$mid]);$after=$this->forMunicipio($mid,true);
        Audit::log('PARAMETROS_INSTANCIA_ATUALIZADOS','Parâmetros da instância municipal atualizados',$mid,[
            'categoria'=>'ADMINISTRACAO','severidade'=>'ATENCAO',
            'antes'=>$this->auditShape($before),'depois'=>$this->auditShape($after),
        ]);
        return $after;
    }

    public function deadlineAlertDays(int $mid): int { return max(1,(int)$this->forMunicipio($mid)['prazo_alerta_dias']); }
    public function maxUploadBytes(int $mid): int { return max(1,(int)$this->forMunicipio($mid)['tamanho_maximo_arquivo_mb'])*1024*1024; }
    public function notificationsEnabled(int $mid): bool { return !empty($this->forMunicipio($mid)['notificacoes_ativas']); }
    public function stageName(int $mid): string { return trim((string)$this->forMunicipio($mid)['nome_etapa_atual'])?:'Etapa 1'; }
    public function observationRequired(int $mid,string $action): bool
    {
        $p=$this->forMunicipio($mid);$key=match($action){'envio'=>'observacao_envio_obrigatoria','aprovacao'=>'observacao_aprovacao_obrigatoria','encerramento'=>'observacao_encerramento_obrigatoria','reabertura'=>'observacao_reabertura_obrigatoria',default=>''};
        return $key!==''&&!empty($p[$key]);
    }
    public function allowedExtensions(int $mid,array $specific=[]): array
    {
        $global=$this->extensionArray((string)$this->forMunicipio($mid)['extensoes_permitidas']);
        $specific=array_values(array_unique(array_filter(array_map(fn($x)=>ltrim(strtolower(trim((string)$x)),'.'),$specific))));
        return $specific?array_values(array_intersect($specific,$global)):$global;
    }

    private function saveIdentity(int $mid,array $data,array $before): void
    {
        if(!empty($data['remover_brasao'])){
            $old=(string)($before['brasao_path']??'');$this->pdo->prepare('UPDATE municipios SET brasao_path=NULL,atualizado_em=NOW() WHERE id=?')->execute([$mid]);if($old!=='')(new FileStorage())->discard($old);
        }
        if(isset($_FILES['brasao'])&&($_FILES['brasao']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            $up=(new FileStorage())->storeUpload('brasao','municipios/'.$mid,'brasao-'.$mid,['png','jpg','jpeg','webp'],2*1024*1024);
            $old=(string)($before['brasao_path']??'');$this->pdo->prepare('UPDATE municipios SET brasao_path=?,atualizado_em=NOW() WHERE id=?')->execute([$up['path'],$mid]);if($old!==''&&$old!==$up['path'])(new FileStorage())->discard($old);
        }
    }

    private function normalizeExtensions(string $value): string { return implode(',',$this->extensionArray($value)); }
    private function extensionArray(string $value): array
    {
        $items=preg_split('/[,;\s]+/',strtolower($value),-1,PREG_SPLIT_NO_EMPTY)?:[];
        $items=array_values(array_unique(array_filter(array_map(fn($x)=>preg_replace('/[^a-z0-9]+/','',ltrim(trim((string)$x),'.')),$items))));
        sort($items);return $items;
    }
    private function color(string $value,string $fallback): string { $v=strtoupper(trim($value));return preg_match('/^#[0-9A-F]{6}$/',$v)?$v:$fallback; }
    private function auditShape(array $p): array
    {
        return array_intersect_key($p,array_flip(['prazo_alerta_dias','tamanho_maximo_arquivo_mb','extensoes_permitidas','notificacoes_ativas','observacao_envio_obrigatoria','observacao_aprovacao_obrigatoria','observacao_encerramento_obrigatoria','observacao_reabertura_obrigatoria','nome_etapa_atual','cor_primaria','cor_secundaria','estilo_decoracao_cabecalho','inteligencia_territorial_ativa','brasao_path']));
    }
    private function tableReady(): bool
    {
        static $ready=null;if($ready!==null)return$ready;
        try{$this->pdo->query('SELECT 1 FROM parametros_instancia LIMIT 1');return$ready=true;}catch(\Throwable){return$ready=false;}
    }
}
