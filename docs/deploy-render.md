# Desplegar Fit Home en Render Free

El repositorio define un único Web Service Docker. Apache prefork carga PHP 8.3 en sus propios procesos. Esta alternativa a Nginx con PHP-FPM permite ejecutar Apache en primer plano como PID 1, con cierre ordenado y un máximo de tres procesos de atención. Sirve solo `public/`. La API utiliza respuestas JSON y la página de bienvenida incluye estilos de reserva. Por ello el build prescinde de Node y Vite.

## Crear el servicio

1. Sube los commits locales revisados a la rama `main` de GitHub (`git push origin main`).
2. Abre el [panel de Render](https://dashboard.render.com/), selecciona **New → Web Service → Git Provider**, conecta GitHub y selecciona el repositorio de Fit Home.
3. Elige la rama **main**, una región cercana a Supabase, lenguaje **Docker**, directorio raíz del repositorio y Dockerfile **./Dockerfile**. Deja el comando Docker predeterminado. El script de la imagen arranca Apache en `0.0.0.0:$PORT` y usa 10000 cuando falta PORT.
4. Selecciona la instancia **Free**. En **Advanced**, establece **Health Check Path** en `/health` y configura **Environment** según la tabla. Deja vacíos los comandos predeploy. El esquema y el catálogo ya están cargados.
5. Pulsa **Create Web Service**. Revisa en **Events / Logs** la instalación desde composer.lock, la verificación de extensiones y la creación de las cachés. Espera a que el servicio pase el health check y copia su URL HTTPS pública. Comprueba que coincide con APP_URL.
6. Si prefieres usar un Blueprint, selecciona **New → Blueprint** y conecta el mismo repositorio. El [render.yaml](../render.yaml) declara el servicio Free y solicita los valores privados al crearlo. Elige una de las dos vías para evitar duplicar el servicio. [Guía de Web Services](https://render.com/docs/web-services) · [Blueprints](https://render.com/docs/blueprint-spec)

## Variables del servidor

| Variable | Valor o procedencia |
| --- | --- |
| APP_NAME, APP_ENV, APP_DEBUG | La imagen proporciona `Fit Home`, `production` y `false`. |
| APP_KEY | Clave estable de 32 bytes para AES-256-CBC. Si existe una clave de producción conserva esa clave. Para un entorno nuevo, genera una sola vez con `php artisan key:generate --show` en una terminal privada. Guárdala en Environment y en el gestor de secretos. |
| APP_URL | URL HTTPS del servicio o dominio propio. |
| DB_CONNECTION | `pgsql` por defecto en la imagen. |
| DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD | Copia los valores exactos de **Connect** en el proyecto Supabase existente. Prioriza conexión directa si la red admite IPv6. Si falla la ruta, usa **Session pooler** y sus propios host, usuario y puerto. |
| DB_SSLMODE, DB_SSLROOTCERT | La imagen usa `require`. Para validar servidor, configura `verify-full` con la CA correspondiente como Secret File de Render y su ruta en DB_SSLROOTCERT. |
| DB_URL | Opcional. Si usas los campos anteriores, deja esta variable ausente. Una URL puede prevalecer sobre esos campos. Para usarla, codifica correctamente caracteres reservados y revisa sus parámetros TLS. |
| GEMINI_API_KEY, GEMINI_MODEL | Clave privada y modelo habilitado en Google. La API solo los usa desde el servidor. |
| SESSION_DRIVER, SESSION_SECURE_COOKIE | `database` y `true`. La API invitada autentica con `X-Guest-Id` y Bearer. Flutter guarda ambos en almacenamiento seguro. |
| CACHE_STORE, QUEUE_CONNECTION | `database` conserva el rate limiting entre reinicios; `sync` ejecuta los trabajos en la petición. |
| LOG_CHANNEL, LOG_LEVEL | `stderr`, `warning`. Apache escribe método, ruta sin query, estado y duración en stdout. Evita registrar cuerpos y cabeceras de autorización. |
| PORT, RENDER | Render suministra ambos. RENDER activa en Laravel la confianza en protocolo e IP reenviados por el ingreso de Render. Usa esa opción exclusivamente detrás del proxy de la plataforma. |
| CORS_ALLOWED_ORIGINS | Para Flutter nativo puede quedar vacío. Para Flutter Web, lista de orígenes exactos separados por comas, incluido esquema y puerto cuando corresponda. El cliente envía credenciales invitadas en cabeceras. |
| Zona horaria | Laravel y la conexión PostgreSQL usan UTC; cada perfil conserva su zona para fechas locales. El proyecto no lee APP_TIMEZONE. |

Los directorios escribibles de Laravel contienen cachés regenerables, vistas y temporales. El arranque valida APP_KEY y reconstruye las cachés de configuración y rutas **después** de recibir los secretos. El contexto Docker excluye .env, vendor local, logs, cachés locales y documentos. Conserva en Render los secretos estables y renuévalos en su panel. [Conexiones PostgreSQL de Supabase](https://supabase.com/docs/guides/database/connecting-to-postgres)

## Verificación desde el cliente

1. Abre `https://TU-SERVICIO.onrender.com/health`. Debe devolver HTTP 200 y `{"status":"ok","service":"fit-home-api"}`. La sonda comprueba que Laravel atiende HTTP incluso cuando las dependencias externas están indisponibles.
2. Comprueba `GET /api/v1/equipment` sin credenciales. El catálogo es privado. Con caché y PostgreSQL disponibles debe devolver 401.
3. Desde una terminal privada, prueba el alta real y la lectura del perfil. Este paso crea **un invitado** en Supabase; conserva sus credenciales para las comprobaciones posteriores. No uses salida verbose ni trazas del shell.

```bash
base=https://TU-SERVICIO.onrender.com/api/v1
guest=$(curl -fsS -H 'Content-Type: application/json' -d '{"name":"Prueba Render"}' "$base/guests")
auth=(-H "X-Guest-Id: $(jq -r .data.public_id <<<"$guest")" -H "Authorization: Bearer $(jq -r .data.token <<<"$guest")")
curl -fsS "${auth[@]}" "$base/profile"
curl -fsS "${auth[@]}" "$base/equipment"
```

4. Para comprobar Gemini con un consumo pequeño, solicita una interpretación autenticada. Revisa `data.items`. La interpretación no escribe porciones. Para probar generación de entrenamientos completa el onboarding y utiliza una request_key UUID estable según el [flujo de la API](api-v1.md).

```bash
curl -fsS "${auth[@]}" -H 'Content-Type: application/json' \
  -d '{"description":"Almorcé dos huevos","meal_type":"lunch"}' \
  "$base/ai/nutrition/analyze"
```

5. Tras un periodo natural de inactividad, abre el dashboard con **el mismo invitado**. Comprueba el arranque en frío, la persistencia del perfil y la reconciliación de fechas vencidas. La generación Gemini sigue siendo síncrona, con timeout de transporte de 45 segundos por intento y hasta dos intentos en generación de planes. Ante una desconexión, reutiliza la misma request_key.

## Prueba local de la imagen

Ejecuta esto con un daemon Docker accesible. Usa una clave efímera de prueba y caché en memoria para que el control de rutas privadas pueda funcionar sin base de datos. El PostgreSQL de producción y Gemini quedan fuera de este ensayo.

```bash
docker build -t fit-home-api .
docker run -d --name fit-home-smoke -p 127.0.0.1:18743:18743 \
  -e PORT=18743 -e CACHE_STORE=array -e DB_HOST=127.0.0.1 -e DB_PORT=1 \
  -e APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')" fit-home-api
curl -i http://127.0.0.1:18743/health
curl -i http://127.0.0.1:18743/api/v1/profile
curl -i http://127.0.0.1:18743/.env
docker exec fit-home-smoke php artisan route:list --path=api/v1
docker exec fit-home-smoke sh -c 'id && test -w storage && test -w bootstrap/cache && test ! -e .env && php -m'
docker logs fit-home-smoke
docker stop fit-home-smoke && docker rm fit-home-smoke
```

Resultados esperados: 200 JSON, 401 JSON, acceso denegado a .env, 49 rutas v1 y módulos pdo_pgsql, pgsql y OPcache. Para verificar el puerto predeterminado omite `-e PORT` y publica el puerto 10000 del contenedor.

## Operación y límites de Free

Render suspende el servicio tras 15 minutos sin tráfico entrante. El siguiente acceso puede tardar aproximadamente un minuto. El disco es efímero y las horas gratuitas son compartidas por workspace. Revisa en el panel las cuotas de tráfico y builds y los límites de gasto de la cuenta. Gemini y Supabase conservan sus propias cuotas. [Límites de Render Free](https://render.com/docs/free)

El contenedor ejecuta solo el servidor HTTP. Laravel conserva las tareas programadas `fit:reconcile` y `fit:recover-generations`; Free no garantiza ejecución por minuto. El dashboard, el inicio y la finalización de jornadas concilian vencimientos, y los reintentos de generación comprueban procesos interrumpidos. El procesamiento puntual en ausencia de usuarios requerirá un scheduler externo fiable. No envíes tráfico artificial para mantener despierta la instancia.

## Diagnóstico

| Síntoma | Comprobación |
| --- | --- |
| Build falla en Composer | Comprueba PHP 8.3 y composer.lock. La etapa build ejecuta check-platform-reqs sin dependencias dev. Revisa primero el nombre de la extensión o el fallo de red del registro. |
| Render espera puerto o devuelve 502 | Revisa PORT, comando Docker predeterminado y logs de Apache. Verifica que el health check apunta a /health. Durante el primer acceso espera el arranque en frío. |
| APP_KEY inválida | El entrypoint termina con un mensaje sin el valor. Corrige la clave estable en Environment y vuelve a desplegar. |
| /health 200 pero API devuelve 500 | Comprueba primero PostgreSQL, incluido el permiso del rol Laravel sobre cache, cache_locks, sesiones y tablas de aplicación. Revisa stderr con APP_DEBUG=false. |
| Conexión PostgreSQL o SSL falla | Verifica host, puerto, credenciales y DB_URL. Ante problemas IPv6 prueba el Session pooler. Si usas verify-full, verifica hostname y CA. |
| Configuración antigua o permisos | Vuelve a desplegar tras cambiar Environment. Comprueba que storage y bootstrap/cache pertenecen a www-data y que el build excluye las cachés de la máquina local. |
| Gemini tarda, devuelve 502 o 503 | Consulta la generación autenticada y su error_code; revisa permisos, modelo, cuota y red. Las peticiones de IA ocupan uno de los tres procesos mientras esperan. |
| Enlaces http o throttling inesperado | Verifica APP_URL HTTPS, RENDER y que la petición pasa por el proxy de Render. Comprueba también el acceso al caché compartido. |

El 14 de noviembre de 2026 la inspección de solo lectura en Supabase confirmó **12 migraciones aplicadas** y **43 tablas públicas con RLS habilitado**, propiedad del rol conectado. Los grants efectivos de SELECT para anon y authenticated permanecían en las 43; la lectura de filas depende además de sus políticas. anon conservaba TRUNCATE en las 43, operación ajena a RLS. Revisa las ACL de tablas, secuencias y privilegios predeterminados con el responsable de la base, y prepara la retirada de permisos innecesarios en una ventana autorizada. El despliegue del servidor no modifica permisos ni datos por sí mismo.
