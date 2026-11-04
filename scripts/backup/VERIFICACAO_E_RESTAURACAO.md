# Backup semanal — operação e recuperação

- Salva **todo o banco** configurado no `.env`, incluindo as tabelas criadas pela V7. Isto é mais seguro do que copiar só uma tabela, pois os vínculos e documentos dependem de múltiplas tabelas.
- Executar `powershell -ExecutionPolicy Bypass -File scripts\backup\backup-semanal.ps1` no Windows/XAMPP e verificar ZIP, SHA-256 e horário.
- Para agendar: abrir PowerShell **como Administrador** e executar `scripts\backup\instalar-agendamento.ps1`. Por padrão, domingos às 02:00, com 90 dias de retenção. A tarefa é criada **somente quando você executar** o script.
- O `.env` deve estar no diretório do projeto. O backup fica **fora** do projeto/Git, em `..\backups-pge`. Proteja este local com ACL e cópia externa criptografada. O ZIP **não é criptografado**.
- O script só contempla **banco de dados**, não arquivos físicos em `storage/uploads` ou outros diretórios; agende também backup destes diretórios, com mesma periodicidade.
- Simule a restauração em banco **descartável**: extraia o ZIP, crie um banco de homologação, ajuste o nome do banco da instrução `CREATE DATABASE/USE` caso necessário e importe o SQL com `mysql.exe`; nunca teste a restauração sobre produção.
- O agendamento não foi executado neste ambiente. Requer `mysqldump`, permissões de gravação e agendador de tarefas do servidor.
