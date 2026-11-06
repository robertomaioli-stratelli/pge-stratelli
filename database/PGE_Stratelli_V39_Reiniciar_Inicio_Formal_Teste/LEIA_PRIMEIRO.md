# PGE Stratelli V39 — Reinício do teste de início formal em Maringá

## Objetivo
Desativar, sem exclusão física, o registro **Ofício 123/2026**, data **04/11/2026**, cujo upload foi feito apenas para teste. Remover a data de início legado de Maringá para eliminar falso atraso; preservar demais cadastros e usuários.

## Procedimento
1. Coloque a PGE em manutenção e faça backup completo do banco `pge_db`, inclusive da pasta de uploads.
2. Execute `01_CONFERIR_INICIO_TESTE.sql` e confirme que existe exatamente um `ATIVO` para Maringá: `Ofício 123/2026`, data `2026-11-04`, vinculado a uma etapa `habilitada=0`.
3. Execute `02_DESATIVAR_INICIO_TESTE_MARINGA.sql` no banco `pge_db`. O script ABORTA se os dados divergirem.
4. Confirme `inicios_formais_ativos_maringa=0` e `cronogramas_legados_maringa=0`. Teste Workflow, Dashboard, Visão Macro e Gantt. Deve aparecer "Aguardando início formal" e sem atraso, até novo upload da modalidade ativa.
5. O SQL **não apaga o arquivo PDF físico**. Para removê-lo do servidor, localize o caminho retornado por `01_CONFERIR_INICIO_TESTE.sql` na pasta de uploads. Faça cópia de segurança e remova o arquivo separadamente, respeitando a política de auditoria e retenção da Stratelli. Nunca apague outros PDFs.

## Observações
- O registro permanece na tabela com `status='RETIFICADO'` e pode ser auditado.
- Não modifica usuários, Etapas, Fases, Atos, Atividades, documentos operacionais ou catálogos de prazo.
- Os resultados automáticos `CALCULADO` são desprogramados para Maringá; regras permanecem cadastradas.
- Alterações no esquema não são necessárias.
- Se a PGE continuar exibindo o início após a atualização, confira se o código da V35+ (`FormalStartService.php`) e as conexões de banco no servidor estão atualizados. Não faça novas exclusões às cegas.
