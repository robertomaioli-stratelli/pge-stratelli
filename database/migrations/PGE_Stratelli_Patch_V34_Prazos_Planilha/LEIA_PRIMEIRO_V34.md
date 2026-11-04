# PGE Stratelli V34 — Catálogo de prazos do Quadro Sinóptico

**Objetivo:** armazenar os textos originais e fundamentos do Quadro Sinóptico de 29/10/2026, aproveitando o motor da V32, sem exigir digitação manual de cada prazo.

## Pré-requisitos
- Backup completo do banco e do storage; ambiente de homologação.
- V32 instalada (`pge_prazo_regras`, `pge_prazo_resultados`).
- Cadastros operacionais V11 dos Atos com códigos `QS11-1.1` etc. ativos. Município Maringá.
- Não reimportar SQLs de limpeza V10/V11.

## Execução
1. Conferir se `@pge_v34_mid` corresponde a Maringá. NÃO executar se não localizado.
2. Importar `V34_CATALOGO_PRAZOS_QUADRO.sql` pelo phpMyAdmin no banco da PGE.
3. Conferir o resultado final: **24 Atos e 11 Atividades**, desde que os códigos ativos coincidam com o banco.
4. Abrir Workflow → Gantt; a V32 recalcula automaticamente regras já determinadas a cada carregamento.
5. Acompanhar o painel de Regras somente para itens que aguardam marco ou precisam de datas próprias.

## Limitações de cálculo (importantes)
- A planilha descreve vários eventos posteriores (abertura de propostas, publicação, sessão, término do diálogo) SEM data. Início formal do processo **não é** a data desses eventos. Não são atribuídos vencimentos fictícios.
- Apenas dois vínculos seguros por conclusão de outro Ato são cadastrados nesta migração: `3.2` depende da publicação associada ao Ato `2.5`; `3.7` depende da emissão do relatório associado ao Ato `3.6`. Confirmar que a conclusão registrada corresponda efetivamente ao marco normativo.
- Textos compostos de `2.7` e `4.6` exigem divisão em subprazos antes de automatização correta.
- Dias úteis na V32 não consideram feriados; validar calendário aplicável e regras legais antes do uso oficial.
- Prazos sem duração numérica não são transformados artificialmente em 0 dias.
- O processo formal é condição geral para calcular; somente inicia a contagem dos itens vinculados diretamente a ele. Os demais aguardam seus marcos.
- O SQL **não altera regras manuais existentes** (`INSERT IGNORE`) e só preenche campos operacionais vazios.
- Banco SQL não inclui alterações de interface; a V32 já gera barras quando as regras têm marcos disponíveis.
