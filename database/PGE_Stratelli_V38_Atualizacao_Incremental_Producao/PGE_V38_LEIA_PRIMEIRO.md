# PGE Stratelli — Atualização incremental de PRODUÇÃO (V38)

## Origem e política de preservação

- Comparados os dumps `pge_db_PRODUCAO.sql` (servidor MariaDB 10.11) e `stratelli_LOCAL.sql` (XAMPP MariaDB 10.4), enviados em 06/11/2026.
- Esta atualização **não substitui o banco nem recria as tabelas**. Usa o `pge_db` existente.
- **Não altera a tabela `usuarios`**, seus hashes de senha, perfis ou permissões. Os IDs locais divergentes são remapeados nos registros novos de início formal e de histórico documental.
- Mantém os dados existentes de produção, incluindo auditoria, notificações, documentos enviados, alterações posteriores de cadastros municipais e seus brasões mais recentes.
- Acrescenta os campos `pge_etapas_execucao.habilitada`, `fases.ativo_individual` e `pge_inicios_formais.etapa_execucao_id`; substitui índice único antigo pela chave de modalidade.
- Importa a nova **ETAPA 5 - CONTRATAÇÃO - EMERGENCIAL DIRETA** (3 fases, 3 atos, 8 atividades); coloca a antiga Etapa 5 Diálogo Competitivo inativa, preservando a hierarquia.
- Importa ainda os registros novos do ambiente local: um requisito documental, um modelo, seu histórico e o início formal do Diálogo Competitivo (não autoriza o início da modalidade Emergencial).

## Procedimento

1. **Coloque a PGE em manutenção**, impedindo acessos/escritas durante a operação.
2. Faça **backup integral** do banco `pge_db` no phpMyAdmin e da pasta de documentos (`storage/uploads` ou o diretório configurado). Confirme que o backup baixado está íntegro.
3. Execute `PGE_V38_01_CONFERENCIA_PREVIA.sql`, sem alterar dados. No dump recebido havia **8 usuários, 3 municípios, 28 fases, 75 atos, 22 atividades e 4 etapas**. Se os dados atuais divergirem, **não execute** a migração.
4. Revise o SQL e então importe `PGE_V38_02_ATUALIZAR_PRODUCAO.sql` dentro de `pge_db`.
5. Execute `PGE_V38_03_VALIDACAO.sql`: Etapa 5 Emergencial Direta habilitada, Diálogo Competitivo desabilitado, 3/3/8 registros na primeira, 8 usuários preservados e início formal preso à modalidade antiga.
6. Suba via GitHub os arquivos PHP correspondentes à versão atual do projeto (inclusive o mecanismo V35/V36 de habilitação por número).
7. Faça login com administrador e gestor de Maringá e confira Configurações, Workflow, Gantt e documentos.

## Avisos críticos

- **Não executar o dump local diretamente sobre `pge_db`**. Não executar scripts de limpeza, V35 ou V35.1 sobre esta migração.
- O SQL foi validado comparando os dois arquivos, mas **não foi executado contra uma instância MariaDB nesta sessão**. `ALTER TABLE` faz commit implícito; `ROLLBACK` não reverte alterações estruturais. Se houver erro, não repita cegamente: solicite revisão com o log completo ou restaure o backup.
- Os arquivos referenciados abaixo **não estão dentro do SQL**: `marcos_formais/1/inicio-formal_20261104_163925_836f5945ea_0729GatewayCertificaPDF.aspx.pdf` e `modelos/1/modelo-grupo-30_20261104_164548_4c5faeb46d_pg_0001.pdf`. Copie-os do ambiente local para a pasta correta na hospedagem, preservando caminhos e permissões. Se não forem copiados, o download desses registros falhará.
- O SQL **não migra alertas/notificações locais de prazos antigos**, pois são artefatos temporários de outro momento e poderiam produzir avisos desatualizados; preserva os alertas existentes do servidor.
- Modificações feitas no banco publicado **depois do dump enviado** não estão contempladas pelas conferências. Se houve novas ações, exporte novo dump antes de aplicar.
- A modalidade Emergencial Direta exige seu próprio documento formal antes de iniciar a contagem dos prazos.
