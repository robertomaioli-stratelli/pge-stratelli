# INPACTA v4.20.10 — Acesso contextual ao cadastro do município

## Objetivo
Transformar o indicador do município no cabeçalho em um acesso direto aos dados cadastrais da instância, respeitando o perfil autenticado.

## Comportamento
- Administrador Stratelli: o bloco do município abre `/admin/municipios/{id}`, mantendo todas as opções de edição já existentes.
- Gestor e Usuário municipal: o bloco abre `/{municipio}/municipio`, uma ficha institucional exclusivamente para consulta.
- A rota somente leitura permanece protegida pelo tenant, portanto um usuário municipal não consegue consultar cadastro de outra instância.

## Ficha somente leitura
Exibe:
- brasão, município, UF e status;
- código IBGE, população, área territorial, site oficial e slug;
- etapa atual;
- quantidade de gestores, usuários, Secretarias, departamentos e fases;
- identidade visual cadastrada;
- latitude/longitude;
- situação do GeoJSON e da Inteligência Territorial;
- mapa do perímetro quando houver GeoJSON;
- data da última atualização cadastral.

Não há formulário, botão de edição ou alteração para perfis municipais.

## Arquivos
- `routes.php`
- `app/Controllers/TenantController.php`
- `app/Views/layouts/app.php`
- `app/Views/tenant/municipio.php` (novo)
- `public/assets/municipality-profile.css` (novo)

## Banco de dados
Não há alteração de banco de dados. Não execute upgrade.
