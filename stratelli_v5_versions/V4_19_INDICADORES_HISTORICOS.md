# INPACTA v4.19 — Indicadores Executivos Históricos

A v4.19 acrescenta uma camada histórica aos relatórios da Etapa 1 sem alterar a arquitetura atual de processo único por município.

## Indicadores

- tempo médio das fases formalmente encerradas;
- atraso médio das fases, sem compensar atrasos por encerramentos antecipados;
- ranking das fases com maior atraso;
- Secretarias com maior volume de correções;
- tempo médio entre envio municipal e decisão da Stratelli;
- taxa de aprovação na primeira versão já validada;
- percentual de fases encerradas no prazo;
- situação da Etapa 1 e conclusão no prazo;
- evolução mensal de envios, aprovações, correções e encerramentos.

## Escopo por perfil

- Stratelli dentro do município: visão completa da Etapa 1 e de todas as Secretarias.
- Gestor Municipal: visão municipal dos documentos sob responsabilidade do município.
- Usuário comum: indicadores documentais limitados à sua Secretaria/Departamento e fases às quais sua unidade está vinculada.
- Visão Macro Stratelli: consolidação histórica de todos os municípios.

## Rotas

- `/admin/indicadores-historicos`
- `/{municipio}/relatorios/historicos`

As duas visões possuem filtro de 6, 12 ou 24 meses e exportação CSV.

## Definições

**Tempo de fase:** dias entre o início operacional recalculado e o encerramento formal vigente.

**Atraso:** diferença positiva entre a data limite operacional e o encerramento formal. Encerramentos antecipados não reduzem o atraso médio das demais fases.

**Tempo de validação:** intervalo entre `enviado_em` e `validado_em` de cada versão de documento municipal que recebeu decisão.

**Aprovação na primeira entrega:** primeiras versões (`versao=1`) já validadas que terminaram como `APROVADO`, divididas pelo total de primeiras versões já decididas.

**Conclusão no prazo:** na arquitetura atual, refere-se à Etapa 1 do município. Enquanto a plataforma ainda não trabalhar com múltiplas Etapas/processos, este indicador não deve ser interpretado como carteira de processos independentes dentro do mesmo município.

## Banco de dados

Não há alteração de schema na v4.19. Os indicadores são calculados a partir das tabelas já auditadas:

- `cronograma_processos`;
- `cronograma_fases`;
- `historico_fases`;
- `documentos_enviados`;
- `historico_documentos`;
- `requisitos_documentais`;
- `secretarias`.

## Instalação

Substitua os arquivos do patch e faça `Ctrl + F5`. Não execute migration.
