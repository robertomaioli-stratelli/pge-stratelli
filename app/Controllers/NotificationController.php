<?php
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\NotificationService;

final class NotificationController
{
    public function index(): void
    {
        $service=new NotificationService();
        $data=$service->listing([
            'page'=>(int)($_GET['pagina']??1),
            'status'=>(string)($_GET['status']??''),
            'tipo'=>(string)($_GET['tipo']??''),
            'q'=>(string)($_GET['q']??''),
        ]);
        View::render('notifications/index',$data+['title'=>'Notificações','erro'=>Session::pullFlash('erro'),'ok'=>Session::pullFlash('ok')]);
    }

    public function open(string $id): void
    {
        $link=(new NotificationService())->open((int)$id);Response::redirect($link);
    }

    public function markRead(string $id): void
    {
        $this->csrf();(new NotificationService())->markRead((int)$id);Response::redirect($this->back());
    }

    public function markAllRead(): void
    {
        $this->csrf();(new NotificationService())->markAllRead();Session::flash('ok','Todas as notificações foram marcadas como lidas.');Response::redirect($this->back());
    }

    private function csrf(): void{if(!Csrf::validate($_POST['_token']??null))Response::abort(419,'Sessão expirada.');}
    private function back(): string{$r=(string)($_POST['voltar']??'/notificacoes');return str_starts_with($r,'/')&&!str_starts_with($r,'//')?$r:'/notificacoes';}
}
