# Agente de impressão direta do Daitech-OS (Windows PowerShell 5.1 ou mais novo).
#
# Fica aberto no computador da loja, ouvindo só em http://127.0.0.1:9123 (não
# aceita conexões de outros computadores). O sistema, aberto no navegador
# deste computador, transforma cada página (via do cupom, fileira de
# etiquetas, folha A4) numa imagem e manda para cá; o agente imprime as
# imagens direto na impressora pedida, com a impressão do próprio Windows
# (System.Drawing), sem abrir janela nenhuma. A impressora pode ser um nome do
# Windows ("ELGIN i9") ou uma impressora compartilhada ("\\CAIXA\ElginI9").
#
# Rotas:
#   GET  /status    -> {"ok":true,"impressoras":[...]}
#   POST /imprimir  -> {"impressora","tipo","orientacao","paginas":[{"png","larguraMm","alturaMm"}]}
#
# Só atende pedidos vindos do endereço do sistema (campo "site" do config.json).

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$pasta = Split-Path -Parent $MyInvocation.MyCommand.Path
$config = Get-Content -Raw -Encoding UTF8 (Join-Path $pasta 'config.json') | ConvertFrom-Json

$porta = if ($config.porta) { [int]$config.porta } else { 9123 }
$site = ([string]$config.site).Trim().TrimEnd('/')
# O navegador manda só a origem (https://dominio, sem a pasta). Se o config.json
# tiver o endereço com a pasta do sistema (https://dominio/mapos), usa só a origem.
if ($site -match '^(https?://[^/]+)') { $site = $Matches[1].ToLower() }
$log = Join-Path $pasta 'agente.log'
$limiteCorpo = 40MB

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

function LerJson([string]$texto) {
    # O ConvertFrom-Json do Windows PowerShell 5.1 não aceita textos de mais de
    # 2 MB (as imagens das páginas passam disso): usa o leitor do .NET sem limite.
    try {
        Add-Type -AssemblyName System.Web.Extensions
        $leitor = New-Object System.Web.Script.Serialization.JavaScriptSerializer
        $leitor.MaxJsonLength = [int]::MaxValue
        return $leitor.DeserializeObject($texto)
    } catch {
        # PowerShell 7 não tem esse leitor, mas o ConvertFrom-Json dele não tem o limite.
        return ($texto | ConvertFrom-Json)
    }
}

function Campo($objeto, [string]$nome) {
    # Lê um campo do JSON, venha ele do leitor do .NET (dicionário) ou do ConvertFrom-Json.
    if ($objeto -is [System.Collections.IDictionary]) { return $objeto[$nome] }
    return $objeto.$nome
}

function ImprimirPaginas([string]$impressora, [string]$tipo, [string]$orientacao, $paginas) {
    $imagens = New-Object System.Collections.ArrayList
    $tamanhos = New-Object System.Collections.ArrayList
    $documento = $null
    try {
        foreach ($p in $paginas) {
            $bytes = [Convert]::FromBase64String([string](Campo $p 'png'))
            $memoria = New-Object System.IO.MemoryStream (, $bytes)
            [void]$imagens.Add([System.Drawing.Image]::FromStream($memoria))
            [void]$tamanhos.Add(@([double](Campo $p 'larguraMm'), [double](Campo $p 'alturaMm')))
        }

        $documento = New-Object System.Drawing.Printing.PrintDocument
        $documento.PrinterSettings.PrinterName = $impressora
        if (-not $documento.PrinterSettings.IsValid) {
            throw "A impressora '$impressora' não existe neste computador. Confira o nome em Configurações > Módulos Daitech (as impressoras deste computador aparecem no Testar o agente)."
        }
        $documento.DocumentName = "Daitech-OS - $tipo"
        # Sem a janelinha "Imprimindo página 1 de 1".
        $documento.PrintController = New-Object System.Drawing.Printing.StandardPrintController

        $estado = @{ pagina = 0 }
        $documento.add_QueryPageSettings({
            param($remetente, $e)
            $t = $tamanhos[$estado.pagina]
            if ($tipo -eq 'a4') {
                $e.PageSettings.Margins = New-Object System.Drawing.Printing.Margins(39, 39, 39, 39)  # 10 mm
            } else {
                # Papel do tamanho da via/fileira: a térmica não puxa papel em branco.
                $largura = [int][Math]::Round($t[0] / 25.4 * 100)  # centésimos de polegada
                $altura = [int][Math]::Round($t[1] / 25.4 * 100)
                $e.PageSettings.PaperSize = New-Object System.Drawing.Printing.PaperSize('Daitech', $largura, $altura)
                $e.PageSettings.Margins = New-Object System.Drawing.Printing.Margins(0, 0, 0, 0)
            }
            if ($orientacao -eq 'landscape') { $e.PageSettings.Landscape = $true }
            elseif ($orientacao -eq 'portrait') { $e.PageSettings.Landscape = $false }
        }.GetNewClosure())

        $documento.add_PrintPage({
            param($remetente, $e)
            $imagem = $imagens[$estado.pagina]
            $g = $e.Graphics
            $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            if ($tipo -eq 'a4') {
                # Dentro das margens; a origem do Graphics é a área imprimível.
                $area = $e.MarginBounds
                $x = $area.X - $e.PageSettings.HardMarginX
                $y = $area.Y - $e.PageSettings.HardMarginY
                $largura = $area.Width
            } else {
                $x = 0
                $y = 0
                $largura = $e.PageSettings.PrintableArea.Width
                if ($largura -le 0) { $largura = $e.PageBounds.Width }
            }
            $altura = $largura * $imagem.Height / $imagem.Width
            $g.DrawImage($imagem, [single]$x, [single]$y, [single]$largura, [single]$altura)
            $estado.pagina++
            $e.HasMorePages = $estado.pagina -lt $imagens.Count
        }.GetNewClosure())

        $documento.Print()
        return $imagens.Count
    } finally {
        if ($documento) { $documento.Dispose() }
        foreach ($imagem in $imagens) { $imagem.Dispose() }
    }
}

function ListarImpressoras {
    # Lista local do Windows: rápida, não consulta impressoras de rede desligadas.
    return @([System.Drawing.Printing.PrinterSettings]::InstalledPrinters | ForEach-Object { [string]$_ })
}

if (-not $site) { throw 'Preencha o campo "site" do config.json com o endereço do sistema, como https://daitechsistemas.com.br' }

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
            $nomes = ListarImpressoras
            Responder $fluxo 200 $origem @{ ok = $true; impressoras = $nomes }
            continue
        }

        if ($pedido.metodo -eq 'POST' -and $pedido.caminho -eq '/imprimir') {
            $dados = LerJson $pedido.corpo
            $impressora = [string](Campo $dados 'impressora')
            $tipo = if (@('cupom', 'etiqueta', 'a4') -contains [string](Campo $dados 'tipo')) { [string](Campo $dados 'tipo') } else { 'a4' }
            $orientacao = if (@('portrait', 'landscape') -contains [string](Campo $dados 'orientacao')) { [string](Campo $dados 'orientacao') } else { 'auto' }
            $paginas = @(Campo $dados 'paginas')
            if (-not $impressora -or $paginas.Count -eq 0 -or $paginas.Count -gt 200) {
                Responder $fluxo 400 $origem @{ ok = $false; erro = 'Pedido de impressão incompleto. Atualize o sistema e o agente para a mesma versão.' }
                continue
            }
            $relogio = [Diagnostics.Stopwatch]::StartNew()
            try {
                $n = ImprimirPaginas $impressora $tipo $orientacao $paginas
                Registrar "Impresso: $tipo, $n página(s) em '$impressora' em $($relogio.ElapsedMilliseconds) ms."
                Responder $fluxo 200 $origem @{ ok = $true; paginas = $n }
            } catch {
                $motivo = $_.Exception.Message
                if ($_.Exception.InnerException) { $motivo = $_.Exception.InnerException.Message }
                Registrar "Erro ao imprimir em '$impressora': $motivo"
                Responder $fluxo 500 $origem @{ ok = $false; erro = $motivo }
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
