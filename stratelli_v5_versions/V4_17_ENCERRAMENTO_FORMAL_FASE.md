# INPACTA v4.17 — Encerramento Formal e Auditável de Fases

A v4.17 separa duas situações que até então eram tratadas como equivalentes:

1. **100% documental aprovado** — todos os documentos obrigatórios da fase foram aprovados;
2. **fase formalmente encerrada** — a Stratelli registrou o ato de encerramento com data, responsável, observação e snapshot documental.

A próxima fase só é liberada após o **encerramento formal** da fase anterior.

## Fluxo operacional

1. Município/Secretarias enviam os documentos da fase.
2. A Stratelli analisa e aprova ou solicita correção.
3. Quando todos os documentos obrigatórios estão aprovados, a fase muda para **PRONTA PARA ENCERRAMENTO**.
4. A Stratelli abre a fase e registra **Encerrar fase**.
5. O sistema valida a integridade dos arquivos, gera o snapshot, calcula o SHA-256 do snapshot e registra o ato.
6. A fase passa para **ENCERRADA** e a próxima fase é liberada.

## Dados registrados no encerramento

- município e fase;
- data formal do encerramento;
- usuário Stratelli responsável;
- data/hora do registro;
- observação obrigatória;
- snapshot JSON dos requisitos e documentos da fase;
- versões dos documentos;
- secretarias/departamentos responsáveis;
- situação documental;
- SHA-256 de cada documento;
- modelo documental vigente, quando existente;
- SHA-256 do snapshot completo.

A data formal não pode:

- estar no futuro;
- ser anterior ao início operacional da fase;
- ser anterior ao encerramento da fase precedente + 1 dia;
- ser anterior à aprovação do último documento obrigatório.

## Integridade antes do encerramento

Antes de encerrar, o sistema verifica novamente os arquivos físicos que compõem o snapshot. Se um documento ou modelo auditado estiver ausente ou tiver SHA-256 divergente, o encerramento é bloqueado.

## Fase encerrada

Depois do encerramento:

- novos envios e reenvios são bloqueados no servidor;
- novas validações são bloqueadas;
- troca de modelo da fase é bloqueada;
- alteração/inativação dos requisitos documentais da fase é bloqueada;
- alteração/inativação da própria fase é bloqueada;
- o marco inicial do processo não pode ser recalculado enquanto existir uma fase formalmente encerrada.

O bloqueio não depende apenas da interface; as Services verificam o estado da fase.

## Reabertura auditada

A Stratelli pode usar **Reabrir fase**, informando obrigatoriamente o motivo.

A reabertura:

- não apaga o encerramento anterior;
- preserva o snapshot e seu SHA-256 no histórico;
- registra usuário, data/hora e motivo;
- volta a permitir alterações documentais naquela fase;
- gera notificação e evento de auditoria.

Uma fase anterior não pode ser reaberta enquanto existir uma fase posterior formalmente encerrada. Nesse caso, as fases posteriores devem ser reabertas primeiro, da última para a primeira.

## Estruturas do banco

A v4.17 amplia `cronograma_fases` para armazenar o estado atual do encerramento e cria `historico_fases` como histórico imutável dos atos de encerramento e reabertura.

O histórico possui proteção contra `DELETE` físico por trigger.

## Migração

Antes da atualização, faça backup do banco e de `storage/uploads`.

Depois de substituir os arquivos:

```bat
cd C:\xampp\htdocs\stratelli\plataforma_etapa1
C:\xampp\php\php.exe bin\upgrade_v4_17.php
C:\xampp\php\php.exe bin\check.php
```

Conclusões manuais já existentes em `cronograma_fases` são preservadas como encerramentos legados. Como não existia snapshot na época desses registros, a migração **não inventa** um snapshot histórico.

## Teste recomendado

1. Entre como Stratelli em Maringá.
2. Deixe todos os documentos obrigatórios da fase atual como `APROVADO`.
3. Confirme que a fase aparece como **PRONTA PARA ENCERRAMENTO**, mas a próxima continua bloqueada para o Gestor/Usuário.
4. Registre o encerramento com data e observação.
5. Confirme a criação do snapshot e a liberação da próxima fase.
6. Volte à fase encerrada e confirme que uploads/validações estão bloqueados.
7. Reabra a fase informando um motivo.
8. Confirme que ela volta a aceitar alterações e que o encerramento anterior continua no histórico formal.
