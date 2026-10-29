# Para o agente de impressão e tira o atalho da inicialização do Windows.
$atalho = Join-Path ([Environment]::GetFolderPath('Startup')) 'Daitech-OS impressao.lnk'
Remove-Item -ErrorAction SilentlyContinue $atalho
Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" |
    Where-Object { $_.CommandLine -like '*agente.ps1*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" |
    Where-Object { $_.CommandLine -like '*daitech-impressao*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Write-Host 'Agente de impressão removido.'
