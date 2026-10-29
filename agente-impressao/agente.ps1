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
$site = ([string]$config.site).TrimEnd('/')
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

function Imprimir([string]$impressora, [string]$ajuste, [string]$html, [int]$numero) {
    $id = [guid]::NewGuid().ToString('N')
    $arquivoHtml = Join-Path $temp "$id.html"
    $arquivoPdf = Join-Path $temp "$id.pdf"
    [IO.File]::WriteAllText($arquivoHtml, $html, (New-Object Text.UTF8Encoding $false))
    $url = ([Uri]$arquivoHtml).AbsoluteUri

    $argsEdge = @('--headless=new', '--disable-gpu', '--no-first-run', '--no-pdf-header-footer',
        "--user-data-dir=`"$temp\perfil-edge`"", '--virtual-time-budget=5000',
        "--print-to-pdf=`"$arquivoPdf`"", "`"$url`"")
    $p = Start-Process -FilePath $edge -ArgumentList $argsEdge -WindowStyle Hidden -PassThru -Wait
    if (-not (Test-Path $arquivoPdf)) { throw "O Edge não gerou o PDF (código $($p.ExitCode))." }

    $argsSumatra = @('-print-to', "`"$impressora`"", '-print-settings', $ajuste, '-silent', "`"$arquivoPdf`"")
    $s = Start-Process -FilePath $sumatra -ArgumentList $argsSumatra -WindowStyle Hidden -PassThru -Wait
    if ($s.ExitCode -ne 0) { throw "O SumatraPDF não conseguiu imprimir em '$impressora' (código $($s.ExitCode))." }

    Remove-Item -Force -ErrorAction SilentlyContinue $arquivoHtml, $arquivoPdf
}

if (-not $site) { throw 'Preencha o campo "site" do config.json com o endereço do sistema, como https://sistema.daitech.com.br' }
if (-not $edge -or -not (Test-Path $edge)) { throw 'Microsoft Edge não encontrado. Informe o caminho do msedge.exe no campo "edge" do config.json.' }
if (-not (Test-Path $sumatra)) { throw "SumatraPDF não encontrado em $sumatra. Baixe o SumatraPDF portátil e coloque o SumatraPDF.exe nesta pasta." }

$ouvinte = New-Object System.Net.Sockets.TcpListener ([System.Net.IPAddress]::Loopback, $porta)
$ouvinte.Start()
Registrar "Agente de impressão no ar em http://127.0.0.1:$porta para $site"

while ($true) {
    $cliente = $ouvinte.AcceptTcpClient()
    try {
        $cliente.ReceiveTimeout = 15000
        $fluxo = $cliente.GetStream()
        $pedido = LerPedido $fluxo
        if ($null -eq $pedido) { continue }

        $origem = [string]$pedido.cabecalhos['origin']
        if ($origem.TrimEnd('/') -ne $site) {
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
            $ajuste = if (@('fit', 'noscale', 'shrink') -contains [string]$dados.ajuste) { [string]$dados.ajuste } else { 'fit' }
            $documentos = @($dados.documentos)
            if (-not $impressora -or $documentos.Count -eq 0 -or $documentos.Count -gt 10) {
                Responder $fluxo 400 $origem @{ ok = $false; erro = 'Pedido de impressão incompleto.' }
                continue
            }
            try {
                $n = 0
                foreach ($html in $documentos) { $n++; Imprimir $impressora $ajuste ([string]$html) $n }
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
