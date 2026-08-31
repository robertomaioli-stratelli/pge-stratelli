# INPACTA v4.18 — Auditoria e Segurança

A v4.18 adiciona uma camada transversal de segurança para produção sem alterar o fluxo funcional da Etapa 1.

## Auditoria técnica persistente

A tabela `auditoria` passa a registrar, além do evento e usuário:

- nome e e-mail do usuário no momento do evento;
- município;
- categoria e severidade;
- sucesso/falha;
- IP;
- User-Agent/navegador;
- método HTTP e rota;
- referer;
- hash da sessão;
- contexto JSON para alterações administrativas.

Os registros de auditoria ficam protegidos contra `UPDATE` e `DELETE` por triggers do banco.

A Stratelli possui a página `/admin/auditoria`, com filtros, paginação de 10 em 10 e exportação CSV. A própria exportação também é auditada.

## Login e tentativas

Toda tentativa é registrada em `tentativas_login`. Após 5 falhas nos últimos 15 minutos para o mesmo e-mail ou IP, novas tentativas são temporariamente bloqueadas. A mensagem de login permanece genérica para não revelar se a conta existe.

## Sessões

Novas variáveis do `.env`:

```env
SESSION_IDLE_MINUTES=60
SESSION_ABSOLUTE_MINUTES=480
```

- `SESSION_IDLE_MINUTES`: encerra a sessão após inatividade.
- `SESSION_ABSOLUTE_MINUTES`: limita a duração máxima da sessão mesmo com uso contínuo.

Alteração de senha, permissão, escopo ou status incrementa `usuarios.auth_version`, invalidando sessões antigas daquele usuário.

## Recuperação de senha

Rotas:

- `/esqueci-senha`
- `/redefinir-senha/{token}`

Tokens são aleatórios, armazenados somente como SHA-256, têm validade de 30 minutos e uso único.

No XAMPP pode-se habilitar temporariamente:

```env
PASSWORD_RESET_SHOW_LINK_LOCAL=true
```

Em produção mantenha `false`. A Stratelli também pode gerar um link temporário na Gestão de Usuários. Se o PHP do servidor estiver configurado para envio de e-mail, pode-se ativar:

```env
MAIL_ENABLED=true
MAIL_FROM=no-reply@seudominio.gov.br
```

## Permissões e usuários

A Gestão de Usuários passa a permitir edição de grupo e escopo, ativação/desativação e geração de link de recuperação. Mudanças registram os valores anteriores e posteriores na auditoria e invalidam sessões abertas.

## Histórico documental

Novas movimentações documentais também registram IP e User-Agent no `historico_documentos`, permitindo visualizar a origem do envio/validação no Histórico da instância.

## Atualização

Faça backup do banco antes da migração e execute:

```bat
cd C:\xampp\htdocs\stratelli\plataforma_etapa1
C:\xampp\php\php.exe bin\upgrade_v4_18.php
C:\xampp\php\php.exe bin\check.php
```

A v4.18 não implementa MFA ainda. A estrutura de segurança foi preparada para que o MFA da Stratelli possa ser acrescentado em uma evolução posterior sem refazer o sistema de autenticação.
