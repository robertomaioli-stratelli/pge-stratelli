# INPACTA v4.20.13.1 — Hotfix do upgrade v4.20.13

## Correção
O script `bin/upgrade_v4_20_13.php` referenciava `app/bootstrap.php`, arquivo que não existe na arquitetura atual do INPACTA.

O hotfix passa a usar o mesmo padrão dos upgrades anteriores:
- carrega `app/Core/Env.php`;
- lê o `.env`;
- registra o autoload das classes `App\\...`;
- conecta pelo `App\\Core\\Database`;
- cria `storage/backups` de forma validada.

## Banco de dados
A migration continua sendo a mesma da v4.20.13. Se a execução anterior falhou no `require`, nenhuma alteração foi aplicada ao banco.

## Execução
```bat
C:\xampp\php\php.exe bin\upgrade_v4_20_13.php
C:\xampp\php\php.exe bin\check.php
```
