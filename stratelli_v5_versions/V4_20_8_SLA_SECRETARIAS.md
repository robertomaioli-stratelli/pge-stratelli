# INPACTA v4.20.8 — Indicador Operacional de SLA por Secretaria

## Objetivo
Transformar os dados documentais e de prazo já registrados na plataforma em uma leitura operacional de desempenho por Secretaria.

## Indicadores
- percentual de entregas obrigatórias realizadas dentro do prazo;
- tempo médio entre o início operacional da fase e o primeiro envio;
- quantidade de correções formalmente solicitadas;
- percentual de aprovação na primeira versão;
- entregas avaliadas, no prazo e fora do prazo;
- pendências obrigatórias já vencidas.

## Onde aparece
- Dashboard Situacional: resumo operacional das Secretarias;
- Relatórios: novo card de acesso ao SLA;
- `/{municipio}/relatorios/sla`: página completa com KPIs, ranking, detalhamento e metodologia;
- `/{municipio}/relatorios/sla/exportar`: exportação CSV.

## Regra do SLA
O SLA considera documentos obrigatórios de responsabilidade municipal. A primeira versão enviada até a data limite operacional da fase é classificada como entrega no prazo. Documento obrigatório não enviado depois do vencimento é classificado como fora do prazo. Fases futuras com prazo ainda provisório são excluídas até a consolidação da data.

Correções e aprovação na primeira versão são indicadores separados do SLA para diferenciar pontualidade de qualidade documental.

## Banco de dados
Não há alteração de banco de dados. A funcionalidade utiliza os dados existentes em `requisitos_documentais`, `documentos_enviados`, `historico_documentos`, `fases` e cronograma operacional.

## Arquivos principais
- `routes.php`
- `app/Controllers/TenantController.php`
- `app/Services/OperationalSlaService.php`
- `app/Views/tenant/dashboard.php`
- `app/Views/tenant/relatorios.php`
- `app/Views/tenant/sla_secretarias.php`
- `public/assets/sla.css`
