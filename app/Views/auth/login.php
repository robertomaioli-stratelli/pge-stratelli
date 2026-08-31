<?php use App\Core\Csrf; ?>
<div class="login-shell">
    <section class="login-brand-panel"><div class="login-mark">IN</div><h1>INPACTA By Stratelli</h1><p>Sistema de Governança de Proteção Municipal</p><div class="login-security">Ambiente autenticado e segregado por município.</div></section>
    <section class="login-card"><h2>Acessar plataforma</h2><p>Entre com suas credenciais para continuar.</p>
        <?php if(!empty($erro)):?><div class="flash error"><?=htmlspecialchars($erro)?></div><?php endif;?><?php if(!empty($ok)):?><div class="flash ok"><?=htmlspecialchars($ok)?></div><?php endif;?>
        <form method="post" action="/login" class="login-form"><input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>">
            <label>E-mail<input type="email" name="email" autocomplete="username" required></label>
            <label>Senha<input type="password" name="senha" autocomplete="current-password" required></label>
            <button class="btn primary" type="submit">Entrar</button>
        </form><div class="login-help"><a href="/esqueci-senha">Esqueci minha senha</a></div>
    </section>
</div>
