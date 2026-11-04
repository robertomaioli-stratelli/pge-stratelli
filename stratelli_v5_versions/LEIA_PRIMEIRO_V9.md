# PGE Stratelli V9 — Integração hierárquica expandida

## Referência aprovada
Planilha `Quadro Sinóptico das atividades v 29.10.2026 gouveia(2).xlsx`: 6 fases, 24 atos e 11 atividades explícitas. A planilha representa UMA etapa de um processo, não o processo inteiro.

**Hierarquia:** Processo → Etapa → Fase → Ato → Atividade → Requisitos documentais/arquivos.

## Mudanças no código
- Área operacional **Configurações da Instância → Etapas · Fases · Atos · Atividades**, com cadastro e edição de processo, etapa, fase, ato e atividade, validação de vínculo e isolamento por município.
- Cadastro de modelos reutilizáveis permanece em `/admin/modelos-processos`; instanciação em `/{municipio}/hierarquia`.
- Vinculação dos requisitos documentais a Atos e Atividades via `requisitos_documentais.atividade_id` e `requisitos_documentais.atividade_ato_id`.
- Navegação e visão sumarizada da hierarquia nas áreas de dashboard, workflow, documentos, pendências e relatórios (visão detalhada de todos os processos reservada à administração).
- Regras de conclusão e progressão por atividade, ato e fase preservadas/adaptadas sobre entidades atuais.
- Controle sequencial de fases considera agora processos distintos no Workflow; encerramento formal também respeita as etapas do processo, sem exigir encerramento de fases de outro processo.
- Rotina PowerShell de backup semanal agora inclui `storage/uploads` no ZIP, além do SQL do banco. Não inclui o `.env` nem segredos.
- Índices adicionais para navegação hierárquica no SQL V9.

## Instalação a partir da V8
1. Congele alterações na aplicação durante a atualização. Faça backup completo do banco e da pasta `storage/uploads` em mídia externa; teste a restauração.
2. Crie um **ambiente de homologação** em XAMPP com cópia do banco e dos arquivos de uploads.
3. Confirme que as migrações `v6_01_catalogo_modelos.sql` e `v7_01_operacional.sql` já foram aplicadas. Não as execute novamente sem inspeção do esquema.
4. Execute em homologação `database/migrations/v9_01_integracao.sql` (índices, sem exclusão de dados).
5. Publique os arquivos PHP e a interface do pacote preservando `.env`, configurações de conexão, uploads, logs e diretórios de dados privados.
6. Entre como administrador e acesse **Configurações → Etapas · Fases · Atos · Atividades**. Instancie o modelo homologado em **Gestão Hierárquica**; não recrie atos históricos com o mesmo ID.
7. Faça testes de criação/edição, login, documento vinculado à atividade, envio, aprovação, conclusão e reabertura da fase, dashboard, relatórios e perfis de acesso antes de publicar em produção.
8. O arquivo `database/migrations/v8_01_reinicializacao_logica.sql` é uma operação **separada, excepcional e de alto impacto**: desativa cadastros antigos de TODOS os municípios. Só execute se ainda não tiver sido aplicado e se houver aprovação explícita dessa reinicialização. Não execute repetidas vezes. Os dados históricos permanecem no banco.

## SQLs inclusos
- `database/migrations/v6_01_catalogo_modelos.sql`: catálogo e estrutura da planilha, 6 fases/24 atos/11 atividades.
- `database/migrations/v7_01_operacional.sql`: processos e etapas operacionais, atividades subordinadas, vínculos aos documentos.
- `database/migrations/v8_01_reinicializacao_logica.sql`: desativa dados operacionais anteriores com snapshot interno; opcional e impacta todos os municípios.
- `database/migrations/v9_01_integracao.sql`: índices de apoio e diagnóstico, para V8 já migrada.

## Backup semanal
Arquivos: `scripts/backup/backup-semanal.ps1` e `scripts/backup/instalar-agendamento.ps1`.
Execute manualmente o primeiro script e verifique que o ZIP inclui o SQL e a pasta `uploads` e que o arquivo SHA-256 corresponde. Configure o Agendador de Tarefas com credenciais adequadas usando o segundo script. O backup depende do XAMPP/Windows estar ligado e do acesso ao diretório de destino. Retenção padrão: 90 dias. Recomenda-se uma cópia externa/off-site e teste periódico de restauração. **Não copie backups nem credenciais para o GitHub.**

## Validação efetuada nesta entrega
- Parser `php -l` executado sobre os arquivos PHP (sem erros de sintaxe na máquina de preparação).
- Conferência do código da V6 com os quantitativos da planilha atual.
- Conferência de integridade do ZIP e presença dos SQLs/rotas.

## Limites de validação importantes
Este pacote **não foi executado no banco MariaDB real do usuário** e não passou por testes ponta-a-ponta no Apache/XAMPP com usuários, envios e permissões reais. Os módulos legados de indicadores, SLA, importação, arquivo de Etapa 1 e cálculos cronológicos têm conceitos anteriores que ainda precisam de testes funcionais e eventual ajuste para processos com várias etapas concorrentes. O cronograma é hoje centrado em `cronograma_processos` por município, portanto não possui marco inicial independente por processo. Não o classifique como entrega certificada para produção antes da homologação integrada.
