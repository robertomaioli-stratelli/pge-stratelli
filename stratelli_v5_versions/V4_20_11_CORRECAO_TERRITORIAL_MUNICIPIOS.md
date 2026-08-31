# INPACTA v4.20.11 — Correção Territorial na Gestão de Municípios

## Objetivo
Corrigir dois comportamentos identificados na Gestão de Municípios:

1. a delimitação GeoJSON cadastrada não era evidenciada no card do município;
2. o botão **Ativar Territorial** apontava para uma rota diferente da rota registrada, resultando em erro 404.

## Correções

### Delimitação territorial na carteira
Cada card de município agora exibe uma área própria **Delimitação territorial** com:

- status `CADASTRADA` ou `PENDENTE`;
- informação de disponibilidade do GeoJSON;
- link **Visualizar perímetro** quando houver delimitação;
- link **Cadastrar delimitação** quando ainda não houver GeoJSON.

O link de visualização abre diretamente o cadastro administrativo na seção do mapa geoprocessado.

### Ativar/Desativar Inteligência Territorial
O formulário da carteira agora utiliza a rota correta:

`/admin/municipios/{id}/modulos/territorio`

Também foi mantida uma rota de compatibilidade para o endereço usado na v4.20.10:

`/admin/municipios/{id}/inteligencia-territorial`

Assim, páginas antigas em cache não retornam 404.

Após ativar ou desativar pela carteira, o sistema retorna para `/admin/municipios`.

## Arquivos alterados

- `routes.php`
- `app/Controllers/AdminController.php`
- `app/Views/admin/municipios.php`
- `app/Views/admin/municipio.php`

## Banco de dados
Não há alteração no banco de dados.

## Instalação
Substitua os arquivos do patch e execute `Ctrl + F5` no navegador.
