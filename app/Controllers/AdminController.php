<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\MacroDashboardService;
use App\Services\PendingService;
use App\Services\MunicipioService;
use App\Services\UserService;
use App\Services\PasswordResetService;
use App\Services\SecurityAuditService;
use App\Services\HistoricalIndicatorsService;
use App\Core\Format;

final class AdminController
{
    public function dashboard(): void
    {
        $data=(new MacroDashboardService())->load();
        $data['pendingDesk']=(new PendingService())->macro($data);
        View::render('admin/dashboard',$data+['title'=>'Visão Macro']);
    }

    public function pendencias(): void
    {
        $data=(new PendingService())->macro();
        View::render('admin/pendencias',$data+['title'=>'Central de Pendências']);
    }

    public function municipios(): void
    {
        $pdo=Database::connection();
        $municipios=$pdo->query('SELECT m.*, (SELECT COUNT(*) FROM usuarios u WHERE u.municipio_id=m.id AND u.ativo=1) usuarios_ativos,
            (SELECT COUNT(*) FROM usuarios u WHERE u.municipio_id=m.id AND u.grupo="GESTOR" AND u.ativo=1) gestores_ativos
            FROM municipios m ORDER BY m.nome')->fetchAll();
        View::render('admin/municipios',['municipios'=>$municipios,'title'=>'Municípios','erro'=>Session::pullFlash('erro'),'ok'=>Session::pullFlash('ok')]);
    }

    public function createMunicipio(): void
    {
        if(!Csrf::validate($_POST['_token']??null)) Response::abort(419,'Sessão expirada.');
        try {
            (new MunicipioService())->createWithManager($_POST);
            Session::flash('ok','Município criado com gestor inicial.');
        } catch(\Throwable $e){ Session::flash('erro',$e->getMessage()); }
        Response::redirect('/admin/municipios');
    }

    public function municipioDetalhes(string $id): void
    {
        $pdo=Database::connection();$mid=(int)$id;
        $st=$pdo->prepare('SELECT m.*,
            (SELECT COUNT(*) FROM usuarios u WHERE u.municipio_id=m.id AND u.ativo=1) usuarios_ativos,
            (SELECT COUNT(*) FROM usuarios u WHERE u.municipio_id=m.id AND u.grupo="GESTOR" AND u.ativo=1) gestores_ativos,
            (SELECT COUNT(*) FROM secretarias s WHERE s.municipio_id=m.id AND s.ativo=1) secretarias_ativas,
            (SELECT COUNT(*) FROM fases f WHERE f.municipio_id=m.id AND f.ativo=1) fases_ativas
            FROM municipios m WHERE m.id=? LIMIT 1');
        $st->execute([$mid]);$municipio=$st->fetch();if(!$municipio)Response::abort(404,'Município não encontrado.');
        View::render('admin/municipio',['municipio'=>$municipio,'title'=>'Cadastro do Município','erro'=>Session::pullFlash('erro'),'ok'=>Session::pullFlash('ok')]);
    }

    public function updateMunicipio(string $id): void
    {
        if(!Csrf::validate($_POST['_token']??null)) Response::abort(419,'Sessão expirada.');
        try{(new MunicipioService())->updateDetails((int)$id,$_POST);Session::flash('ok','Dados do município atualizados.');}
        catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/admin/municipios/'.(int)$id);
    }

    public function toggleTerritorialModule(string $id): void
    {
        if(!Csrf::validate($_POST['_token']??null)) Response::abort(419,'Sessão expirada.');
        try{
            $enabled=((string)($_POST['ativo']??'0'))==='1';
            (new MunicipioService())->setTerritorialModule((int)$id,$enabled);
            Session::flash('ok',$enabled?'Inteligência Territorial ativada para os usuários do município.':'Inteligência Territorial desativada para os usuários do município.');
        }catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        $voltar=(string)($_POST['voltar']??'');
        if($voltar==='/admin/municipios')Response::redirect('/admin/municipios');
        Response::redirect('/admin/municipios/'.(int)$id);
    }

    public function usuarios(): void
    {
        $pdo=Database::connection();
        $usuarios=$pdo->query('SELECT u.*,m.nome municipio_nome,m.uf municipio_uf,s.nome secretaria_nome,s.sigla secretaria_sigla,d.nome departamento_nome,d.sigla departamento_sigla FROM usuarios u LEFT JOIN municipios m ON m.id=u.municipio_id LEFT JOIN secretarias s ON s.id=u.secretaria_id AND s.municipio_id=u.municipio_id LEFT JOIN departamentos d ON d.id=u.departamento_id AND d.municipio_id=u.municipio_id ORDER BY u.administrador_plataforma DESC,m.nome,u.nome')->fetchAll();
        $municipios=$pdo->query('SELECT id,nome,uf FROM municipios WHERE ativo=1 ORDER BY nome')->fetchAll();
        $secretarias=$pdo->query('SELECT id,municipio_id,nome,sigla FROM secretarias WHERE ativo=1 ORDER BY nome')->fetchAll();
        $departamentos=$pdo->query('SELECT id,municipio_id,secretaria_id,nome,sigla FROM departamentos WHERE ativo=1 ORDER BY nome')->fetchAll();
        View::render('admin/usuarios',['usuarios'=>$usuarios,'municipios'=>$municipios,'secretarias'=>$secretarias,'departamentos'=>$departamentos,'title'=>'Usuários','erro'=>Session::pullFlash('erro'),'ok'=>Session::pullFlash('ok')]);
    }

    public function createUsuario(): void
    {
        if(!Csrf::validate($_POST['_token']??null)) Response::abort(419,'Sessão expirada.');
        try {
            $platform = isset($_POST['administrador_plataforma']);
            $municipioId=$platform?null:(int)($_POST['municipio_id']??0);
            (new UserService())->create($_POST,$municipioId,$platform);
            Session::flash('ok','Usuário criado com sucesso.');
        } catch(\Throwable $e){ Session::flash('erro',$e->getMessage()); }
        Response::redirect('/admin/usuarios');
    }


    public function auditoria(): void
    {
        $data=(new SecurityAuditService())->load($_GET);View::render('admin/auditoria',$data+['title'=>'Auditoria e Segurança']);
    }
    public function exportAuditoria(): void{(new SecurityAuditService())->export($_GET);}
    public function indicadoresHistoricos(): void
    {
        $data=(new HistoricalIndicatorsService())->macro($_GET);
        View::render('admin/indicadores_historicos',$data+['title'=>'Indicadores Executivos Históricos']);
    }
    public function exportIndicadoresHistoricos(): void{(new HistoricalIndicatorsService())->exportMacro($_GET);}
    public function editUsuario(string $id): void
    {
        $pdo=Database::connection();$service=new UserService();try{$usuario=$service->findForAdmin((int)$id);}catch(\Throwable$e){Response::abort(404,$e->getMessage());}
        $municipios=$pdo->query('SELECT id,nome,uf FROM municipios WHERE ativo=1 ORDER BY nome')->fetchAll();$secretarias=$pdo->query('SELECT id,municipio_id,nome,sigla FROM secretarias WHERE ativo=1 ORDER BY nome')->fetchAll();$departamentos=$pdo->query('SELECT id,municipio_id,secretaria_id,nome,sigla FROM departamentos WHERE ativo=1 ORDER BY nome')->fetchAll();
        View::render('admin/usuario',['usuario'=>$usuario,'municipios'=>$municipios,'secretarias'=>$secretarias,'departamentos'=>$departamentos,'title'=>'Editar usuário','erro'=>Session::pullFlash('erro'),'ok'=>Session::pullFlash('ok')]);
    }
    public function updateUsuario(string $id): void
    {
        if(!Csrf::validate($_POST['_token']??null))Response::abort(419,'Sessão expirada.');try{(new UserService())->updateByAdmin((int)$id,$_POST);Session::flash('ok','Usuário e permissões atualizados.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}Response::redirect('/admin/usuarios/'.(int)$id);
    }
    public function updateUsuarioSenha(string $id): void
    {
        if(!Csrf::validate($_POST['_token']??null))Response::abort(419,'Sessão expirada.');
        try{(new UserService())->setPasswordByAdmin((int)$id,$_POST);Session::flash('ok','Nova senha definida com sucesso. As sessões anteriores do usuário foram invalidadas.');}
        catch(\Throwable$e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/admin/usuarios/'.(int)$id);
    }

    public function toggleUsuario(string $id): void
    {
        if(!Csrf::validate($_POST['_token']??null))Response::abort(419,'Sessão expirada.');try{(new UserService())->setActive((int)$id,((string)($_POST['ativo']??'0'))==='1');Session::flash('ok','Status do usuário atualizado.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}Response::redirect('/admin/usuarios');
    }
    public function recoveryUsuario(string $id): void
    {
        if(!Csrf::validate($_POST['_token']??null))Response::abort(419,'Sessão expirada.');try{(new PasswordResetService())->adminSend((int)$id);Session::flash('ok','E-mail de recuperação enviado ao usuário.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}Response::redirect('/admin/usuarios');
    }

    public function configuracoes(): void
    {
        $pdo=Database::connection();
        $municipios=$pdo->query('SELECT id,nome,uf,slug,status,ativo,brasao_path,inteligencia_territorial_ativa FROM municipios ORDER BY ativo DESC,nome')->fetchAll();
        $selectedId=(int)($_GET['municipio']??0);
        if(!$selectedId && $municipios)$selectedId=(int)$municipios[0]['id'];
        $selected=null;
        foreach($municipios as $m){if((int)$m['id']===$selectedId){$selected=$m;break;}}
        if(!$selected && $municipios){$selected=$municipios[0];$selectedId=(int)$selected['id'];}
        $stats=['fases'=>0,'secretarias'=>0,'departamentos'=>0,'tipos'=>0,'documentos'=>0,'modelos'=>0,'camadas'=>0,'objetos_territoriais'=>0];
        if($selected){
            $queries=[
                'fases'=>'SELECT COUNT(*) FROM fases WHERE municipio_id=?',
                'secretarias'=>'SELECT COUNT(*) FROM secretarias WHERE municipio_id=?',
                'departamentos'=>'SELECT COUNT(*) FROM departamentos WHERE municipio_id=?',
                'tipos'=>'SELECT COUNT(*) FROM tipos_documento WHERE municipio_id=?',
                'documentos'=>'SELECT COUNT(*) FROM requisitos_documentais WHERE municipio_id=?',
                'modelos'=>'SELECT COUNT(DISTINCT requisito_id) FROM modelos_documentos WHERE municipio_id=? AND ativo=1',
                'camadas'=>'SELECT COUNT(*) FROM camadas_territoriais WHERE municipio_id=? AND ativo=1',
                'objetos_territoriais'=>'SELECT COUNT(*) FROM objetos_territoriais WHERE municipio_id=? AND ativo=1',
            ];
            foreach($queries as $key=>$sql){$st=$pdo->prepare($sql);$st->execute([$selectedId]);$stats[$key]=(int)$st->fetchColumn();}
        }
        View::render('admin/configuracoes',[
            'municipios'=>$municipios,
            'selected'=>$selected,
            'stats'=>$stats,
            'title'=>'Configurações',
            'erro'=>Session::pullFlash('erro'),
            'ok'=>Session::pullFlash('ok'),
        ]);
    }
}
