$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$npxCommand = (Get-Command npx.cmd -ErrorAction Stop).Source

Push-Location $repositoryRoot

try {
    $jsonFiles = @(
        Get-ChildItem -Path 'docs\contracts\v1' -Filter '*.json' -Recurse -File |
            ForEach-Object { $_.FullName }
    )

    & node -e "const fs=require('fs'); for (const f of process.argv.slice(1)) JSON.parse(fs.readFileSync(f,'utf8'));" $jsonFiles

    if ($LASTEXITCODE -ne 0) {
        throw 'Mindestens eine Vertragsdatei enthält ungültiges JSON.'
    }

    $positiveChecks = @(
        @(
            'docs/contracts/v1/schemas/embed-config.schema.json',
            'docs/contracts/v1/examples/valid/embed-config-*.json'
        ),
        @(
            'docs/contracts/v1/schemas/metadata-response.schema.json',
            'docs/contracts/v1/examples/valid/metadata-success.json'
        ),
        @(
            'docs/contracts/v1/schemas/error-response.schema.json',
            'docs/contracts/v1/examples/valid/error-tournament-not-found.json'
        ),
        @(
            'docs/contracts/v1/schemas/embed-message.schema.json',
            'docs/contracts/v1/examples/valid/message-*.json'
        )
    )

    foreach ($check in $positiveChecks) {
        & $npxCommand --yes ajv-cli@5 validate --spec=draft2020 -s $check[0] -d $check[1]

        if ($LASTEXITCODE -ne 0) {
            throw "Gültiges Vertragsbeispiel abgelehnt: $($check[1])"
        }
    }

    $negativeChecks = @(
        @(
            'docs/contracts/v1/schemas/embed-config.schema.json',
            'docs/contracts/v1/examples/invalid/embed-config-external-url.json'
        ),
        @(
            'docs/contracts/v1/schemas/metadata-response.schema.json',
            'docs/contracts/v1/examples/invalid/metadata-private-field.json'
        ),
        @(
            'docs/contracts/v1/schemas/embed-message.schema.json',
            'docs/contracts/v1/examples/invalid/message-wrong-type.json'
        )
    )

    foreach ($check in $negativeChecks) {
        $previousErrorPreference = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        $negativeOutput = & $npxCommand --yes ajv-cli@5 validate --spec=draft2020 -s $check[0] -d $check[1] 2>&1
        $negativeExitCode = $LASTEXITCODE
        $ErrorActionPreference = $previousErrorPreference

        if ($negativeExitCode -eq 0) {
            throw "Ungültiges Vertragsbeispiel akzeptiert: $($check[1])"
        }
    }

    $metadata = Get-Content -LiteralPath 'docs\contracts\v1\examples\valid\metadata-success.json' -Raw -Encoding UTF8 |
        ConvertFrom-Json

    if ($metadata.supported_languages -notcontains $metadata.response_language) {
        throw 'response_language fehlt in supported_languages.'
    }

    if ($metadata.supported_languages -notcontains $metadata.tournament.default_language) {
        throw 'default_language fehlt in supported_languages.'
    }

    $groupIds = @($metadata.groups | ForEach-Object { $_.id })

    foreach ($participant in $metadata.participants) {
        foreach ($groupId in $participant.group_ids) {
            if ($groupIds -notcontains $groupId) {
                throw "Teilnehmer referenziert eine unbekannte Gruppe: $groupId"
            }
        }
    }

    $viewIds = @($metadata.views | ForEach-Object { $_.id })

    if (@($viewIds | Select-Object -Unique).Count -ne $viewIds.Count) {
        throw 'Eine Ansicht kommt in den Metadaten mehrfach vor.'
    }

    $allowedFilters = @{
        standings = @('group')
        matches = @('group', 'participant', 'match_number_range', 'date_range')
    }
    $allowedOptions = @{
        standings = @(
            'team_logos',
            'played',
            'wins_draws_losses',
            'score_balance',
            'points',
            'group_navigation'
        )
        matches = @(
            'match_number',
            'date',
            'time',
            'field',
            'group',
            'round',
            'referee',
            'live_state',
            'extra_time',
            'penalty_result'
        )
    }

    foreach ($view in $metadata.views) {
        foreach ($filter in $view.filters) {
            if ($allowedFilters[$view.id] -notcontains $filter) {
                throw "Filter $filter ist für Ansicht $($view.id) nicht erlaubt."
            }
        }

        foreach ($option in $view.options) {
            if ($allowedOptions[$view.id] -notcontains $option) {
                throw "Option $option ist für Ansicht $($view.id) nicht erlaubt."
            }
        }
    }

    if ($metadata.tournament.public_url -notlike "*$($metadata.tournament.ref)") {
        throw 'public_url und tournament.ref stimmen nicht überein.'
    }

    if (
        $metadata.tournament.state -in @('completed', 'cancelled') -and
        $null -ne $metadata.tournament.refresh_interval_seconds
    ) {
        throw 'Ein abgeschlossenes oder abgesagtes Turnier darf kein Pollingintervall haben.'
    }

    if (
        $metadata.branding.policy -eq 'required' -and
        $metadata.branding.default_visible -ne $true
    ) {
        throw 'Erforderliches Branding muss standardmäßig sichtbar sein.'
    }

    if (
        $metadata.branding.policy -eq 'hidden' -and
        $metadata.branding.default_visible -ne $false
    ) {
        throw 'Ausgeblendetes Branding darf nicht standardmäßig sichtbar sein.'
    }

    $configFiles = @(
        Get-ChildItem -Path 'docs\contracts\v1\examples\valid' -Filter 'embed-config-*.json' -File
    )

    foreach ($configFile in $configFiles) {
        $config = Get-Content -LiteralPath $configFile.FullName -Raw -Encoding UTF8 | ConvertFrom-Json

        if (
            $null -ne $config.matchFrom -and
            $null -ne $config.matchTo -and
            $config.matchFrom -gt $config.matchTo
        ) {
            throw "matchFrom liegt nach matchTo: $($configFile.Name)"
        }

        if (
            $null -ne $config.dateFrom -and
            $null -ne $config.dateTo
        ) {
            $dateFrom = [DateTime]::ParseExact(
                $config.dateFrom,
                'yyyy-MM-dd',
                [Globalization.CultureInfo]::InvariantCulture
            )
            $dateTo = [DateTime]::ParseExact(
                $config.dateTo,
                'yyyy-MM-dd',
                [Globalization.CultureInfo]::InvariantCulture
            )

            if ($dateFrom -gt $dateTo) {
                throw "dateFrom liegt nach dateTo: $($configFile.Name)"
            }
        }

        if ($config.minHeight -gt $config.maxHeight) {
            throw "minHeight liegt über maxHeight: $($configFile.Name)"
        }
    }

    $linkErrors = @()

    Get-ChildItem -Path . -Filter '*.md' -Recurse -File | ForEach-Object {
        $markdownFile = $_
        $content = Get-Content -LiteralPath $markdownFile.FullName -Raw -Encoding UTF8
        $pattern = '\]\((?!https?://|mailto:|#)([^\)#]+)(?:#[^\)]*)?\)'

        foreach ($match in [regex]::Matches($content, $pattern)) {
            $relativeTarget = $match.Groups[1].Value.Trim('<', '>')
            $targetPath = Join-Path $markdownFile.DirectoryName $relativeTarget

            if (-not (Test-Path -LiteralPath $targetPath)) {
                $linkErrors += "$($markdownFile.FullName): $relativeTarget"
            }
        }
    }

    if ($linkErrors.Count -gt 0) {
        throw "Lokale Markdown-Linkziele fehlen: $($linkErrors -join ', ')"
    }

    Write-Output "Contract validation passed: $($jsonFiles.Count) JSON files, $($positiveChecks.Count) positive schema groups, $($negativeChecks.Count) expected failures, semantic relations and local links."
} finally {
    Pop-Location
}
