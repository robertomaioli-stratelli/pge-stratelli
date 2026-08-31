# INPACTA v4.20.2 — Destaque visual da fase em visualização

## Objetivo
Melhorar a leitura da prévia do cronograma no Workflow e no Dashboard Situacional.

## O que foi ajustado
- A fase que está sendo visualizada no momento agora recebe destaque visual próprio.
- O destaque funciona mesmo quando a fase visualizada não é a fase atual do processo.
- No Workflow, a marcação acompanha a fase aberta na tela.
- No Dashboard Situacional, a marcação acompanha a fase em foco exibida ao usuário.
- Foi adicionada uma etiqueta visual "EM VISUALIZAÇÃO" no card destacado.

## Arquivos alterados
- `app/Views/tenant/partials/process_preview.php`
- `public/assets/production.css`

## Observação
Não há alteração de banco de dados. Basta substituir os arquivos e limpar o cache do navegador com `Ctrl + F5`.
