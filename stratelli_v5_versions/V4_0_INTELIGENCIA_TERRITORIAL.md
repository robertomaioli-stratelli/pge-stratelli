# INPACTA v4.0 — Inteligência Territorial

## O que entra nesta versão

- catálogo dinâmico de camadas territoriais por município;
- tipos de geometria: ponto, linha, polígono e camada mista;
- cadastro direto no mapa sem depender do QGIS para cada novo objeto;
- importação de GeoJSON com múltiplas `Feature`;
- vínculo opcional entre objeto territorial e Fase do Workflow;
- filtros por camada, Secretaria, status e fase;
- ficha do objeto no mapa com acesso ao Workflow quando a fase estiver liberada ao perfil;
- indicadores territoriais no Dashboard, Relatórios e Visão Macro Stratelli;
- permissões multi-tenant e por perfil;
- auditoria das principais alterações territoriais.

## Atualização de uma instalação existente

1. Faça backup da pasta e do banco.
2. Substitua os arquivos do patch v4.0.
3. No Prompt de Comando:

```bat
cd C:\xampp\htdocs\stratelli\plataforma_etapa1
C:\xampp\php\php.exe bin\upgrade_v4_0.php
C:\xampp\php\php.exe bin\check.php
```

4. Reinicie o Apache e use `Ctrl + F5`.

## Primeiro uso

Entre como Administrador Stratelli e abra:

`/maringa/territorio?modo=configuracao`

Crie uma camada, por exemplo:

- Nome: `Escolas Municipais`
- Categoria: `Educação`
- Geometria: `Ponto`
- Secretaria: Secretaria de Educação

Depois use **Novo objeto territorial → Ponto** e clique no mapa para cadastrar a unidade.

Para linhas e polígonos, clique nos vértices no mapa e depois pressione **Concluir desenho**.

## Status territorial

- ATIVO: azul;
- ATENÇÃO: amarelo;
- CRÍTICO: vermelho;
- CONCLUÍDO: verde;
- INATIVO: cinza.

## Importação GeoJSON

A importação aceita `.geojson` e `.json` com até 12 MB. Cada `Feature` compatível com o tipo da camada gera um objeto territorial. Propriedades existentes no GeoJSON são preservadas em `atributos_json`.


## v4.1 — Localização por endereço

Objetos territoriais do tipo **Ponto** podem ser localizados digitando um endereço e usando **Localizar no mapa**. O backend também tenta geocodificar o endereço ao salvar quando nenhuma geometria foi informada. A implementação usa um provedor configurável, cache local e limitação de chamadas compatível com o Nominatim público.
