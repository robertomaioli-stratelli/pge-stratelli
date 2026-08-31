<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use RuntimeException;

final class SecurityAuditService
{
    private const SORTS = [
        'data' => 'a.criado_em',
        'evento' => 'a.evento',
        'usuario' => 'COALESCE(a.usuario_nome, a.usuario_email, "")',
        'municipio' => 'COALESCE(m.nome, "")',
        'origem' => 'COALESCE(a.ip, "")',
        'rota' => 'COALESCE(a.rota, "")',
        'classificacao' => 'a.categoria',
        'detalhes' => 'COALESCE(a.detalhes, "")',
    ];

    public function load(array $q=[]): array
    {
        if(!Auth::isPlatformAdmin()) {
            throw new RuntimeException('Acesso restrito à Stratelli.');
        }

        $pdo=Database::connection();
        $search=trim((string)($q['busca']??''));
        $category=strtoupper(trim((string)($q['categoria']??'')));
        $severity=strtoupper(trim((string)($q['severidade']??'')));
        $mid=max(0,(int)($q['municipio']??0));
        $page=max(1,(int)($q['pagina']??1));
        $per=10;
        $sort=strtolower(trim((string)($q['ordem']??'data')));
        if(!isset(self::SORTS[$sort])) $sort='data';
        $direction=strtolower(trim((string)($q['direcao']??'desc')))==='asc'?'asc':'desc';

        [$base,$params]=$this->baseQuery($search,$category,$severity,$mid);

        $st=$pdo->prepare('SELECT COUNT(*)'.$base);
        $st->execute($params);
        $total=(int)$st->fetchColumn();
        $pages=max(1,(int)ceil($total/$per));
        $page=min($page,$pages);
        $offset=($page-1)*$per;

        $sortSql=self::SORTS[$sort];
        $directionSql=strtoupper($direction);
        $secondary=$sort==='data'?'a.id '.$directionSql:'a.criado_em DESC, a.id DESC';
        $sql='SELECT a.*,m.nome municipio_nome,m.uf municipio_uf'.$base
            .' ORDER BY '.$sortSql.' '.$directionSql.', '.$secondary
            .' LIMIT '.$per.' OFFSET '.$offset;
        $st=$pdo->prepare($sql);
        $st->execute($params);
        $items=$st->fetchAll();

        $summary=['total'=>$total,'falhas'=>0,'alertas'=>0,'logins'=>0,'downloads'=>0];
        $st=$pdo->prepare(
            'SELECT '
            .'COALESCE(SUM(a.sucesso=0),0) AS falhas, '
            .'COALESCE(SUM(a.severidade="ALERTA"),0) AS alertas, '
            .'COALESCE(SUM(a.evento LIKE "LOGIN%"),0) AS logins, '
            .'COALESCE(SUM(a.evento LIKE "%DOWNLOAD%"),0) AS downloads'
            .$base
        );
        $st->execute($params);
        $s=$st->fetch();
        if($s){
            $summary=[
                'total'=>$total,
                'falhas'=>(int)($s['falhas']??0),
                'alertas'=>(int)($s['alertas']??0),
                'logins'=>(int)($s['logins']??0),
                'downloads'=>(int)($s['downloads']??0),
            ];
        }

        $municipios=$pdo->query('SELECT id,nome,uf FROM municipios ORDER BY nome')->fetchAll();
        return compact(
            'items','total','pages','page','per','search','category','severity','mid',
            'summary','municipios','sort','direction'
        );
    }

    private function baseQuery(string $search,string $category,string $severity,int $mid): array
    {
        $where=['1=1'];
        $params=[];
        if($search!==''){
            $where[]='(a.evento LIKE ? OR a.detalhes LIKE ? OR a.usuario_nome LIKE ? OR a.usuario_email LIKE ? OR a.ip LIKE ? OR a.rota LIKE ?)';
            for($i=0;$i<6;$i++) $params[]='%'.$search.'%';
        }
        if($category!==''){
            $where[]='a.categoria=?';
            $params[]=$category;
        }
        if($severity!==''){
            $where[]='a.severidade=?';
            $params[]=$severity;
        }
        if($mid){
            $where[]='a.municipio_id=?';
            $params[]=$mid;
        }
        $base=' FROM auditoria a LEFT JOIN municipios m ON m.id=a.municipio_id WHERE '.implode(' AND ',$where);
        return [$base,$params];
    }

    public function browser(string $ua): string
    {
        if($ua==='') return '—';
        foreach(['Edg/'=>'Edge','OPR/'=>'Opera','Chrome/'=>'Chrome','Firefox/'=>'Firefox','Safari/'=>'Safari'] as $needle=>$label){
            if(str_contains($ua,$needle)) return $label;
        }
        return substr($ua,0,45);
    }

    public function export(array $q=[]): never
    {
        if(!Auth::isPlatformAdmin()) {
            throw new RuntimeException('Acesso restrito à Stratelli.');
        }
        Audit::log('EXPORTACAO_AUDITORIA','Exportação CSV da Auditoria de Segurança',null,[
            'categoria'=>'ACESSO_DADOS','severidade'=>'ATENCAO'
        ]);

        $pdo=Database::connection();
        $search=trim((string)($q['busca']??''));
        $category=strtoupper(trim((string)($q['categoria']??'')));
        $severity=strtoupper(trim((string)($q['severidade']??'')));
        $mid=max(0,(int)($q['municipio']??0));
        $sort=strtolower(trim((string)($q['ordem']??'data')));
        if(!isset(self::SORTS[$sort])) $sort='data';
        $direction=strtolower(trim((string)($q['direcao']??'desc')))==='asc'?'asc':'desc';
        [$base,$params]=$this->baseQuery($search,$category,$severity,$mid);
        $directionSql=strtoupper($direction);
        $sortSql=self::SORTS[$sort];
        $secondary=$sort==='data'?'a.id '.$directionSql:'a.criado_em DESC, a.id DESC';

        $st=$pdo->prepare(
            'SELECT a.*,m.nome municipio_nome,m.uf municipio_uf'.$base
            .' ORDER BY '.$sortSql.' '.$directionSql.', '.$secondary
        );
        $st->execute($params);

        header('Content-Type:text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="auditoria-seguranca-'.date('Ymd-His').'.csv"');
        echo "\xEF\xBB\xBF";
        $out=fopen('php://output','w');
        fputcsv($out,['Data','Evento','Categoria','Severidade','Sucesso','Usuário','E-mail','Município','IP','Navegador','Método','Rota','Detalhes'],';');
        while($r=$st->fetch()){
            fputcsv($out,[
                $r['criado_em'],$r['evento'],$r['categoria'],$r['severidade'],$r['sucesso']?'SIM':'NÃO',
                $r['usuario_nome'],$r['usuario_email'],trim(($r['municipio_nome']??'').' '.($r['municipio_uf']??'')),
                $r['ip'],$this->browser((string)$r['user_agent']),$r['metodo'],$r['rota'],$r['detalhes']
            ],';');
        }
        fclose($out);
        exit;
    }
}
