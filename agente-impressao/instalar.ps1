# Instala o agente de impressão: atalho na inicialização do Windows e abre agora.
$pasta = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not (Test-Path (Join-Path $pasta 'SumatraPDF.exe'))) {
    Write-Host 'Falta o SumatraPDF.exe nesta pasta. Baixe a versão portátil (64 bits) em https://www.sumatrapdfreader.org e coloque o SumatraPDF.exe aqui.'
    exit 1
}
$config = Get-Content -Raw -Encoding UTF8 (Join-Path $pasta 'config.json') | ConvertFrom-Json
if (-not $config.site -or $config.site -like '*COLOQUE-AQUI*') {
    Write-Host 'Abra o config.json e troque o "site" pelo endereço do sistema, como https://sistema.daitech.com.br'
    exit 1
}
$vbs = Join-Path $pasta 'iniciar-oculto.vbs'
$atalho = Join-Path ([Environment]::GetFolderPath('Startup')) 'Daitech-OS impressao.lnk'
$s = (New-Object -ComObject WScript.Shell).CreateShortcut($atalho)
$s.TargetPath = 'wscript.exe'
$s.Arguments = '"' + $vbs + '"'
$s.WorkingDirectory = $pasta
$s.Save()
Start-Process wscript.exe -ArgumentList ('"' + $vbs + '"')
Write-Host ''
Write-Host 'Pronto. O agente de impressão está aberto e vai abrir sozinho sempre que o Windows ligar.'
Write-Host 'Teste no sistema em Configurações > Módulos Daitech > Testar o agente.'
