# INPACTA v4.20.3 — Checklist de Implantação por Município

## Objetivo
Dar à Stratelli uma leitura rápida e objetiva da prontidão de cada instância antes da homologação.

## Indicadores utilizados
A prontidão é calculada automaticamente, sem preenchimento manual, usando oito componentes:

1. Cadastro institucional — nome, UF, slug, código IBGE, latitude, longitude e brasão;
2. Gestor cadastrado — ao menos um Gestor ativo;
3. Secretarias configuradas — ao menos uma Secretaria ativa;
4. Fases configuradas — ao menos uma Fase ativa;
5. Documentos configurados — ao menos um requisito documental ativo;
6. Modelos cadastrados — cobertura proporcional de modelos sobre os documentos configurados;
7. GeoJSON cadastrado — delimitação municipal disponível;
8. Usuários criados — ao menos um usuário comum ativo.

A Inteligência Territorial aparece no checklist como informação de configuração, mas não reduz a porcentagem, pois sua ativação é opcional por município.

## Faixas visuais
- 100% — Pronto para homologação;
- 80% a 99% — Implantação avançada;
- 50% a 79% — Configuração parcial;
- abaixo de 50% — Configuração inicial.

## Gestão de Municípios
Cada cliente passa a exibir:
- percentual de prontidão;
- barra de progresso;
- checklist recolhível;
- quantidades reais em cada item;
- cobertura de modelos no formato `8/12`;
- estado da Inteligência Territorial;
- ordenação por maior ou menor prontidão.

A tela também passa a mostrar a `Prontidão média` de toda a carteira.

## Arquivos alterados
- `app/Services/MunicipalityCardService.php`
- `app/Views/admin/municipios.php`

## Banco de dados
Não há alteração de schema nem migração nesta versão.
