# INPACTA v4.20.6 — Busca Global V2

## Objetivo
Refazer integralmente o layout da Busca Global, eliminando o aspecto de formulário simples e criando uma experiência visual consistente com o potencial e o padrão executivo do INPACTA.

## Principais alterações
- novo layout completo da página `/busca`;
- novo painel de pesquisa rápida no topo;
- folha de estilos exclusiva `global-search.css`, isolada do restante da plataforma;
- carregamento dos estilos com versionamento `?v=4206`, reduzindo problemas de cache;
- hero executivo da Busca Global;
- console principal de pesquisa com campo amplo e exemplos rápidos;
- cards de exploração por área quando ainda não existe pesquisa;
- resumo executivo e navegação por categorias quando existem resultados;
- resultados apresentados em cards com contexto, tipo, município e ação de abertura;
- estados de nenhum resultado e erro redesenhados;
- responsividade para desktop, tablet e celular;
- mantidas as regras de permissão e escopo da v4.20.4/v4.20.5.

## Arquivos alterados/adicionados
- `app/Views/layouts/app.php`
- `app/Views/search/index.php`
- `public/assets/global-search.css` (novo)

## Banco de dados
Não há alteração de banco de dados.

## Aplicação
Substitua os arquivos do patch e faça `Ctrl + F5`.
