<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;
use App\Services\DeadlineAutomationService;

final class DeadlineController
{
    public function save(string $municipio): void
    {
        if (!Auth::isPlatformAdmin()) Response::abort(403,'Somente Stratelli.');
        if (!Csrf::validate((string)($_POST['_token']??''))) Response::abort(419,'Token CSRF invalido.');
        try {
            (new DeadlineAutomationService())->save(
                (int)Tenant::id(),
                strtoupper((string)($_POST['nivel']??'')),
                (int)($_POST['item_id']??0),
                (string)($_POST['marco_tipo']??''),
                (int)($_POST['marco_id']??0)?:null,
                (string)($_POST['data_fixa']??''),
                (int)($_POST['quantidade']??0),
                (string)($_POST['unidade']??''),
                (string)($_POST['direcao']??''),
                Auth::id()
            );
            Session::flash('ok','Regra de prazo salva e cronograma recalculado.');
        } catch (\Throwable $e) { Session::flash('erro',$e->getMessage()); }
        Response::redirect('/'.Tenant::current()['slug'].'/workflow#gantt');
    }
}
