# Operación de Fit Home

Laravel 13 utiliza las tablas de aplicación en `public`. El backend conserva el identificador bigint de los usuarios existentes. Las nuevas entidades y el identificador público de invitado utilizan UUID. [Tablas, columnas y diagrama Mermaid](esquema.md)

## Configurar y revisar Supabase

1. Completa en el entorno del servidor las variables de [.env.example](../.env.example). En el panel **Connect** del proyecto existente copia el host, usuario, puerto y base exactos. Para migraciones utiliza conexión directa. Si solo tienes IPv4, usa los datos de **Session pooler**. [Conexiones de Supabase](https://supabase.com/docs/guides/database/connecting-to-postgres)
2. Utiliza `DB_SSLMODE=require` como mínimo. Con la CA del proyecto y un hostname verificable configura `DB_SSLMODE=verify-full` y `DB_SSLROOTCERT`. Comprueba también cualquier `DB_URL` ya configurada, porque puede prevalecer sobre los campos individuales. Las credenciales pertenecen al servidor Laravel.
3. Desde la raíz ejecuta la inspección siguiente. Comprueba que las cuatro migraciones originales figuren aplicadas y que las ocho de Fit Home estén pendientes antes del primer despliegue.

```sh
php artisan config:clear
php artisan migrate:status
php artisan migrate --pretend
```

Tras revisar y autorizar el despliegue, ejecuta en este orden:

```sh
php artisan migrate --force
php artisan db:seed --force
php artisan migrate:status
php artisan schedule:list
```

La primera migración protege las diez tablas originales de Laravel mediante RLS. Las siguientes adaptan usuarios invitados y crean el catálogo, los perfiles, los entrenamientos y el seguimiento. Cada tabla nueva activa RLS dentro de la transacción que la crea. Las siguientes migraciones protegen las instantáneas nutricionales y añaden cumplimiento de jornadas, claves de idempotencia y nutrientes desconocidos. El rol propietario de Laravel conserva acceso, y los clientes pasan por Laravel. Un rol de ejecución distinto del propietario necesita grants y políticas propios. Revisa en el panel si la Data API debe estar habilitada. [Seguridad de la Data API](https://supabase.com/docs/guides/api/securing-your-api)

El catálogo se puede cargar de nuevo. Revertir una migración de creación elimina los datos de esas tablas. La reversión del cambio de invitados requiere que los usuarios existentes tengan correo y contraseña para restablecer las restricciones originales. [Orden y contenido de los seeders](../database/seeders/README.md)

## Identidad, agenda y saldo

`GuestIdentity::issue` emite un secreto aleatorio de 256 bits. La tabla guarda su SHA-256. La autenticación requiere el UUID público y el secreto vigente. Flutter deberá guardar el secreto en almacenamiento seguro. El middleware de la API autentica la credencial y pasa el usuario a los servicios.

`WorkoutCalendar` convierte el día y la hora límite locales en un instante UTC y conserva la zona. Solo existe una jornada vigente por usuario y fecha. Una reprogramación guarda la sesión anterior, copia su prescripción y registra el motivo en la revisión del plan. Las jornadas de descanso se representan con `kind=rest`.

Configura el cron del servidor para ejecutar `php artisan schedule:run` cada minuto. El scheduler procesa las sesiones vencidas. Al reabrir la aplicación, el backend puede invocar `WorkoutLedger::reconcile($user)`. Para una reconciliación manual:

```sh
php artisan fit:reconcile
php artisan fit:reconcile --user=123
```

Las operaciones bloquean primero la fila del usuario. Cada omisión elegible crea una deuda. Completar una jornada resuelve la deuda anterior más antigua, genera un evento de constancia y registra una notificación. Los índices únicos restringen tanto la sesión de origen como la de resolución. El saldo es el número de filas pendientes.

El inicio del entrenamiento congela la adaptación de cada ejercicio. El valor nominal son cinco repeticiones por deuda y serie. El incremento efectivo queda limitado por el máximo de la regla, el máximo extra del usuario y el máximo absoluto de repeticiones del ejercicio para su nivel. Los valores iniciales dejan el incremento desactivado hasta configurar límites revisados. Una restricción física o recuperación suspende el ajuste. La aplicación conserva la prescripción, el saldo observado, el resultado y la razón del límite.

## Planes, alimentación y estadísticas

`GeneratedPlanImporter::import` recibe un usuario autenticado, una generación de tipo `training_plan` con `schema_version=1` y el JSON recibido. Valida claves admitidas, objetivo activo, fechas, disponibilidad, equipo, restricciones, nivel y límites. Persiste el plan y todas sus sesiones de forma atómica o registra el motivo de rechazo. Un reintento de la misma generación devuelve la decisión guardada. El importador acepta ejercicios por repeticiones y tiempo. El esquema de persistencia admite también distancias.

La disponibilidad semanal contiene números ISO del 1 (lunes) al 7. Configura la duración en `user_onboarding.session_minutes`, los límites por nivel en `exercises.rep_limits` y los límites de programación en `user_preferences.settings.plan_limits` (`max_sets`, `max_seconds_per_set` y `seconds_per_rep`). La prueba de importación incluye una carga JSON completa. El modelo y la versión del prompt se registran en la generación. El cliente HTTP de Gemini genera propuestas mediante el endpoint de generación. Consulta el [contrato HTTP](api-v1.md).

Los alimentos y porciones conservan su fuente y precisión decimal. Corrige una porción con una nueva fila cuyo `supersedes_id` apunta a la versión anterior de la misma comida. `NutritionTotals` suma únicamente las versiones vigentes del usuario. Las rachas se reconstruyen por fecha local desde los eventos y las jornadas de descanso del calendario. El cumplimiento semanal se calcula a partir de las sesiones y sus estados, y el peso y las medidas se consultan por fecha de medición.

## Reproducir las pruebas

Utiliza PostgreSQL local con una **base exclusiva y desechable llamada fit_home_test**, un usuario propietario, PHP con `pdo_pgsql` y las dependencias de desarrollo de Composer. El entorno de pruebas migra y siembra esa base, crea perfiles de prueba y conserva los registros entre ejecuciones. El nombre de la base es fijo. La prueba de concurrencia arranca dos procesos PHP y comprueba que ambos se bloquean en PostgreSQL antes de liberar la transacción.

```sh
FIT_TEST_HOST=127.0.0.1 FIT_TEST_PORT=5432 FIT_TEST_USER=postgres vendor/bin/pest
```

Configura `FIT_TEST_PASSWORD` en el entorno si tu instancia lo requiere. También se admite como host la ruta absoluta a un directorio de socket Unix. Cuando falta `FIT_TEST_HOST`, Pest marca explícitamente como omitidas las pruebas de integración.

## Verificación del 13 de noviembre de 2026

En PostgreSQL 16.15 pasaron 14 pruebas con 71 aserciones, incluidas dos pruebas originales de Laravel. Se ejecutó además el ciclo de las siete migraciones en una segunda base local. Tras revertirlas, las cuatro migraciones previas y los registros testigo en `auth` y `storage` permanecieron intactos. Las siete volvieron a aplicarse. Con grants simulados, el propietario leyó los 26 ejercicios y el rol cliente leyó cero.

En Supabase se inspeccionaron el inventario, el propietario y los permisos, y se generó la vista previa SQL. En ese momento las diez tablas existentes concedían SELECT a `anon` y tenían RLS desactivado. El efecto de los cambios en el proyecto alojado queda pendiente de aplicar las migraciones autorizadas.
