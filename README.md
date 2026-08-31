# INPACTA — Produção v2

Arquitetura SaaS multi-tenant em PHP 8.2+ e MySQL/MariaDB, preparada para XAMPP e produção.

## Instalação nova no XAMPP

1. Extraia o projeto, por exemplo em `C:\xampp\htdocs\stratelli\plataforma_etapa1`.
2. Aponte o VirtualHost para `C:/xampp/htdocs/stratelli/plataforma_etapa1/public`.
3. Copie `.env.example` para `.env` e configure o banco.
4. Crie o banco vazio.
5. Execute:

```bat
cd C:\xampp\htdocs\stratelli\plataforma_etapa1
C:\xampp\php\php.exe bin\install.php
C:\xampp\php\php.exe bin\check.php
```

## Atualização de uma instalação v1

Preserve o `.env`, substitua os arquivos e execute:

```bat
C:\xampp\php\php.exe bin\upgrade_v2.php
C:\xampp\php\php.exe bin\check.php
```

Não é necessário apagar o banco ou recriar os usuários.

## URLs

- `/login`
- `/admin`
- `/admin/municipios`
- `/admin/usuarios`
- `/{municipio}/dashboard`
- `/{municipio}/workflow`
- `/{municipio}/workflow/fase/{id}`
- `/{municipio}/documentos`
- `/{municipio}/relatorios`
- `/{municipio}/historico`
- `/{municipio}/configuracoes` (Stratelli)

Nenhuma página funcional expõe `.php`.

Consulte `MIGRACAO_MVP_V2.md` para a lista dos itens migrados.

## Atualização v3.2 — Visão Macro Executiva

A Visão Macro da Stratelli foi ampliada para funcionar como painel executivo da carteira de clientes. A tela `/admin` passa a consolidar indicadores de clientes, processos, prazos, documentos aguardando validação, correções, secretarias pendentes e fases concluídas; cada município possui card operacional com fase atual, saúde, progresso, documentos, próxima ação da Stratelli e última movimentação. Também foram adicionados filtros rápidos, busca por município e atividades recentes de toda a plataforma.

Esta atualização não altera o schema do banco. Para aplicar sobre a v3.1, basta substituir os arquivos da aplicação e manter o `.env` atual.

## Atualização v3.3 — Identidade e território municipal

A v3.3 adiciona ao cadastro de municípios:

- brasão institucional;
- código IBGE;
- população;
- área territorial;
- site oficial;
- coordenadas centrais opcionais;
- delimitação municipal em GeoJSON;
- mapa interativo com perímetro territorial;
- brasão na barra superior da instância e na Visão Macro.

Para atualizar uma instalação v3.2 existente, copie os arquivos da v3.3 preservando o `.env` e execute:

```bat
C:\xampp\php\php.exe bin\upgrade_v3_3.php
```

Depois acesse **Visão Macro > Municípios > Cadastro e território** para complementar cada cliente.

## v3.8 — Perfis municipais e acesso sequencial

- Dashboard com cards responsivos por largura real do conteúdo.
- Usuário comum vinculado a Secretaria e, opcionalmente, Departamento.
- Usuário comum visualiza apenas fases com documentos atribuídos à sua unidade.
- Gestores visualizam as fases municipais, mas só acessam uma etapa quando todas as anteriores estiverem concluídas.
- O bloqueio é aplicado na interface e no servidor, inclusive para URLs e downloads diretos.
- Upgrade incremental: `php bin/upgrade_v3_8.php`.
- Usuário comum de teste de Maringá/SEADM criado pelo upgrade, se ainda não existir.


## v3.9 — Território operacional
- Nova página `/municipio/territorio`.
- Mapa territorial no Dashboard.
- Prévia territorial na Visão Macro Stratelli.
- Identificação territorial nos Relatórios.
- GeoJSON servido por endpoint autenticado e com isolamento municipal.
- Esta etapa reutiliza o GeoJSON já cadastrado e não exige alteração de banco.

## v4.0 — Inteligência Territorial

A versão 4.0 adiciona uma camada territorial multi-tenant ao INPACTA.

### Antes de acessar a v4.0 em uma instalação existente

Execute:

```bat
cd C:\xampp\htdocs\stratelli\plataforma_etapa1
C:\xampp\php\php.exe bin\upgrade_v4_0.php
C:\xampp\php\php.exe bin\check.php
```

O upgrade cria, sem remover dados existentes:

- `camadas_territoriais`;
- `objetos_territoriais`;
- `vinculos_territoriais`;
- índice composto de isolamento multi-tenant em `documentos_enviados`.

### Recursos territoriais iniciais

O Administrador Stratelli pode:

- criar camadas dinâmicas;
- escolher ponto, linha, polígono ou camada mista;
- vincular camada a uma Secretaria;
- cadastrar objetos clicando/desenhando diretamente no mapa;
- importar Feature ou FeatureCollection GeoJSON;
- vincular objeto territorial a uma fase do Workflow;
- filtrar objetos por Secretaria, status e fase;
- editar/desativar camadas e objetos.

Gestores municipais visualizam todo o território da própria instância. Usuários comuns veem apenas os objetos gerais ou vinculados à própria Secretaria e, quando houver vínculo departamental, ao próprio Departamento.

O módulo reutiliza o perímetro municipal já salvo em `municipios.geojson_delimitacao`.


## Segurança local no XAMPP (v4.2)

Consulte `SEGURANCA_XAMPP.md` e `deploy/xampp-vhosts.conf.example`. O acesso suportado é pela URL
canônica configurada em `APP_URL` (no ambiente atual, `http://stratelli.local`) e o `DocumentRoot`
deve apontar somente para `public/`.


## v4.14 — Central de Pendências / Minha Mesa

A versão adiciona `/admin/pendencias` e `/{municipio}/pendencias`, com fila de ações derivada do Workflow, filtros, busca e paginação de 10 itens. Não requer alteração de banco.

## v4.17 — Encerramento formal das fases

- 100% de documentos obrigatórios aprovados passa a significar **fase pronta para encerramento**, e não conclusão automática.
- Somente o encerramento formal registrado pela Stratelli libera a fase seguinte.
- O encerramento registra responsável, data, observação, snapshot documental e SHA-256 do snapshot.
- Fases encerradas bloqueiam alterações documentais e de configuração relacionadas à fase.
- Reaberturas exigem motivo e permanecem no histórico imutável.
- A Central de Pendências passa a destacar fases prontas para encerramento.
- Upgrade incremental: `php bin/upgrade_v4_17.php`.

Consulte `V4_17_ENCERRAMENTO_FORMAL_FASE.md` para as regras completas.


## Correção v4.17.2
O botão **Encerrar fase** da prévia abre agora o formulário de encerramento formal em uma janela modal. O clique isolado não é confundido com o ato de encerramento: a gravação ocorre somente após data, observação e confirmação.

## v4.18 — Auditoria e Segurança

Após atualizar os arquivos, execute `php bin/upgrade_v4_18.php` e `php bin/check.php`. Consulte `V4_18_AUDITORIA_SEGURANCA.md` para as novas opções de sessão, recuperação de senha e auditoria.

## v4.19 — Indicadores Executivos Históricos

A área de Relatórios passa a oferecer histórico executivo por município e a Visão Macro Stratelli ganha consolidação histórica da carteira. Veja `V4_19_INDICADORES_HISTORICOS.md`.

## Instalação comercial limpa — r.20

Para uma implantação nova sem dados de demonstração, consulte `INSTALACAO_LIMPA_R20.md`. O instalador cria toda a estrutura da r.20 e somente o Administrador da Stratelli.
