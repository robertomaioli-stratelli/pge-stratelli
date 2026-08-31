# INPACTA Produção v2 — Migração do MVP

Esta versão traz para a arquitetura SaaS os módulos construídos no MVP, mantendo autenticação, isolamento por município e URLs limpas.

## Itens migrados

- Dashboard Situacional por perfil
- fase global atual do processo
- cartões de prazo, progresso, pendências e análise
- situação de todo o processo no padrão visual da prévia
- pendências por secretaria
- gráfico Gantt responsivo com Hoje/Concluído
- Workflow de Contratação
- prévia do cronograma por fase
- controle de prazo encadeado pela conclusão da fase anterior
- documentos agrupados por secretaria
- modelo único compartilhado por documentos equivalentes
- envio, reenvio, aprovação, correção e versionamento
- situação documental por cores e percentuais
- log da fase
- central de notificações contextual
- catálogo geral de Documentos por fase
- Relatórios por perfil
- Histórico com escopo por perfil, paginação de 10 registros e ordenação por cabeçalho
- Configurações dinâmicas de fases, secretarias, departamentos, tipos, requisitos e modelos
- menu recolhível e impressão
- visão Macro Stratelli de municípios e usuários

## Perfis em produção

- Administrador Stratelli (`administrador_plataforma=1`): visão global, configurações e validação.
- Gestor/Admin municipal: visão de todas as secretarias do próprio município.
- Usuário comum: vinculado a uma secretaria e restrito aos documentos desta secretaria.

## Atualização de uma instalação v1 existente

1. Faça backup da pasta atual e do banco MySQL/MariaDB.
2. Preserve seu arquivo `.env`.
3. Substitua os arquivos da aplicação pelos arquivos da v2.
4. Execute na raiz do projeto:

```bat
C:\xampp\php\php.exe bin\upgrade_v2.php
C:\xampp\php\php.exe bin\check.php
```

O `upgrade_v2.php` é idempotente: ele pode ser executado novamente e somente complementa o que estiver faltando.
