# PGE Stratelli V6 — entrega incremental de catálogo

## O que está implementado
- Nova tela de administração **Modelos de Processos**, em `/admin/modelos-processos`.
- Visualização aninhada Processo/modelo → Etapa → Fase → Ato → Atividade.
- Criação e edição de modelos, etapas, fases, atos e atividades no catálogo, com validação de campos, vinculação ao pai, proteção CSRF e acesso exclusivo ao administrador da plataforma.
- Preservação do texto dos prazos e fundamentos normativos dos atos da planilha de Rafael; código `2.5.1` inferido está identificado.
- Script `database/migrations/v6_01_catalogo_modelos.sql` para criar/popular tabelas independentes, sem tocar nas tabelas operacionais existentes.

## Instalação em **homologação**
1. Backup completo de MySQL/MariaDB, uploads e projeto.
2. Aplicar `database/migrations/v6_01_catalogo_modelos.sql` em cópia do banco MariaDB 10.4 (verificar previamente conflitos com nomes das novas tabelas).
3. Substituir a aplicação pela versão do pacote em servidor de testes, preservando `.env` e uploads fora do pacote.
4. Entrar como administrador da Stratelli, abrir **Modelos de Processos** e conferir estrutura e cadastros.
5. Conferir 6 fases, 24 atos, 11 atividades para o modelo importado; criar e editar um registro fictício e validar recarga.
6. Verificar que workflow antigo, documentos existentes e conclusões continuam inalterados.

## Limites desta entrega
- Este módulo administra **modelos reutilizáveis**, não instancia processos operacionais por município.
- O workflow operacional legado ainda usa `fases` e `atividades_fase`; a separação de atos e atividades na execução, a migração de documentos e a nova consolidação de indicadores **ainda não estão implementadas**.
- Não foram executados testes de integração contra servidor MariaDB real neste ambiente. Os testes PHP são de sintaxe/inspeção.
- Não copiar este SQL diretamente no banco de produção sem homologação.
- Exclusão de registros e alteração do vínculo pai não são permitidas nesta versão, para proteger integridade referencial.
