param(
    [string]$BaseUrl = "http://localhost/sistema-cameras-v1.0/public"
)

$ErrorActionPreference = "Stop"

function Assert-StatusCode {
    param(
        [int]$Actual,
        [int]$Expected,
        [string]$Label
    )

    if ($Actual -ne $Expected) {
        throw "Falha: $Label (esperado=$Expected, atual=$Actual)"
    }

    Write-Host "OK: $Label ($Actual)"
}

function Get-StatusCode {
    param(
        [string]$Url,
        [string]$Method = "GET"
    )

    try {
        $response = Invoke-WebRequest -Uri $Url -Method $Method -UseBasicParsing -TimeoutSec 10
        return [int]$response.StatusCode
    } catch {
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            return [int]$_.Exception.Response.StatusCode.value__
        }
        throw "Não foi possível conectar em $Url"
    }
}

Write-Host "=== Smoke Básico do Sistema ==="

# 1. Página inicial
$homeUrl = "$BaseUrl/"
$code = Get-StatusCode -Url $homeUrl -Method "GET"
Assert-StatusCode -Actual $code -Expected 200 -Label "Página inicial carrega"

# 2. API health sem auth
$healthUrl = "$BaseUrl/index.php?page=api/api_health"
$code = Get-StatusCode -Url $healthUrl -Method "GET"
Assert-StatusCode -Actual $code -Expected 401 -Label "API health sem autenticação"

# 3. Página login
$loginUrl = "$BaseUrl/index.php?page=login"
$code = Get-StatusCode -Url $loginUrl -Method "GET"
Assert-StatusCode -Actual $code -Expected 200 -Label "Página login carrega"

# 4. API cameras sem auth
$camsUrl = "$BaseUrl/index.php?page=api/api_cameras"
$code = Get-StatusCode -Url $camsUrl -Method "GET"
Assert-StatusCode -Actual $code -Expected 401 -Label "API cameras sem autenticação"

Write-Host "=== Smoke Básico concluido ==="
