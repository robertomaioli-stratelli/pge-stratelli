# INPACTA v4.20.9 — Identidade visual dinâmica dos cabeçalhos

## Objetivo
Aplicar automaticamente a identidade visual de cada município aos cabeçalhos principais da instância, utilizando as cores já cadastradas nos Parâmetros da Instância e um detalhe decorativo opcional em formato de semicírculo.

## Novidades
- cabeçalhos principais passam a usar a cor primária e a cor secundária do município;
- contraste do texto é calculado automaticamente para cores claras ou escuras;
- novo parâmetro `estilo_decoracao_cabecalho`;
- opções disponíveis: `Semicírculo suave` e `Sem decoração`;
- o semicírculo é gerado automaticamente a partir da cor secundária;
- a prévia da identidade em Configurações foi atualizada e reage às alterações de cor/estilo;
- o cadastro inicial de novo município agora permite informar as duas cores e o estilo decorativo;
- a Visão Macro da Stratelli continua com identidade institucional; a identidade municipal é aplicada apenas quando uma instância está ativa.

## Cabeçalhos abrangidos
- Dashboard Situacional;
- Workflow de Contratação;
- Minha Mesa / Central de Pendências no contexto municipal;
- Documentos;
- Relatórios;
- Histórico;
- Configurações da Instância;
- Inteligência Territorial;
- SLA por Secretaria;
- Indicadores Executivos Históricos;
- Busca Global no contexto da instância.

Cards semânticos de sucesso, atenção, correção e criticidade preservam suas cores próprias.

## Banco de dados
Execute:

```bat
C:\xampp\php\php.exe bin\upgrade_v4_20_9.php
C:\xampp\php\php.exe bin\check.php
```

O upgrade adiciona a coluna `parametros_instancia.estilo_decoracao_cabecalho` com padrão `semicirculo`.

## Arquivos principais
- `app/Services/InstanceParameterService.php`
- `app/Services/MunicipioService.php`
- `app/Views/layouts/app.php`
- `app/Views/tenant/configuracoes.php`
- `app/Views/admin/municipios.php`
- `public/assets/tenant-theme.css`
- `public/assets/instance-parameters.css`
- `database/schema.sql`
- `bin/upgrade_v4_20_9.php`
- `bin/check.php`
