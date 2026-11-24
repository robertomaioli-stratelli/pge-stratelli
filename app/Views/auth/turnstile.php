<?php use App\Core\Format; ?>
<?php if ($turnstileSiteKey !== ''): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <div class="cf-turnstile" data-sitekey="<?=Format::h($turnstileSiteKey)?>"
         data-action="<?=Format::h($turnstileAction)?>" data-size="flexible"></div>
    <noscript><p>Ative o JavaScript para concluir a verificação de segurança.</p></noscript>
<?php else: ?>
    <p role="alert">A verificação de segurança está temporariamente indisponível.</p>
<?php endif; ?>
