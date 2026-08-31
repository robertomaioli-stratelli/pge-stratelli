<?php use App\Core\Auth; use App\Core\Csrf; use App\Core\Format; $u=Auth::user(); ?>
<div class="module-page">
    <div class="module-hero">
        <div>
            <h1>Alterar senha</h1>
            <p>Atualize sua senha de acesso com segurança. Esta alteração vale imediatamente para o usuário logado.</p>
        </div>
        <span class="module-context">🔒 Segurança da conta</span>
    </div>
    <div class="module-kpis-grid">
        <div class="module-kpi"><small>USUÁRIO</small><b><?=Format::h($u['nome']??'—')?></b><span><?=Format::h($u['email']??'—')?></span></div>
        <div class="module-kpi neutral"><small>PERFIL</small><b><?=Format::h(!empty($u['administrador_plataforma'])?'STRATELLI':($u['grupo']??'—'))?></b><span><?=Format::h(($u['municipio_nome']??'Sem município').(!empty($u['municipio_uf'])?' - '.$u['municipio_uf']:''))?></span></div>
    </div>
    <section class="card password-card">
        <form method="post" action="/conta/senha" class="password-form">
            <input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>">
            <label>Senha atual<input type="password" name="senha_atual" autocomplete="current-password" required></label>
            <label>Nova senha<input type="password" name="nova_senha" autocomplete="new-password" minlength="10" required></label>
            <label>Confirmar nova senha<input type="password" name="confirmacao_senha" autocomplete="new-password" minlength="10" required></label>
            <div class="password-help">Use pelo menos 10 caracteres. Preferencialmente combine letras maiúsculas, minúsculas, números e símbolos.</div>
            <div class="password-actions">
                <button class="btn primary" type="submit">Salvar nova senha</button>
            </div>
        </form>
    </section>
</div>
