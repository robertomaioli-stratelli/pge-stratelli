# INPACTA v4.20 — Recuperação de senha por e-mail

A recuperação de senha passa a operar por SMTP real. O token continua sendo aleatório, armazenado apenas por SHA-256, de uso único e com validade configurável (30 minutos por padrão).

## Configuração do `.env`

```env
APP_URL=https://seu-dominio-da-plataforma.com.br
PASSWORD_RESET_SHOW_LINK_LOCAL=false
PASSWORD_RESET_MINUTES=30

MAIL_ENABLED=true
MAIL_TRANSPORT=smtp
MAIL_HOST=smtp.seu-provedor.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=no-reply@seudominio.com.br
MAIL_PASSWORD=sua-senha-ou-app-password
MAIL_FROM=no-reply@seudominio.com.br
MAIL_FROM_NAME=INPACTA By Stratelli
MAIL_TIMEOUT=20
MAIL_VERIFY_PEER=true
```

`MAIL_ENCRYPTION` aceita `tls` (STARTTLS), `ssl` (TLS implícito) ou `none`. Em produção mantenha `MAIL_VERIFY_PEER=true`.

## Teste do SMTP

```bat
cd C:\xampp\htdocs\stratelli\plataforma_etapa1
C:\xampp\php\php.exe bin\test_mail.php seu-email@dominio.com.br
```

O comando deve retornar `[OK]` e a mensagem deve chegar ao destinatário.

## Fluxo

1. Usuário informa o e-mail em `/esqueci-senha`.
2. A plataforma sempre responde de forma genérica para evitar enumeração de contas.
3. Se a conta estiver ativa, um token é emitido e enviado por SMTP.
4. O usuário recebe um botão `Redefinir minha senha`.
5. O token expira no tempo configurado e é invalidado após uso.
6. Se o SMTP falhar, o token emitido é imediatamente invalidado e a falha aparece na Auditoria e Segurança.

## Proteções adicionais

- no máximo 5 solicitações por IP em 15 minutos;
- no máximo 3 solicitações por usuário em 15 minutos;
- nenhum token puro é persistido no banco;
- redefinição invalida sessões antigas pelo `auth_version`;
- administradores Stratelli deixam de visualizar/copiar o link de recuperação: o botão na Gestão de Usuários envia o e-mail diretamente.

## XAMPP

O SMTP funciona no XAMPP sem configurar o `sendmail.ini`, porque a v4.20 conecta diretamente ao servidor SMTP informado no `.env`.

Se `APP_URL=http://stratelli.local`, o e-mail será enviado, mas o link só funcionará em computadores que resolvam `stratelli.local`. Para uso real externo, configure `APP_URL` com o domínio HTTPS de produção.
