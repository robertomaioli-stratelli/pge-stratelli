# PGE Stratelli — V25 | Mapa executivo + marco formal de início

## Antes de aplicar
1. Faça backup de todo o banco `stratelli` **e** de `storage/uploads`.
2. Aplique primeiro em uma cópia de homologação. Esta entrega passou por lint PHP, mas não foi testada no MariaDB/Apache do seu computador.
3. Execute `database/migrations/V25_INICIO_FORMAL.sql` pelo phpMyAdmin.
4. Copie **somente os arquivos deste pacote** para o projeto, preservando seus diretórios. **Não substitua `.env` nem configurações de conexão.**
5. Se o OPcache estiver ativo, reinicie o Apache; atualize o navegador com Ctrl+F5.

## Fluxo de abertura
- Com administrador Stratelli: abra `/maringa/workflow` (ou o município correspondente).
- Na seção **Marco formal do cronograma**, informe a **data de vigência**, a referência do documento, anexe o **PDF assinado** (máximo 20 MB) e confirme.
- O sistema salva o PDF em `storage/uploads/marcos_formais/<municipio_id>/`, fora de `public`, com SHA-256 e referência ao usuário responsável.
- A data registrada passa a ser a origem da programação por fase. O início formal só pode ser registrado uma vez na interface; eventual correção exigirá procedimento auditado próprio.
- Os usuários com acesso à instância podem baixar o documento de abertura. Somente administradores da plataforma podem formalizar a abertura.

## Regra sem abertura
- Os registros antigos de `cronograma_processos` não autorizam iniciar contagem.
- O Workflow, o Dashboard e a Visão Macro apresentam `AGUARDANDO INÍCIO`, sem dias vencidos.
- Gantt mostra trilhas vazias, mesmo que existam datas preenchidas na versão antiga.
- SLA por secretaria deixa de pontuar atraso por fase antes do início (por meio do cronograma central).
- O servidor rejeita a conclusão manual de Atos/Atividades e o encerramento formal de Fases antes do documento de abertura.
- A antiga rota de alteração manual do marco inicial é desativada.

## Limitações a considerar
- **O marco documental desta atualização é por município, não individual por Etapa.** Os processos ou etapas independentes precisarão de uma extensão do modelo para ter marcos próprios.
- O cronograma ainda usa a duração legada por Fase para seus prazos; cálculos normativos por Ato/Atividade, feriados e marcos jurídicos compostos não são automaticamente homologados por este patch.
- Datas previstas cadastradas manualmente em Atos/Atividades não são recalculadas aqui a partir do documento. O Gantt somente deixa de apresentá-las como vigentes antes da abertura e volta a exibi-las após o marco.
- Os históricos de marcos de versões anteriores são preservados, mas não considerados autorização formal. Se algum município já teve início real, o documento correspondente precisará ser cadastrado após conferência.
- Esta versão não inclui rotina de cancelamento ou retificação de início formal. Em caso de erro, não edite ou apague diretamente a linha no banco.

## Testes recomendados em homologação
1. Abrir Visão Macro, Workflow, Dashboard, Relatórios, Pendências e Gantt **sem início formal**. Todas as telas devem abrir sem erro 500, com prazo aguardando início.
2. Tentar conclusão de Ato/Atividade e encerramento de Fase antes do documento: deve bloquear com mensagem.
3. Registrar documento PDF formal como admin, validar usuário, referência, arquivo e checksum; testar download.
4. Conferir recálculo de início e término das Fases e status após a abertura.
5. Recarregar Visão Macro e verificar o card de situação, próxima ação e mapa centrado.
6. Confirmar que nenhuma configuração ou dado histórico foi apagado pela migração.

**Atenção:** instalação por partes (somente PHP ou somente SQL) causará erros. SQL primeiro; depois copie todos os arquivos incluídos nesta V25.
