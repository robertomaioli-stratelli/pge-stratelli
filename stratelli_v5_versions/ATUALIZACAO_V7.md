# PGE Stratelli V7 — integração operacional (entrega de código para homologação)

## Escopo implementado

- A base completa da aplicação V6 foi mantida; não é só um módulo avulso.
- CRUD administrativo dos **modelos de Processo → Etapa → Fase → Ato → Atividade** da V6.
- Nova entidade de processos executados (`pge_processos_execucao`), etapas (`pge_etapas_execucao`) e atividades filhas (`pge_atividades_ato`).
- Registros `atividades_fase` antigos preservados e interpretados como **Atos**. **Não foram renomeados fisicamente**, pois IDs antigos são usados por requisitos, secretarias, conclusão e histórico.
- `fases.etapa_execucao_id` associa fases à etapa; cadastro de fases inclui seleção de etapa.
- `/municipio/hierarquia`: árvore operacional por processo, etapa, fase, ato e atividade; documentos por fase/ato/atividade; status e progresso; criação de processo/etapa/atividade; associação de documento a atividade; processo novo criado a partir de modelo.
- Configuração documental existente passa a permitir associação opcional à Atividade subordinada ao Ato, conservando o `requisito_id` e versões de `documentos_enviados`.
- A conclusão do Ato foi bloqueada enquanto houver Atividades filhas pendentes. Conclusão de Atividade verifica documentos obrigatórios vinculados.
- A apuração de atos/fases nas métricas existentes considera, quando presentes, as atividades filhas e seus documentos.
- Link de navegação hierárquica disponível em páginas municipais da aplicação.
- Backup semanal de **todo o banco**, com script PowerShell e instalador de tarefa Windows (não executados automaticamente), com retenção configurável e checksum SHA-256.

## Aplicação sobre o banco existente (homologação, NÃO produção)

1. Faça cópia completa dos dados, arquivos enviados, `.env` e do diretório atual antes de qualquer modificação. Não substitua o `.env` existente.
2. Execute `database/migrations/v6_01_catalogo_modelos.sql` caso não tenha aplicado o catálogo V6. Caso já tenha aplicado, **não importe novamente sem verificar estado**.
3. Execute `database/migrations/v7_01_operacional.sql` em cópia do banco. Cria tabelas operacionais, adiciona duas colunas e classifica as fases existentes em Etapa legada. Não apaga documentos, atos ou aprovações.
4. Copie os arquivos V7 para a instalação de homologação, mantendo `.env` e os diretórios de uploads reais. Configure `DocumentRoot` para `public/`.
5. Visite `/admin/modelos-processos` e `/{slug}/hierarquia`. Crie um processo **novo** a partir do modelo 'Diálogo competitivo' e confira 6 fases, 24 atos e 11 atividades.
6. Teste: fases e atos preexistentes, requisito com documentos aprovados e versão anterior, nova atividade, requisito associado a nova atividade, conclusão/reabertura e encerramento de fases.
7. Instale backup semanal conforme `scripts/backup/VERIFICACAO_E_RESTAURACAO.md` e teste restauração em banco isolado. Armazene cópias também fora do PC/servidor.

## Limitações e riscos que impedem declarar prontidão para produção

- **Não foi executado teste de integração com MariaDB real nem com Apache/XAMPP neste ambiente.** Verificações realizadas: sintaxe PHP e inspeção estática do código/SQL.
- A instância nova da planilha não cria automaticamente secretarias, requisitos, permissões ou arquivos documentais; estes precisam ser configurados segundo o processo específico.
- Os fluxos originais de dashboard, SLA, cronograma, bloqueio sequencial, notificações e relatórios ainda contêm regras agregadas **por fase** e algumas consultas legadas. Foram preservados para evitar regressão, mas **não foram reimplementados integralmente por processo e etapa**. Assim, não há garantia de comportamento correto com várias etapas/processos concorrentes no mesmo município.
- O catálogo novo e a execução nova convivem com referências e terminologia legadas em alguns módulos; **esta é uma versão funcional ampliada para homologação, não uma migração integral concluída em todas as áreas da plataforma**.
- A rotina de backup exige agendamento efetivo no Windows, confirmação de permissões e teste da restauração; não garante backup sem instalação no ambiente local.
- A rotina SQL MariaDB possui DDL com commit implícito; reversão exige procedimento de backup/restore, não simplesmente `ROLLBACK`.

## Reversão

Restaure o ZIP da aplicação antiga e o backup completo da base de dados. Não execute `DROP` manual das tabelas novas se houver dados operacionais nelas. Restaure também uploads, quando alterados.
