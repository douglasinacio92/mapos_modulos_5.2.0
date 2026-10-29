# Agente de impressão direta do Daitech-OS (Windows PowerShell 5.1 ou mais novo).
#
# Fica aberto no computador da loja, ouvindo só em http://127.0.0.1:9123 (não
# aceita conexões de outros computadores). O sistema, aberto no navegador
# deste computador, manda a página de impressão; o agente gera o PDF com o
# Microsoft Edge (já vem no Windows) e imprime com o SumatraPDF, sem abrir
# janela nenhuma, na impressora pedida: um nome do Windows ("ELGIN i9") ou
# uma impressora compartilhada ("\\CAIXA\ElginI9").
#
# Rotas:
#   GET  /status    -> {"ok":true,"impressoras":[...]}
#   POST /imprimir  -> corpo JSON {"impressora","tipo","ajuste","documentos":[html,...]}
#
# Só atende pedidos vindos do endereço do sistema (campo "site" do config.json).

$ErrorActionPreference = 'Stop'
$pasta = Split-Path -Parent $MyInvocation.MyCommand.Path
$config = Get-Content -Raw -Encoding UTF8 (Join-Path $pasta 'config.json') | ConvertFrom-Json

$porta = if ($config.porta) { [int]$config.porta } else { 9123 }
$site = ([string]$config.site).Trim().TrimEnd('/')
# O navegador manda só a origem (https://dominio, sem a pasta). Se o config.json
# tiver o endereço com a pasta do sistema (https://dominio/mapos), usa só a origem.
if ($site -match '^(https?://[^/]+)') { $site = $Matches[1].ToLower() }
$sumatra = if ($config.sumatra) { [string]$config.sumatra } else { Join-Path $pasta 'SumatraPDF.exe' }
$edge = [string]$config.edge
if (-not $edge) {
    $edge = @(
        "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
        "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe"
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1
}
$temp = Join-Path $env:TEMP 'daitech-impressao'
New-Item -ItemType Directory -Force -Path $temp | Out-Null
$log = Join-Path $pasta 'agente.log'
$limiteCorpo = 25MB

function Registrar([string]$texto) {
    $linha = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '  ' + $texto
    Add-Content -Path $log -Value $linha -Encoding UTF8
    Write-Host $linha
}

function Responder($fluxo, [int]$codigo, [string]$origem, $corpo) {
    $json = if ($null -ne $corpo) { $corpo | ConvertTo-Json -Compress -Depth 4 } else { '' }
    $bytes = [Text.Encoding]::UTF8.GetBytes($json)
    $status = @{ 200 = 'OK'; 204 = 'No Content'; 400 = 'Bad Request'; 403 = 'Forbidden'; 404 = 'Not Found'; 413 = 'Payload Too Large'; 500 = 'Internal Server Error' }[$codigo]
    $cab = "HTTP/1.1 $codigo $status`r`n"
    if ($origem) {
        $cab += "Access-Control-Allow-Origin: $origem`r`nVary: Origin`r`n"
        $cab += "Access-Control-Allow-Methods: GET, POST, OPTIONS`r`nAccess-Control-Allow-Headers: Content-Type`r`n"
        $cab += "Access-Control-Allow-Private-Network: true`r`nAccess-Control-Max-Age: 600`r`n"
    }
    $cab += "Content-Type: application/json; charset=utf-8`r`nContent-Length: $($bytes.Length)`r`nConnection: close`r`n`r`n"
    $cabBytes = [Text.Encoding]::ASCII.GetBytes($cab)
    $fluxo.Write($cabBytes, 0, $cabBytes.Length)
    if ($bytes.Length -gt 0) { $fluxo.Write($bytes, 0, $bytes.Length) }
    $fluxo.Flush()
}

function LerPedido($fluxo) {
    # Lê os cabeçalhos até a linha em branco e depois o corpo pelo Content-Length.
    $buffer = New-Object System.IO.MemoryStream
    $um = New-Object byte[] 1
    $fim = 0
    while ($fim -lt 4) {
        if ($fluxo.Read($um, 0, 1) -le 0) { return $null }
        $buffer.WriteByte($um[0])
        if ($buffer.Length -gt 65536) { return $null }
        $esperado = if ($fim % 2 -eq 0) { 13 } else { 10 }
        if ($um[0] -eq $esperado) { $fim++ } elseif ($um[0] -eq 13) { $fim = 1 } else { $fim = 0 }
    }
    $linhas = [Text.Encoding]::ASCII.GetString($buffer.ToArray()) -split "`r`n"
    $primeira = $linhas[0] -split ' '
    $cabecalhos = @{}
    foreach ($linha in $linhas[1..($linhas.Length - 1)]) {
        $i = $linha.IndexOf(':')
        if ($i -gt 0) { $cabecalhos[$linha.Substring(0, $i).Trim().ToLower()] = $linha.Substring($i + 1).Trim() }
    }
    $tamanho = 0
    if ($cabecalhos.ContainsKey('content-length')) { $tamanho = [int64]$cabecalhos['content-length'] }
    if ($tamanho -gt $limiteCorpo) { return @{ metodo = $primeira[0]; caminho = $primeira[1]; cabecalhos = $cabecalhos; grande = $true } }
    $corpo = New-Object byte[] $tamanho
    $lido = 0
    while ($lido -lt $tamanho) {
        $n = $fluxo.Read($corpo, $lido, $tamanho - $lido)
        if ($n -le 0) { break }
        $lido += $n
    }
    return @{ metodo = $primeira[0]; caminho = ($primeira[1] -split '\?')[0]; cabecalhos = $cabecalhos; corpo = [Text.Encoding]::UTF8.GetString($corpo, 0, $lido) }
}

function Encerrar($processo) {
    # Mata o processo e os filhos (o Edge abre vários) para não travar o próximo trabalho.
    if ($processo -and -not $processo.HasExited) {
        & taskkill.exe /PID $processo.Id /T /F 2>$null | Out-Null
    }
}

function LimparEdgesPresos {
    # Um Edge headless que ficou aberto prende o perfil e o próximo sai sem PDF.
    Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -like '*daitech-impressao*' } |
        ForEach-Object { & taskkill.exe /PID $_.ProcessId /T /F 2>$null | Out-Null }
}

function ImprimirDocumentos([string]$impressora, [string]$ajuste, $documentos) {
    LimparEdgesPresos
    $trabalhos = @()
    $n = 0
    # 1. Gera todos os PDFs ao mesmo tempo, um Edge (com perfil próprio) por documento.
    foreach ($html in $documentos) {
        $n++
        $id = [guid]::NewGuid().ToString('N')
        $arquivoHtml = Join-Path $temp "$id.html"
        $arquivoPdf = Join-Path $temp "$id.pdf"
        [IO.File]::WriteAllText($arquivoHtml, [string]$html, (New-Object Text.UTF8Encoding $false))
        $url = ([Uri]$arquivoHtml).AbsoluteUri
        $argsEdge = @('--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
            '--disable-extensions', '--disable-sync', '--no-pdf-header-footer',
            "--user-data-dir=`"$temp\perfil-edge-$n`"", "--print-to-pdf=`"$arquivoPdf`"", "`"$url`"")
        $p = Start-Process -FilePath $edge -ArgumentList $argsEdge -WindowStyle Hidden -PassThru
        $trabalhos += @{ processo = $p; html = $arquivoHtml; pdf = $arquivoPdf }
    }

    try {
        foreach ($t in $trabalhos) {
            if (-not $t.processo.WaitForExit(30000)) {
                Encerrar $t.processo
                throw 'O Edge demorou mais de 30 segundos para gerar o PDF.'
            }
            if (-not (Test-Path $t.pdf)) { throw "O Edge não gerou o PDF (código $($t.processo.ExitCode))." }
        }

        # 2. Imprime na ordem (a via do cliente antes da via da loja).
        foreach ($t in $trabalhos) {
            $argsSumatra = @('-print-to', "`"$impressora`"", '-print-settings', $ajuste, '-silent', "`"$($t.pdf)`"")
            $s = Start-Process -FilePath $sumatra -ArgumentList $argsSumatra -WindowStyle Hidden -PassThru
            if (-not $s.WaitForExit(60000)) {
                Encerrar $s
                throw "O SumatraPDF travou ao imprimir em '$impressora'. Confira se a impressora está ligada e sem papel preso."
            }
            if ($s.ExitCode -ne 0) { throw "O SumatraPDF não conseguiu imprimir em '$impressora' (código $($s.ExitCode)). Confira o nome da impressora." }
        }
    } finally {
        foreach ($t in $trabalhos) {
            Encerrar $t.processo
            Remove-Item -Force -ErrorAction SilentlyContinue $t.html, $t.pdf
        }
    }

    return $n
}

if (-not $site) { throw 'Preencha o campo "site" do config.json com o endereço do sistema, como https://sistema.daitech.com.br' }
if (-not $edge -or -not (Test-Path $edge)) { throw 'Microsoft Edge não encontrado. Informe o caminho do msedge.exe no campo "edge" do config.json.' }
if (-not (Test-Path $sumatra)) { throw "SumatraPDF não encontrado em $sumatra. Baixe o SumatraPDF portátil e coloque o SumatraPDF.exe nesta pasta." }

$ouvinte = New-Object System.Net.Sockets.TcpListener ([System.Net.IPAddress]::Loopback, $porta)
$ouvinte.Start()
Registrar "Agente de impressão no ar em http://127.0.0.1:$porta para $site"

while ($true) {
    try {
        $cliente = $ouvinte.AcceptTcpClient()
    } catch {
        Registrar "Erro ao aceitar conexão: $($_.Exception.Message)"
        Start-Sleep -Milliseconds 500
        continue
    }
    try {
        $cliente.ReceiveTimeout = 15000
        $fluxo = $cliente.GetStream()
        $pedido = LerPedido $fluxo
        if ($null -eq $pedido) { continue }

        $origem = [string]$pedido.cabecalhos['origin']
        if ($origem.TrimEnd('/').ToLower() -ne $site) {
            Responder $fluxo 403 '' @{ ok = $false; erro = 'Origem não autorizada.' }
            Registrar "Recusado: pedido de '$origem'."
            continue
        }

        if ($pedido.metodo -eq 'OPTIONS') { Responder $fluxo 204 $origem $null; continue }
        if ($pedido.grande) { Responder $fluxo 413 $origem @{ ok = $false; erro = 'Impressão grande demais.' }; continue }

        if ($pedido.metodo -eq 'GET' -and $pedido.caminho -eq '/status') {
            $nomes = @(Get-Printer | Select-Object -ExpandProperty Name)
            Responder $fluxo 200 $origem @{ ok = $true; impressoras = $nomes }
            continue
        }

        if ($pedido.metodo -eq 'POST' -and $pedido.caminho -eq '/imprimir') {
            $dados = $pedido.corpo | ConvertFrom-Json
            $impressora = [string]$dados.impressora
            # Só as opções conhecidas do SumatraPDF: escala e orientação.
            $partes = @(([string]$dados.ajuste) -split ',' | ForEach-Object { $_.Trim() } | Where-Object { @('fit', 'noscale', 'shrink', 'portrait', 'landscape') -contains $_ })
            $ajuste = if ($partes.Count -gt 0) { $partes -join ',' } else { 'fit' }
            $documentos = @($dados.documentos)
            if (-not $impressora -or $documentos.Count -eq 0 -or $documentos.Count -gt 10) {
                Responder $fluxo 400 $origem @{ ok = $false; erro = 'Pedido de impressão incompleto.' }
                continue
            }
            try {
                $n = ImprimirDocumentos $impressora $ajuste $documentos
                Registrar "Impresso: $($dados.tipo), $n documento(s) em '$impressora'."
                Responder $fluxo 200 $origem @{ ok = $true; documentos = $n }
            } catch {
                Registrar "Erro ao imprimir em '$impressora': $($_.Exception.Message)"
                Responder $fluxo 500 $origem @{ ok = $false; erro = $_.Exception.Message }
            }
            continue
        }

        Responder $fluxo 404 $origem @{ ok = $false; erro = 'Rota não encontrada.' }
    } catch {
        Registrar "Erro no pedido: $($_.Exception.Message)"
    } finally {
        $cliente.Close()
    }
}
