<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;
use App\Core\View;
use App\Services\OperationalEmailService;

final class OperationalEmailController
{
    public function index(string $municipio): void
    {
        if(!Auth::isPlatformAdmin())Response::abort(403,'Somente administradores Stratelli podem enviar cobrancas.');
        $escopo=strtoupper(trim((string)($_GET['escopo']??'ETAPA')));
        $escopoId=(int)($_GET['item']??0);
        $svc=new OperationalEmailService();
        View::render('tenant/notificar',[ 'tenant'=>Tenant::current(),'title'=>'Notificar / Cobrar',
            'itens'=>$svc->pendencias((int)Tenant::id(),$escopo,$escopoId),
            'escopo'=>$escopo,'escopoId'=>$escopoId,
            'ok'=>Session::pullFlash('ok'),'erro'=>Session::pullFlash('erro')]);
    }
    public function send(string $municipio): void
    {
        if(!Auth::isPlatformAdmin())Response::abort(403,'Acesso negado.');
        if(!Csrf::validate($_POST['_token']??null))Response::abort(419,'Sessao expirada.');
        try {
            if(($_POST['confirmacao']??'')!=='SIM')throw new \RuntimeException('Confirme explicitamente o envio.');
            $r=(new OperationalEmailService())->cobrar((int)Tenant::id(),(array)($_POST['requisito_ids']??[]),
                (string)($_POST['filtro']??'TODOS'),(string)($_POST['mensagem']??''),(int)(Auth::id()??0),
                (string)($_POST['escopo']??'ETAPA'),(int)($_POST['item']??0));
            Session::flash('ok',"Envios: {$r['enviados']}. Repetidos: {$r['repetidos']}. Falhas: {$r['falhas']}. Sem destinatario: {$r['sem_destinatario']}.");
        }catch(\Throwable $e){ Session::flash('erro',$e->getMessage()); }
        $escopo=(string)($_POST['escopo']??'ETAPA');$item=(int)($_POST['item']??0);
        Response::redirect('/'.Tenant::current()['slug'].'/notificar?'.http_build_query(['escopo'=>$escopo,'item'=>$item]));
    }
}
