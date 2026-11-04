# PGE Stratelli - cópia lógica semanal do banco completo (inclui tabelas da hierarquia).
# Requer PowerShell 5.1+, mysqldump.exe do MariaDB/XAMPP, .env local. Não envia cópias ao GitHub.
param(
 [string]$Projeto = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
 [string]$Destino = '',
 [string]$DumpExe = 'C:\xampp\mysql\bin\mysqldump.exe',
 [int]$RetencaoDias = 90
)
$ErrorActionPreference = 'Stop'
if (-not $Destino) { $Destino = Join-Path (Split-Path $Projeto -Parent) 'backups-pge' }
if (-not (Test-Path -LiteralPath $DumpExe -PathType Leaf)) { throw "mysqldump não encontrado: $DumpExe" }
$envFile = Join-Path $Projeto '.env'
if (-not (Test-Path -LiteralPath $envFile)) { throw 'Arquivo .env não encontrado no projeto' }
$values=@{}
foreach($line in (Get-Content -LiteralPath $envFile)) {
 if($line -match '^\s*([A-Z_]+)\s*=\s*(.*?)\s*$') { $values[$matches[1]]=$matches[2].Trim('"',"'") }
}
foreach($key in @('DB_HOST','DB_PORT','DB_NAME','DB_USER')) { if(-not $values[$key]) { throw "Configuração $key ausente" } }
New-Item -ItemType Directory -Path $Destino -Force | Out-Null
$lock=Join-Path $Destino '.backup.lock'
$stream=New-Object System.IO.FileStream($lock,[System.IO.FileMode]::OpenOrCreate,[System.IO.FileAccess]::ReadWrite,[System.IO.FileShare]::None)
$timestamp=Get-Date -Format 'yyyyMMdd_HHmmss'
$dbname=$values['DB_NAME']
if($dbname -notmatch '^[a-zA-Z0-9_]+$'){ throw 'DB_NAME contém caracteres inesperados' }
$basename="pge_${dbname}_${timestamp}"
$sql=Join-Path $Destino "$basename.sql"
$cnf=Join-Path $env:TEMP "pge_mysqldump_$([guid]::NewGuid().ToString('N')).cnf"
try {
 # Defaults file temporário: evita mostrar senha na linha de comando do processo.
 $pw=($values['DB_PASS'] -replace '\\','\\\\' -replace '"','\"')
 @("[client]","host=$($values['DB_HOST'])","port=$($values['DB_PORT'])","user=$($values['DB_USER'])",'password="'+$pw+'"') | Set-Content -LiteralPath $cnf -Encoding ASCII
 & icacls $cnf /inheritance:r /grant:r "${env:USERNAME}:(R)" *> $null
 $args=@("--defaults-extra-file=$cnf",'--single-transaction','--quick','--routines','--triggers','--events','--default-character-set=utf8mb4','--databases',$dbname,'--result-file='+$sql)
 & $DumpExe @args
 if($LASTEXITCODE -ne 0) { throw "mysqldump falhou (exit $LASTEXITCODE)" }
 if(-not (Test-Path $sql) -or (Get-Item $sql).Length -lt 100) { throw 'Arquivo SQL vazio ou incompleto' }
 $zip=Join-Path $Destino "$basename.zip"
 # Inclui arquivos físicos (documentos/modelos) além do SQL.
 $uploadDir=Join-Path $Projeto 'storage\uploads'
 $contents=@($sql)
 if(Test-Path -LiteralPath $uploadDir -PathType Container){ $contents+= $uploadDir }
 Compress-Archive -LiteralPath $contents -DestinationPath $zip -CompressionLevel Optimal
 if(-not (Test-Path $zip)) { throw 'Falha ao gerar ZIP' }
 $hash=(Get-FileHash -Path $zip -Algorithm SHA256).Hash
 "$hash  $(Split-Path $zip -Leaf)" | Set-Content -LiteralPath "$zip.sha256" -Encoding UTF8
 Remove-Item -LiteralPath $sql -Force
 Get-ChildItem -LiteralPath $Destino -File | Where-Object { $_.Name -match '^pge_.*\.(zip|zip\.sha256)$' -and $_.LastWriteTime -lt (Get-Date).AddDays(-$RetencaoDias) } | Remove-Item -Force
 Write-Output "Backup gerado: $zip | SHA256: $hash"
} catch {
 if(Test-Path $sql){Remove-Item -LiteralPath $sql -Force}
 throw
} finally {
 Remove-Item -LiteralPath $cnf -Force -ErrorAction SilentlyContinue
 if($stream){$stream.Dispose()}
}
