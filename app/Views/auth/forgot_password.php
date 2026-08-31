<?php use App\Core\Csrf;use App\Core\Format; ?>
<div class="login-shell recovery-shell">
    <section class="login-brand-panel">
        <div class="login-mark">IN</div>
        <h1>INPACTA By Stratelli</h1>
        <p>Sistema de Governança de Proteção Municipal</p>
        <div class="login-security">Recuperação protegida por link individual, temporário e de uso único.</div>
    </section>
    <section class="login-card recovery-card">
        <div class="recovery-icon">✉</div>
        <h2>Recuperar senha</h2>
        <p>Informe o e-mail cadastrado. Se existir uma conta ativa, enviaremos um link seguro para redefinição da senha.</p>
        <?php if(!empty($erro)):?><div class="flash error"><?=Format::h($erro)?></div><?php endif;?>
        <?php if(!empty($ok)):?><div class="flash ok"><?=Format::h($ok)?></div><?php endif;?>
        <?php if(!empty($reset_link)):?><div class="security-reset-local"><b>Ambiente local</b><span>Link temporário gerado para teste:</span><code><?=Format::h($reset_link)?></code></div><?php endif;?>
        <form method="post" action="/esqueci-senha" class="login-form recovery-form">
            <input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>">
            <label>E-mail<input type="email" name="email" required autocomplete="email" placeholder="nome@dominio.com.br"></label>
            <button class="btn primary" type="submit">Enviar link de recuperação</button>
        </form>
        <div class="recovery-security-note"><b>Segurança</b><span>O link expira automaticamente e só pode ser utilizado uma vez. A plataforma nunca informa se um e-mail possui ou não cadastro.</span></div>
        <div class="login-help"><a href="/login">← Voltar ao login</a></div>
    </section>
</div>
