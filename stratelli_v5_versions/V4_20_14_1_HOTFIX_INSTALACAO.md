# INPACTA v4.20.14.1 — Hotfix de instalação automática

## Motivo
A v4.20.14 dependia da execução manual de `bin/upgrade_v4_20_14.php` via PHP-CLI.
Em algumas instalações XAMPP, o `php.exe` de linha de comando pode estar indisponível ou incompatível com o Windows, mesmo com o Apache/PHP web funcionando.

## Solução
A instalação da funcionalidade passa a ser **automática pelo próprio aplicativo**.

Ao acessar o Workflow, ou qualquer operação cadastral que precise verificar o estado de arquivamento, o INPACTA:

1. verifica/cria a tabela `arquivamentos_etapa` com `CREATE TABLE IF NOT EXISTS`;
2. cria `storage/archives` se necessário;
3. segue normalmente para a operação solicitada.

A operação é idempotente: se a tabela e o diretório já existirem, nada é recriado nem apagado.

## Instalação
Não é mais necessário executar migration manual.

Basta substituir os arquivos da v4.20.14.1 na instalação existente e acessar:

`/{municipio}/workflow`

Depois, como Stratelli, o sistema poderá exibir/usar o controle de encerramento da Etapa 1.

## Segurança
A tabela continua com chave estrangeira para `municipios` e `usuarios`, índice/unicidade por município + etapa e armazenamento fora da pasta pública.

O script `bin/upgrade_v4_20_14.php` permanece no pacote para instalações que possuam PHP-CLI funcional, mas deixa de ser obrigatório.
