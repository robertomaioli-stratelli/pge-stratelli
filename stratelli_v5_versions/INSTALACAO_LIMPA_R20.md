# INPACTA r.20 — Instalação Limpa

Esta edição de implantação cria o banco completo da r.20 sem dados de demonstração e cria somente um Administrador da Stratelli.

## Resultado
- 0 municípios
- 0 secretarias
- 0 departamentos
- 0 fases
- 0 documentos/modelos
- 0 usuários municipais
- 0 backups/pontos de restauração
- 0 arquivamentos
- 1 Administrador da plataforma Stratelli

## Instalação pelo navegador (recomendada no XAMPP)
1. Crie um banco MySQL/MariaDB vazio e configure `.env`.
2. Copie a pasta para o XAMPP com outro nome, por exemplo `stratelli_r20_limpa`.
3. Aponte o navegador para `/instalacao_limpa.php`.
4. Informe nome, e-mail e senha do Administrador Stratelli.
5. O instalador aborta se o banco já tiver qualquer tabela.
6. Após concluir, remova/bloqueie `public/instalacao_limpa.php`.

## Instalação CLI
```bat
C:\xampp\php\php.exe bin\install_clean.php --name="Administrador Stratelli" --email=admin@stratelli.com.br --password="SUA_SENHA_FORTE"
```

O instalador CLI também aborta se o banco já possuir tabelas.

## Segurança
- Senha armazenada com Argon2id quando disponível, ou `PASSWORD_DEFAULT` como fallback.
- Não há senhas padrão gravadas no código.
- O instalador web usa token de sessão.
- Após concluir, é criado `storage/.installation.lock`.
- O instalador web não pode ser executado novamente depois do lock.
- Nenhuma tabela existente é apagada.
