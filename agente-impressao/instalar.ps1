# Instala o agente de impressão: atalho na inicialização do Windows e abre agora.
$pasta = Split-Path -Parent $MyInvocation.MyCommand.Path
$config = Get-Content -Raw -Encoding UTF8 (Join-Path $pasta 'config.json') | ConvertFrom-Json
if (-not $config.site -or $config.site -like '*COLOQUE-AQUI*') {
    Write-Host 'Abra o config.json e troque o "site" pelo endereço do sistema, como https://sistema.daitech.com.br'
    exit 1
}
# Fecha o agente que estiver aberto (versão antiga ou aberta à mão) e os Edges que ele deixou.
Get-CimInstance Win32_Process -Filter "Name='powershell.exe' OR Name='pwsh.exe'" |
    Where-Object { $_.CommandLine -like '*agente.ps1*' -and $_.ProcessId -ne $PID } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" |
    Where-Object { $_.CommandLine -like '*daitech-impressao*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Start-Sleep -Seconds 1

$vbs = Join-Path $pasta 'iniciar-oculto.vbs'
$atalho = Join-Path ([Environment]::GetFolderPath('Startup')) 'Daitech-OS impressao.lnk'
$s = (New-Object -ComObject WScript.Shell).CreateShortcut($atalho)
$s.TargetPath = 'wscript.exe'
$s.Arguments = '"' + $vbs + '"'
$s.WorkingDirectory = $pasta
$s.Save()
Start-Process wscript.exe -ArgumentList ('"' + $vbs + '"')
Write-Host ''
Start-Sleep -Seconds 3
try {
    $status = Invoke-RestMethod -Uri 'http://127.0.0.1:9123/status' -Headers @{ Origin = ([string]$config.site -replace '^(https?://[^/]+).*$', '$1') } -TimeoutSec 5
    Write-Host ('Agente respondendo. Impressoras: ' + (@($status.impressoras) -join ', '))
} catch {
    Write-Host 'O agente não respondeu. Veja o agente.log nesta pasta. Se a porta 9123 estiver ocupada, reinicie o computador e rode o instalar.bat de novo.'
}
Write-Host 'Pronto. O agente de impressão está aberto e vai abrir sozinho sempre que o Windows ligar.'
Write-Host 'Teste no sistema em Configurações > Módulos Daitech > Testar o agente.'
