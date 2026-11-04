# PGE Stratelli — V8: reinicialização lógica / cadastro hierárquico

## O que foi atualizado
- Atalho dentro de **Administração > Configurações** para o cadastro existente em `/admin/modelos-processos`, que já permite criar/editar Etapa, Fase, Ato e Atividade, no modelo de Processo.
- Script `database/migrations/v8_01_reinicializacao_logica.sql` para retirar da operação **todas as fases, atos, atividades e requisitos documentais anteriores** sem destruir registros auditados.
- Script `database/migrations/v8_02_restaurar_reinicio.sql` para restaurar o estado anterior dos campos `ativo`, usando tabelas snapshot criadas na execução de V8_01.

## Antes de aplicar
1. Backup externo completo do MariaDB e da pasta de uploads/documentos. Valide restauração.
2. Confirme que as migrações V6 e V7 já foram aplicadas, inclusive `pge_atividades_ato`.
3. **Teste em banco de homologação**. O script V8_01 desativa cadastros de **TODOS os municípios**.
4. Execute **uma única vez** `v8_01_reinicializacao_logica.sql` pelo phpMyAdmin ou mysql.
5. Publique o código do ZIP mantendo suas configurações locais/credenciais atuais; não sobrescreva arquivos de configuração sem comparação.
6. Faça login como administrador, abra **Configurações > Configurar Etapas, Fases, Atos e Atividades**.
7. Cadastre ou revise os modelos; o modelo `DIALOGO_COMPETITIVO_GOUVEIA`, se importado pela V6, já corresponde à planilha revisada (6 fases, 24 atos, 11 atividades).

## Limitações importantes
- Este procedimento **não exclui fisicamente** fases/atos anteriores; ele os inativa, preservando chaves estrangeiras e documentos históricos. O banco original impede exclusão física de documentos auditados.
- O catálogo (modelo) anterior não é apagado; deve ser editado ou substituído de forma administrada na interface. Não se deve destruir um modelo que já tenha sido usado para instanciar processos sem conciliação.
- A V7 ainda contém telas/serviços legados. O atalho em Configurações não é uma reescrita transversal desses módulos. Não utilizar este pacote para produção sem testes de regressão.
- Reiniciar a operação com novos registros exige criar processos a partir do modelo após a limpeza lógica. Validar primeiro em homologação.
- A rotina de backup semanal da V7 depende de agendamento no Windows e **não** substitui o backup anterior à migração.
