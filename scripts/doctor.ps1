# Verifica que el entorno de desarrollo cumpla el estándar del LMS (Windows nativo).
$fallas = 0
function Check($nombre, [scriptblock]$prueba, $ayuda) {
  try { if (& $prueba) { Write-Host "OK  $nombre" -ForegroundColor Green; return } } catch {}
  Write-Host "X   $nombre  ->  $ayuda" -ForegroundColor Red; $script:fallas++
}
Check "PHP 8.4" { (php -r "echo PHP_VERSION;") -like "8.4.*" } "Instale PHP 8.4 (php.new)"
foreach ($ext in "pdo_pgsql","mbstring","intl","bcmath","zip","gd") {
  Check "Extension $ext" { (php -m) -contains $ext } "Habilite $ext en php.ini"
}
Check "Composer 2" { (composer --version) -match "version 2" } "Instale Composer 2"
Check "Bun" { bun --version } "powershell -c `"irm bun.sh/install.ps1 | iex`""
Check "Docker Desktop" { docker info *> $null; $LASTEXITCODE -eq 0 } "Inicie Docker Desktop"
Check "PostgreSQL :5432" { docker compose -f docker-compose.dev.yml exec -T postgres pg_isready -U lms *> $null; $LASTEXITCODE -eq 0 } "docker compose -f docker-compose.dev.yml up -d"
Check "Redis :6379" { (docker compose -f docker-compose.dev.yml exec -T redis redis-cli ping) -eq "PONG" } "docker compose -f docker-compose.dev.yml up -d"
Check "Archivo .env" { Test-Path .env } "composer setup"
Check "Fin de linea LF" { (git config core.autocrlf) -ne "true" } "git config core.autocrlf input"
Write-Host "`nNota: en Windows no se usa Horizon (requiere pcntl). Use 'composer dev:win'." -ForegroundColor Yellow
exit $fallas
