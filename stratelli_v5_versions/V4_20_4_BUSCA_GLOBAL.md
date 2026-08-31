# INPACTA v4.20.4 — Busca Global da Plataforma

## Objetivo
Adicionar uma pesquisa integrada no topo da plataforma para localizar rapidamente registros distribuídos entre diferentes módulos.

## Escopo pesquisado
- Municípios
- Usuários (visível na busca global da Stratelli)
- Secretarias
- Documentos configurados
- Fases do Workflow
- Arquivos enviados e modelos documentais
- Objetos da Inteligência Territorial

## Comportamento
- Botão de busca no cabeçalho com atalho `Ctrl + K`.
- Sugestões instantâneas a partir de 2 caracteres.
- Resultados agrupados por área.
- Página `/busca` com visão consolidada dos resultados.
- A busca da Stratelli alcança todas as instâncias municipais.
- Usuários municipais recebem somente resultados pertencentes ao seu município e ao escopo autorizado do perfil.
- Usuários de Secretaria recebem apenas documentos e fases vinculados à própria unidade/departamento.
- A Inteligência Territorial só entra na busca municipal quando o módulo estiver habilitado; a Stratelli continua podendo pesquisar seus objetos.
- Fases ainda bloqueadas podem aparecer como contexto, mas o resultado não contorna o controle sequencial do Workflow.
- Objetos territoriais encontrados pela busca podem ser abertos diretamente no mapa, com a ficha lateral do objeto em foco.

## Proteções e desempenho
- Pesquisa mínima de 2 caracteres.
- Debounce de 250 ms na busca instantânea.
- Resultados instantâneos limitados por categoria.
- Consultas preparadas via PDO.
- Nenhuma permissão é ampliada pela Busca Global.

## Arquivos adicionados
- `app/Controllers/SearchController.php`
- `app/Services/GlobalSearchService.php`
- `app/Views/search/index.php`

## Arquivos alterados
- `routes.php`
- `app/Views/layouts/app.php`
- `app/Views/tenant/territorio.php`
- `public/assets/production.css`

## Banco de dados
Não há alteração estrutural no banco de dados nesta versão. Não é necessário executar script de upgrade.

## Após instalar
Substitua os arquivos do patch e force a atualização dos arquivos estáticos no navegador com `Ctrl + F5`.
