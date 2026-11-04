# Execute como Administrador do Windows, no PC/servidor que hospeda o banco.
param([string]$Nome='PGE-Stratelli-Backup-Semanal', [string]$Hora='02:00', [string]$Dia='Sunday')
$backup=Join-Path $PSScriptRoot 'backup-semanal.ps1'
if(-not(Test-Path $backup)){throw 'Script de backup não encontrado'}
$acao=New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "'+$backup+'"')
$disparo=New-ScheduledTaskTrigger -Weekly -DaysOfWeek $Dia -At $Hora
$settings=New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 3)
Register-ScheduledTask -TaskName $Nome -Action $acao -Trigger $disparo -Settings $settings -Description 'Backup lógico semanal MariaDB da PGE Stratelli' -Force
Write-Output "Agendamento criado: $Nome ($Dia às $Hora). Verifique a conta de execução e teste com Start-ScheduledTask."
