# INPACTA v4.20.12 — Importação e Replicação de Estruturas

## Objetivo
Acelerar a implantação de novos municípios permitindo reutilizar estruturas já configuradas e cadastrar Fases, Secretarias e Departamentos em lote por planilha Excel.

## Nova área
`Configurações da Instância > Importar Estrutura`

## Opção 1 — Copiar de outro município
A Stratelli pode selecionar uma instância de origem e escolher quais estruturas copiar:
- Fases;
- Secretarias;
- Departamentos.

Os vínculos entre Fases e Secretarias são replicados quando as referências correspondentes existem no destino.

Não são copiados:
- usuários;
- documentos enviados;
- modelos documentais;
- histórico;
- auditoria;
- encerramentos de fase;
- prazos executados.

## Opção 2 — Planilha Excel
A plataforma disponibiliza para download o arquivo:
`Modelo_Importacao_Estrutura_INPACTA.xlsx`

Abas:
1. `INSTRUCOES`
2. `FASES`
3. `SECRETARIAS`
4. `DEPARTAMENTOS`
5. `VINCULOS_FASE_SECRETARIA`

Os vínculos usam Código da Fase e Sigla da Secretaria. IDs internos do banco não são expostos.

## Validação obrigatória
Nenhum dado é gravado no upload. Primeiro a plataforma gera uma prévia classificando cada registro como:
- Será criado;
- Será atualizado;
- Já existe e será ignorado;
- Requer correção.

São validados, entre outros:
- sequência das Fases;
- código e ordem duplicados;
- prazo inicial/final;
- Secretarias duplicadas;
- referência de Departamento para Secretaria;
- conflitos com registros existentes;
- vínculos Fase–Secretaria.

## Conflitos
Estratégia padrão: `IGNORAR`.

Opcionalmente a Stratelli pode escolher `ATUALIZAR`. Fases formalmente encerradas nunca podem ser atualizadas pela importação.

## Segurança
A importação é transacional. Se ocorrer uma falha durante a confirmação, todas as alterações daquele lote são revertidas.

Cada confirmação gera registro em `importacoes_estrutura` e na Auditoria e Segurança.

Importações que somente criaram novos registros podem ser desfeitas enquanto os registros importados ainda não tiverem sido utilizados. Se houver usuários, documentos, cronograma ou novos vínculos dependentes, o desfazimento é bloqueado.

## Banco de dados
Executar:

```bat
C:\xampp\php\php.exe bin\upgrade_v4_20_12.php
C:\xampp\php\php.exe bin\check.php
```

A atualização cria a tabela `importacoes_estrutura`.

## Arquivos principais
- `app/Services/StructureImportService.php`
- `app/Services/XlsxStructureReader.php`
- `app/Controllers/TenantController.php`
- `app/Views/tenant/configuracoes.php`
- `public/assets/structure-import.css`
- `resources/templates/Modelo_Importacao_Estrutura_INPACTA.xlsx`
- `bin/upgrade_v4_20_12.php`
