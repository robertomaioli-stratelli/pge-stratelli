# INPACTA v4.15 — Notificações persistentes

A v4.15 transforma o sino da plataforma em uma Central de Notificações persistente por usuário.

## Eventos gerados

- novo documento municipal enviado para validação da Stratelli;
- correção solicitada;
- documento aprovado;
- prazo da fase próximo do vencimento;
- prazo da fase vencido;
- fase concluída;
- usuário criado;
- Inteligência Territorial ativada para o município.

## Regras de acesso

Cada notificação possui um destinatário individual. O usuário somente consulta e marca como lidas as próprias notificações. Administradores Stratelli recebem eventos operacionais da carteira; gestores e usuários municipais recebem somente eventos pertinentes ao seu município e, para documentos, ao escopo permitido de Secretaria/Departamento.

## Atualização

Execute após substituir os arquivos:

```bat
cd C:\xampp\htdocs\stratelli\plataforma_etapa1
C:\xampp\php\php.exe bin\upgrade_v4_15.php
C:\xampp\php\php.exe bin\check.php
```

Não é necessário apagar ou recriar dados existentes.

## Observação sobre prazos

Alertas de prazo são sincronizados quando a situação do Workflow é calculada durante o uso da plataforma. A chave do evento evita notificações duplicadas para o mesmo usuário, fase e prazo.
