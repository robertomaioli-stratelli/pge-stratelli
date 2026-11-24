<?php
namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthRateLimit;
use App\Services\PasswordResetService;
use App\Services\Turnstile;
use App\Services\UserService;

final class AuthController
{
    private const LOGIN_ERROR = 'E-mail ou senha inválidos, ou acesso temporariamente bloqueado por segurança.';
    private const RECOVERY_MESSAGE = 'Se o e-mail estiver vinculado a uma conta ativa, enviaremos as instruções de recuperação. Verifique também a caixa de spam.';

    public function loginForm(): void
    {
        View::render('auth/login', [
            'erro' => Session::pullFlash('erro'),
            'ok' => Session::pullFlash('ok'),
            'turnstileRequired' => Turnstile::enabled() && Auth::requiresLoginChallenge(Audit::ip()),
            'turnstileSiteKey' => Turnstile::siteKey(),
            'turnstileAction' => 'login',
        ], 'guest');
    }

    public function login(): void
    {
        $this->checkCsrf('/login');
        $ip = Audit::ip();
        if (!AuthRateLimit::allow('login_ip', $ip, 15, 60)) {
            $this->logSecurityLimit('LOGIN_LIMITADO', 'Limite de envios do formulário por IP atingido.');
            Session::flash('erro', self::LOGIN_ERROR);
            Response::redirect('/login');
        }

        if (Turnstile::enabled() && Auth::requiresLoginChallenge($ip)) {
            $this->checkChallenge('login', '/login');
        }

        $email = $this->postString('email');
        $password = $this->postString('senha');
        if (!Auth::attempt($email, $password)) {
            Session::flash('erro', self::LOGIN_ERROR);
            Response::redirect('/login');
        }
        Response::redirect(Router::homeForUser());
    }

    public function changePasswordForm(): void{View::render('auth/change_password',['title'=>'Alterar senha','erro'=>Session::pullFlash('erro'),'ok'=>Session::pullFlash('ok')]);}
    public function changePassword(): void{if(!Csrf::validate($_POST['_token']??null)){Session::flash('erro','Sessão expirada. Tente novamente.');Response::redirect('/conta/senha');}try{(new UserService())->changeOwnPassword((int)Auth::id(),$_POST);Session::flash('ok','Senha alterada com sucesso.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}Response::redirect('/conta/senha');}

    public function forgotForm(): void
    {
        View::render('auth/forgot_password', [
            'erro' => Session::pullFlash('erro'),
            'ok' => Session::pullFlash('ok'),
            'reset_link' => Session::pullFlash('reset_link'),
            'turnstileRequired' => Turnstile::enabled(),
            'turnstileSiteKey' => Turnstile::siteKey(),
            'turnstileAction' => 'recuperar_senha',
        ], 'guest');
    }

    public function forgot(): void
    {
        $this->checkCsrf('/esqueci-senha');
        $ip = Audit::ip();
        if (!AuthRateLimit::allow('recuperacao_ip', $ip, 5, 900)) {
            $this->logSecurityLimit('RECUPERACAO_SENHA_LIMITADA', 'Limite de solicitações por IP atingido.');
            $this->finishRecovery();
        }
        if (Turnstile::enabled()) {
            $this->checkChallenge('recuperar_senha', '/esqueci-senha');
        }

        $email = strtolower(trim($this->postString('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->finishRecovery();
        }
        if (!AuthRateLimit::allow('recuperacao_email', $email, 3, 900)) {
            $this->logSecurityLimit('RECUPERACAO_SENHA_LIMITADA', 'Limite de solicitações por e-mail atingido.');
            $this->finishRecovery();
        }

        try {
            $link = (new PasswordResetService())->request($email);
            if ($link !== null) {
                Session::flash('reset_link', $link);
            }
        } catch (\Throwable) {
            // A falha de envio é registrada pelo serviço. A resposta pública permanece a mesma.
        }
        $this->finishRecovery();
    }

    public function resetForm(string $token): void{if(!(new PasswordResetService())->validate($token)){View::render('auth/reset_password',['title'=>'Redefinir senha','token'=>$token,'erro'=>'Link de recuperação inválido ou expirado.'],'guest');return;}View::render('auth/reset_password',['title'=>'Redefinir senha','token'=>$token,'erro'=>Session::pullFlash('erro')],'guest');}
    public function reset(string $token): void{if(!Csrf::validate($_POST['_token']??null)){Session::flash('erro','Sessão expirada. Tente novamente.');Response::redirect('/redefinir-senha/'.$token);}try{(new PasswordResetService())->reset($token,(string)($_POST['nova_senha']??''),(string)($_POST['confirmacao_senha']??''));Session::flash('ok','Senha redefinida. Faça login com a nova credencial.');Response::redirect('/login');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());Response::redirect('/redefinir-senha/'.$token);}}
    public function logout(): void{if(Csrf::validate($_POST['_token']??null))Auth::logout();Response::redirect('/login');}

    private function postString(string $name): string
    {
        $value = $_POST[$name] ?? null;
        return is_string($value) ? $value : '';
    }

    private function checkCsrf(string $path): void
    {
        if (!Csrf::validate($this->postString('_token'))) {
            Session::flash('erro', 'Sessão expirada. Tente novamente.');
            Response::redirect($path);
        }
    }

    private function checkChallenge(string $action, string $path): void
    {
        $result = Turnstile::verify($_POST['cf-turnstile-response'] ?? null, $action);
        if ($result === 'valido') {
            return;
        }

        $unavailable = $result === 'indisponivel';
        $this->logSecurityLimit(
            $unavailable ? 'CAPTCHA_INDISPONIVEL' : 'CAPTCHA_FALHA',
            'Verificação de segurança recusada no formulário ' . $action . '.',
            $action
        );
        Session::flash('erro', $unavailable
            ? 'A verificação de segurança está temporariamente indisponível. Tente novamente em instantes.'
            : 'Conclua a verificação de segurança e envie o formulário novamente.');
        Response::redirect($path);
    }

    private function logSecurityLimit(string $event, string $detail, string $action = ''): void
    {
        // Registra a primeira ocorrência do minuto para conter repetições na auditoria.
        if (AuthRateLimit::allow('auditoria_' . $event . '_' . $action, Audit::ip(), 1, 60)) {
            Audit::logActor(null, $event, $detail, null, [
                'categoria' => 'SEGURANCA',
                'severidade' => 'ALERTA',
                'sucesso' => false,
            ]);
        }
    }

    private function finishRecovery(): never
    {
        Session::flash('ok', self::RECOVERY_MESSAGE);
        Response::redirect('/esqueci-senha');
    }
}
