# INPACTA v4.20.7 — Central de Parâmetros da Instância

## Objetivo
Centralizar regras operacionais que antes estavam fixas ou distribuídas pelo código, permitindo que a Stratelli configure cada município individualmente.

## Nova área
Acesse:

`Configurações > Parâmetros da Instância`

A área é exclusiva para Administradores Stratelli dentro da instância municipal.

## Parâmetros disponíveis
- nome da Etapa atual;
- prazo de alerta de vencimento, em dias;
- tamanho máximo por arquivo documental;
- extensões globais permitidas;
- notificações internas ativas/inativas;
- Inteligência Territorial ativa/inativa para usuários municipais;
- exigência de observação no envio/reenvio;
- exigência de observação na aprovação documental;
- exigência de observação no encerramento formal;
- exigência de justificativa na reabertura;
- cor primária da instância;
- cor secundária da instância;
- brasão municipal.

O motivo de uma solicitação de correção continua sempre obrigatório para preservar a rastreabilidade documental.

## Regras efetivamente integradas
### Prazo de alerta
O Workflow e a visão Macro passam a usar a quantidade de dias configurada na instância para classificar uma fase como `ATENÇÃO AO PRAZO`.

### Arquivos
O limite máximo configurado passa a valer para documentos e modelos. O servidor PHP ainda precisa aceitar um limite igual ou superior em `upload_max_filesize` e `post_max_size`.

A extensão precisa ser aceita simultaneamente:
1. pelos Parâmetros da Instância; e
2. pelo Tipo de Documento.

### Notificações
Quando desativadas, novos eventos daquela instância deixam de gerar notificações internas. Registros já existentes são preservados.

### Inteligência Territorial
A ativação/desativação continua utilizando a regra existente do município. Por padrão permanece inativa até liberação da Stratelli.

### Observações
As exigências configuradas são validadas no backend. Correções documentais continuam exigindo motivo independentemente do parâmetro.

### Etapa atual
O nome configurado passa a ser utilizado também nos Indicadores Executivos Históricos da instância.

### Identidade
As cores configuradas são aplicadas à identidade visual da instância e o brasão pode ser substituído ou removido diretamente nesta Central.

## Banco de dados
Esta versão cria a tabela `parametros_instancia`.

Antes de atualizar, faça backup do banco e da pasta `storage/uploads`.

No CMD:

```bat
cd C:\xampp\htdocs\stratelli\plataforma_etapa1
C:\xampp\php\php.exe bin\upgrade_v4_20_7.php
C:\xampp\php\php.exe bin\check.php
```

O upgrade inicializa automaticamente os municípios existentes com valores padrão compatíveis com o comportamento atual.

## Valores padrão
- alerta: 3 dias;
- limite documental: 20 MB;
- notificações: ativas;
- Inteligência Territorial: preserva o valor atual do município;
- observação de envio: opcional;
- observação de aprovação: opcional;
- observação de encerramento: obrigatória;
- motivo de reabertura: obrigatório;
- etapa: `Etapa 1`;
- cores: azul institucional INPACTA.
