<?php
use App\Core\Csrf;
use App\Core\Format;

$invalidToken = !empty($erro) && stripos((string)$erro, 'inválido ou expirado') !== false;
?>
<div class="login-shell reset-password-shell">
    <section class="login-brand-panel reset-password-brand">
        <div class="login-mark">IN</div>
        <h1>INPACTA <span>by Stratelli</span></h1>
        <p>PGE - Plataforma de Governança Executiva</p>

        <div class="reset-brand-message">
            <div class="reset-brand-icon">✓</div>
            <div>
                <b>Redefinição segura de acesso</b>
                <span>Crie uma nova credencial para continuar utilizando a plataforma.</span>
            </div>
        </div>

        <div class="login-security reset-security-badge">
            Link individual, temporário e de uso único.
        </div>
    </section>

    <section class="login-card reset-password-card">
        <div class="reset-password-heading">
            <div class="reset-password-icon">⌁</div>
            <div>
                <small>SEGURANÇA DA CONTA</small>
                <h2>Redefinir senha</h2>
                <p>Informe e confirme sua nova senha para concluir a recuperação de acesso.</p>
            </div>
        </div>

        <?php if(!empty($erro)):?>
            <div class="flash error reset-password-flash"><?=Format::h($erro)?></div>
        <?php endif;?>

        <?php if($invalidToken):?>
            <div class="reset-expired-state">
                <div class="reset-expired-icon">!</div>
                <div>
                    <b>Solicite um novo link de recuperação</b>
                    <p>Por segurança, links expirados ou já utilizados não podem ser reativados.</p>
                </div>
            </div>
            <a class="btn primary reset-main-action" href="/esqueci-senha">Solicitar novo link</a>
        <?php else:?>
            <form method="post" action="/redefinir-senha/<?=Format::h($token)?>" class="reset-password-form" id="reset-password-form">
                <input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>">

                <label class="reset-password-field">
                    <span>Nova senha</span>
                    <div class="reset-password-input-wrap">
                        <input id="nova_senha" type="password" name="nova_senha" minlength="10" required autocomplete="new-password" placeholder="Digite sua nova senha">
                        <button class="reset-password-toggle" type="button" data-toggle-password="nova_senha" aria-label="Mostrar nova senha">Mostrar</button>
                    </div>
                </label>

                <div class="password-strength" aria-live="polite">
                    <div class="password-strength-head">
                        <span>Segurança da senha</span>
                        <b id="password-strength-label">Aguardando senha</b>
                    </div>
                    <div class="password-strength-track"><span id="password-strength-bar"></span></div>
                </div>

                <label class="reset-password-field">
                    <span>Confirmar nova senha</span>
                    <div class="reset-password-input-wrap">
                        <input id="confirmacao_senha" type="password" name="confirmacao_senha" minlength="10" required autocomplete="new-password" placeholder="Digite novamente a nova senha">
                        <button class="reset-password-toggle" type="button" data-toggle-password="confirmacao_senha" aria-label="Mostrar confirmação da senha">Mostrar</button>
                    </div>
                    <small class="password-match-message" id="password-match-message">As duas senhas devem ser idênticas.</small>
                </label>

                <div class="reset-password-rules">
                    <div class="reset-rule-icon">✓</div>
                    <div>
                        <b>Requisitos de segurança</b>
                        <ul>
                            <li id="rule-length">Mínimo de 10 caracteres.</li>
                            <li>Prefira uma combinação difícil de adivinhar e diferente de outras senhas.</li>
                            <li>Depois da alteração, sessões antigas desta conta deixam de ser válidas.</li>
                        </ul>
                    </div>
                </div>

                <button class="btn primary reset-main-action" id="reset-submit" type="submit">Redefinir senha</button>
            </form>
        <?php endif;?>

        <div class="login-help reset-login-help"><a href="/login">← Voltar ao login</a></div>
    </section>
</div>

<?php if(!$invalidToken):?>
<script>
(function(){
    const password = document.getElementById('nova_senha');
    const confirmation = document.getElementById('confirmacao_senha');
    const bar = document.getElementById('password-strength-bar');
    const label = document.getElementById('password-strength-label');
    const matchMessage = document.getElementById('password-match-message');
    const lengthRule = document.getElementById('rule-length');

    document.querySelectorAll('[data-toggle-password]').forEach(function(button){
        button.addEventListener('click', function(){
            const input = document.getElementById(button.getAttribute('data-toggle-password'));
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Ocultar' : 'Mostrar';
            button.setAttribute('aria-label', (show ? 'Ocultar' : 'Mostrar') + ' senha');
        });
    });

    function scorePassword(value){
        if(!value) return 0;
        let score = 0;
        if(value.length >= 10) score++;
        if(value.length >= 14) score++;
        if(/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
        if(/\d/.test(value) || /[^A-Za-z0-9]/.test(value)) score++;
        return Math.min(score, 4);
    }

    function updateStrength(){
        const score = scorePassword(password.value);
        const labels = ['Aguardando senha','Fraca','Razoável','Boa','Forte'];
        const widths = ['0%','25%','50%','75%','100%'];
        bar.style.width = widths[score];
        bar.dataset.level = String(score);
        label.textContent = labels[score];
        lengthRule.classList.toggle('is-ok', password.value.length >= 10);
        updateMatch();
    }

    function updateMatch(){
        if(!confirmation.value){
            matchMessage.textContent = 'As duas senhas devem ser idênticas.';
            matchMessage.className = 'password-match-message';
            confirmation.setCustomValidity('');
            return;
        }
        if(password.value === confirmation.value){
            matchMessage.textContent = '✓ As senhas coincidem.';
            matchMessage.className = 'password-match-message is-ok';
            confirmation.setCustomValidity('');
        }else{
            matchMessage.textContent = 'As senhas não coincidem.';
            matchMessage.className = 'password-match-message is-error';
            confirmation.setCustomValidity('As senhas não coincidem.');
        }
    }

    password.addEventListener('input', updateStrength);
    confirmation.addEventListener('input', updateMatch);
    updateStrength();
})();
</script>
<?php endif;?>
