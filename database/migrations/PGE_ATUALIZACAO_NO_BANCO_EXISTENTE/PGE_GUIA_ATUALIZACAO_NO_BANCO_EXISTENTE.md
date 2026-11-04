# PGE Stratelli — Atualização no banco publicado existente

O banco selecionado no phpMyAdmin deve ser **pge_db**. Nenhum outro banco será criado.

## O que a atualização faz

- Substitui as 28 tabelas do dump publicado pelas 54 tabelas consolidadas.
- Carrega os dados operacionais do dump local e preserva as três contas publicadas do export, com IDs, senhas (hashes), perfis e permissões. Incorpora mais cinco contas locais que não existiam no servidor, totalizando oito.
- Confere *antes de apagar* que o servidor contém exatamente as 28 tabelas esperadas e as três contas publicadas, com hashes e IDs iguais aos do export enviado. Se divergirem, o script deve interromper antes da substituição.
- Não cria banco e não executa mudanças nos arquivos PHP.

## IMPORTANTE — riscos

Essa operação é uma reconstrução **dentro do próprio banco**. `DROP TABLE` e `CREATE TABLE` são DDL **não reversíveis por ROLLBACK**. Uma falha durante a importação pode deixar o banco incompleto. O script não deve ser executado se o backup externo não tiver sido feito e conferido.

Dados novos ou modificados no servidor **após o dump publicado** (além dos usuários protegidos pela checagem) poderão ser substituídos pelos do dump local. **Não executar** enquanto a plataforma receber novos cadastros.

O SQL preserva os usuários tal como no **dump publicado enviado**, não garante novas contas ou senhas alteradas depois daquele export. Se houver alterações, forneça novo dump do servidor para reconciliação.

## Instalação pelo phpMyAdmin

1. Coloque a PGE em manutenção e impeça acessos de escrita ao banco.
2. No banco **pge_db**, faça uma exportação completa imediatamente antes da migração: estrutura + dados + triggers + rotinas. Guarde fora do servidor.
3. Faça também uma cópia de `storage/uploads` e outros arquivos físicos da PGE. Confirme a possibilidade de restaurar o SQL e os arquivos.
4. Importe `PGE_CONFERIR_ANTES_DE_ATUALIZAR.sql` em **pge_db**. O resultado final deve ser `APTO`. Caso contrário, **pare**.
5. Importe `PGE_ATUALIZAR_BANCO_PUBLICADO_EXISTENTE.sql` no **pge_db** existente, somente após confirmar o backup. Não recarregue a página durante a execução.
6. Verifique o resultado ao final: 54 tabelas, 8 usuários e 3 municípios. Execute `PGE_VERIFICACAO_APOS_IMPORTACAO.sql` do pacote anterior para verificar as referências.
7. Confirme a versão PHP publicada, o login dos usuários preexistentes, a hierarquia, os documentos, o Workflow, o Gantt e os relatórios antes de reabrir o sistema.

## Se houver falha

**Não tente executar novamente esse SQL por cima do banco parcialmente importado.** Mantenha o acesso em manutenção, restaure o backup completo original e investigue o primeiro erro exibido pelo phpMyAdmin.

## Restrições de acesso

O usuário MariaDB precisa conseguir `DROP`, `CREATE`, `INSERT`, `ALTER`, `INDEX`, `REFERENCES` e `TRIGGER` no banco atual. O script não exige `CREATE DATABASE` nem `CREATE ROUTINE`.

**Nota:** a conferência e a migração foram preparadas a partir dos dumps e verificadas estaticamente. Não foram executadas na sua hospedagem.
