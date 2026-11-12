#!/bin/sh
# Arranque del contenedor según su rol: web | horizon | scheduler | reverb | <comando>.
# El rol sale de LMS_ROL si existe (Railway, donde cada servicio fija su variable) y, si no, del primer argumento.
# Solo el rol web migra y siembra; los demás esperan a que web esté sano (docker/compose.yml).
set -e

ROL="${LMS_ROL:-${1:-web}}"
cd /var/www/html

artisan() {
    su-exec www-data php artisan "$@"
}

# Conexión PDO a PostgreSQL. Con DB_HOST se usan las variables DB_*; sin él, DB_URL (Railway la entrega así).
# Laravel acepta las dos formas, pero estas comprobaciones corren antes de que arranque la aplicación.
CONEXION_PHP='
    if (getenv("DB_HOST")) {
        [$host, $puerto, $base, $usuario, $clave] = [getenv("DB_HOST"), getenv("DB_PORT") ?: 5432, getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")];
    } else {
        $url = parse_url((string) getenv("DB_URL"));
        [$host, $puerto, $base, $usuario, $clave] = [$url["host"] ?? "", $url["port"] ?? 5432, ltrim($url["path"] ?? "", "/"), urldecode($url["user"] ?? ""), urldecode($url["pass"] ?? "")];
    }
    $pdo = new PDO(sprintf("pgsql:host=%s;port=%s;dbname=%s", $host, $puerto, $base), $usuario, $clave);
'

esperar_base() {
    intentos=0
    until php -r "try { $CONEXION_PHP } catch (Throwable \$e) { exit(1); }"; do
        intentos=$((intentos + 1))
        [ "$intentos" -ge 60 ] && echo "PostgreSQL no responde (${DB_HOST:-DB_URL})." && exit 1
        echo "Esperando a PostgreSQL (${intentos})..."
        sleep 2
    done
}

hay_usuarios() {
    php -r "$CONEXION_PHP exit((int) \$pdo->query('select count(*) from users')->fetchColumn() > 0 ? 0 : 1);"
}

# Falla al arrancar, con un mensaje claro, si falta una variable obligatoria (si no, Laravel cae con un stacktrace
# de Pusher o de PDO). Cada alternativa se escribe «A|B»: basta con que una exista.
verificar_entorno() {
    faltan=""
    for requisito in APP_KEY REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET "DB_HOST|DB_URL" "REDIS_HOST|REDIS_URL"; do
        presente=0
        for variable in $(echo "$requisito" | tr '|' ' '); do
            [ -n "$(printenv "$variable")" ] && presente=1
        done
        [ "$presente" -eq 1 ] || faltan="${faltan}  - $(echo "$requisito" | sed 's/|/ o /')
"
    done
    if [ -n "$faltan" ]; then
        printf 'Faltan variables de entorno en el rol %s:\n%s' "$ROL" "$faltan" >&2
        echo "En Railway: enlace las variables compartidas a este servicio (Variables > Shared Variables)." >&2
        exit 1
    fi
}

case "$ROL" in
    web|horizon|scheduler|reverb) verificar_entorno ;;
esac

# Los volúmenes llegan vacíos la primera vez.
mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

case "$ROL" in
    web)
        sed -i \
            -e "s|__PORT__|${PORT:-80}|" \
            -e "s|__RESOLVER__|${NGINX_RESOLVER:-127.0.0.11 valid=30s ipv6=off}|" \
            -e "s|__REVERB_UPSTREAM__|${REVERB_UPSTREAM:-http://reverb:8080}|" \
            /etc/nginx/http.d/default.conf
        esperar_base
        artisan migrate --force
        if [ "${LMS_DEMO_SEED:-false}" = "true" ] && ! hay_usuarios; then
            echo "Base vacía: sembrando los datos de la demo..."
            artisan db:seed --force
        fi
        artisan storage:link --force >/dev/null 2>&1 || true
        artisan optimize
        exec supervisord -c /etc/supervisord.conf
        ;;
    horizon)
        artisan optimize
        exec su-exec www-data php artisan horizon
        ;;
    scheduler)
        artisan optimize
        exec su-exec www-data php artisan schedule:work
        ;;
    reverb)
        artisan optimize
        exec su-exec www-data php artisan reverb:start --host=0.0.0.0 --port=8080
        ;;
    *)
        exec "$@"
        ;;
esac
