#!/usr/bin/env bash
# Verifica que el entorno de desarrollo cumpla el estándar del LMS (Linux / WSL / macOS).
ok=0
check() { if eval "$2" >/dev/null 2>&1; then echo "✅ $1"; else echo "❌ $1  →  $3"; ok=1; fi; }
check "PHP 8.4"            "php -r 'exit(version_compare(PHP_VERSION, \"8.4.0\", \">=\") && version_compare(PHP_VERSION, \"8.5.0\", \"<\") ? 0 : 1);'" "Instale PHP 8.4 (php.new)"
for ext in pdo_pgsql mbstring intl bcmath zip gd pcntl; do
  check "Extensión $ext"  "php -m | grep -qi '^$ext$'" "Instale la extensión $ext"
done
check "Composer 2"         "composer --version | grep -q 'version 2'" "Instale Composer 2"
check "Bun"                "bun --version" "curl -fsSL https://bun.sh/install | bash"
check "Docker"             "docker info" "Inicie Docker"
check "PostgreSQL :5432"   "docker compose -f docker-compose.dev.yml exec -T postgres pg_isready -U lms" "docker compose -f docker-compose.dev.yml up -d"
check "Redis :6379"        "docker compose -f docker-compose.dev.yml exec -T redis redis-cli ping" "docker compose -f docker-compose.dev.yml up -d"
check "Archivo .env"       "test -f .env" "composer setup"
check "Fin de línea LF"    "test \"\$(git config core.autocrlf)\" != 'true'" "git config core.autocrlf input"
exit $ok
