<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Response;use App\Core\Router;use App\Core\Session;use App\Services\SsoTokenService;
final class SsoController
{
    /** Porta de entrada do SSO Stratelli: valida e consome o token (uso único) e abre a sessão local do usuário. */
    public function entrar(): void
    {
        $token=(string)($_GET['token']??'');
        try{
            $email=(new SsoTokenService())->consume($token);
        }catch(\Throwable $e){
            Session::flash('erro','Acesso via Stratelli recusado: '.$e->getMessage());
            Response::redirect('/login');
        }
        if(!Auth::loginBySso($email)){
            Session::flash('erro','O e-mail '.$email.' não possui conta ativa nesta plataforma. Solicite o cadastro ao administrador.');
            Response::redirect('/login');
        }
        Response::redirect(Router::homeForUser());
    }

    /** Pré-checagem da origem (server-to-server): o e-mail do token tem conta ativa? Valida a assinatura mas NÃO consome o token. */
    public function existe(): void
    {
        $token=(string)($_GET['token']??'');
        try{
            $email=(new SsoTokenService())->peek($token);
        }catch(\Throwable $e){
            Response::json(['ok'=>false,'erro'=>$e->getMessage()],400);
        }
        Response::json(['ok'=>true,'existe'=>Auth::emailTemContaAtiva($email)]);
    }
}
