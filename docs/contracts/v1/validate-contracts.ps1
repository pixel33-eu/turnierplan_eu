$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$validator = Join-Path $repositoryRoot 'tools\validate-contracts.mjs'

Push-Location $repositoryRoot

try {
    & node $validator

    if ($LASTEXITCODE -ne 0) {
        throw 'Die Vertragsprüfung ist fehlgeschlagen.'
    }
} finally {
    Pop-Location
}
