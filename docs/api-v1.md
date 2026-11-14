# API REST de Fit Home

La API usa JSON y el prefijo `/api/v1`. Envía `Accept: application/json` y `Content-Type: application/json`. Para todas las operaciones salvo crear invitado, envía `X-Guest-Id: <public_id>` y `Authorization: Bearer <token>` por HTTPS. Guarda ambos en almacenamiento seguro. Si se pierden, el perfil puede quedar irrecuperable. Rota antes de los 90 días y sustituye el secreto local tras recibir el nuevo. La rotación invalida el anterior.

Una respuesta de consulta tiene esta forma:

```json
{"success":true,"message":"Operación realizada correctamente","data":{"completed_sets":1,"planned_sets":2,"required_percent":100,"fulfilled":false}}
```

Los listados paginados aceptan `?page=2`. Sus filas están en `data.data`, con `current_page`, `last_page` y `total` en el mismo paginador. Los decimales de PostgreSQL pueden llegar como cadenas. Una consulta individual opcional devuelve `data:null` cuando aún no existe un registro.

| Código | Cuándo se devuelve |
| --- | --- |
| 200 / 201 | Consulta o cambio correcto / nuevo recurso |
| 202 | Otra solicitud con la misma clave está procesando la generación |
| 401 / 403 | Credenciales inválidas, revocadas o vencidas / acceso denegado |
| 404 | Identificador desconocido o privado de otro invitado |
| 409 | Estado incompatible, fecha ocupada, corrección repetida o serie existente con otros datos |
| 422 | Entrada inválida, cuestionario incompleto o propuesta de plan rechazada |
| 429 | Límite de peticiones alcanzado. Respeta Retry-After |
| 502 / 503 | Respuesta incompleta del proveedor / configuración, cuota o transporte |
| 500 | Error interno con mensaje genérico |

```json
{"success":false,"message":"Revisa los datos enviados.","errors":{"weight_kg":["The weight kg field must be between 1 and 500."]}}
```

## Operaciones

Las rutas siguientes son relativas al prefijo. Los parámetros de ruta son UUID. Las solicitudes de escritura aceptan únicamente los campos validados. Los campos de propiedad se derivan de las credenciales.

| Método y ruta | Entrada | Contenido de data |
| --- | --- | --- |
| POST /guests | Objeto vacío o campos de perfil | public_id y token, 201 |
| POST /guests/rotate-token | Objeto vacío | public_id y nuevo token |
| GET /profile | — | Nombre, perfil, metas y equipo |
| PUT /profile | Campos parciales de perfil | Perfil actualizado |
| GET /onboarding | — | Paso, disponibilidad y finalización |
| POST /onboarding | Campos parciales de perfil y step o complete | Estado acumulado del cuestionario |
| GET /preferences | — | Preferencias o null |
| PUT /preferences | units, recovery, daily_cutoff, exercise_preferences o penalty_adjustments | Preferencias guardadas |
| GET /equipment | page | Equipo con capacidades |
| PUT /profile/equipment | equipment_ids (lista de UUID, admite vacía) | Equipo disponible |
| GET /exercises | Filtros de catálogo | Ejercicios activos paginados con músculos y equipo |
| GET /exercises/eligible | Filtros de catálogo | Ejercicios compatibles |
| GET /exercises/{id} | — | Ficha activa con alternativa |
| GET /training-plans | page | Planes y objetivo |
| GET /training-plans/current | — | Plan activo vigente o null |
| GET /training-plans/{id} | — | Plan con objetivo y sesiones |
| GET /workouts/today | — | Jornada en fecha local o null |
| GET /workouts/week | — | Jornadas de lunes a domingo |
| GET /workouts/{id} | — | Sesión, ejercicios, instrucciones, ajustes y series |
| POST /workouts/{id}/start | Objeto vacío | Sesión iniciada y prescripción |
| POST /workouts/{id}/sets | Resultado de serie | Serie creada (201) o existente idéntica (200) |
| PUT /workouts/{id}/sets/{setId} | Resultado con el mismo ejercicio y número | Serie actualizada |
| POST /workouts/{id}/complete | Objeto vacío | Sesión completada |
| GET /workouts/{id}/progress | — | Series cumplidas, prescritas, porcentaje exigido y fulfilled |
| POST /workouts/{id}/reschedule | date (YYYY-MM-DD) y reason | Nueva sesión vinculada a la anterior |
| GET /penalties | page | Deudas pendientes |
| GET /penalties/history | page | Deudas con fechas de activación y resolución |
| GET /penalties/summary | page | Saldo, regla, próxima sesión y ajustes con prescripción original y aplicada |
| GET /dashboard | — | Objetivo, peso, jornada local, próxima sesión, cumplimiento, racha, saldo, nutrición y recordatorio |
| GET /foods | page | Referencias nutricionales paginadas |
| GET /meals | page | Comidas con sus porciones y versiones |
| POST /meals | kind, eaten_at y notes opcional | Comida creada |
| GET /meals/{id} | — | Comida, versiones y totales vigentes |
| POST /meals/{id}/items | Porción | Instantánea creada |
| POST /meals/{id}/items/{itemId}/correct | Nueva porción completa | Nueva versión con supersedes_id |
| GET /nutrition/today | — | kcal, protein_g, carbs_g y fat_g |
| GET /nutrition/history | page | Totales por fecha local con porciones registradas |
| POST /weight-logs | weight_kg y measured_at | Medición creada |
| GET /weight-logs | page | Historial por fecha descendente |
| GET /statistics/overview | — | Resumen del dashboard |
| GET /statistics/weight | — | Primera medición, última y cambio en kg |
| GET /statistics/workouts | — | Jornadas completadas, programadas y porcentaje semanal |
| GET /statistics/streaks | page | Racha de jornadas y eventos paginados |
| GET /notifications/preferences | — | Preferencias por recordatorio |
| PUT /notifications/preferences | kind, local_time (HH:mm), timezone y enabled | Lista actualizada |
| GET /notifications | page | Eventos con canal, hora y estado |
| POST /ai/training-plans/generate | request_key (UUID estable para cada intento lógico) | Generación con recomendación, plan y jornadas |
| POST /ai/nutrition/analyze | description y meal_type opcional | Interpretación, referencias, incertidumbre y propuesta de confirmación |
| GET /ai/generations/{id} | — | Estado, uso, errores y planes de la generación |

**Perfil.** Usa name, last_name, birth_date o age_years, weight_kg (1–500), height_cm (40–260), timezone IANA, level (beginner, intermediate, advanced), goal (fat_loss, muscle_gain, strength_cardio), weekly_availability (enteros ISO distintos de 1 a 7), session_minutes (5–180), equipment_ids y exercise_preferences. El cuestionario conserva los campos omitidos. Para finalizar envía complete=true después de registrar edad o nacimiento, peso, estatura, objetivo, días y duración. Los límites de entrada son controles de calidad de datos. Las unidades de almacenamiento son kg y cm incluso si la preferencia de presentación es imperial.

**Catálogo y series.** Filtra por muscle_group (slug), category, level, measurement, equipment_id y eligible=1. Usa workout_exercise_id de la prescripción de esa sesión al registrar resultados. Envía set_number y una sola medida entre reps, duration_seconds y distance_m, más load_kg y perceived_effort (1–10) opcionales. El servidor fecha el registro. Se exige por defecto el 100 % de las series con la cantidad prescrita. El backend puede ajustar completion_percent por jornada entre 1 y 100. El resultado parcial queda registrado al vencer la jornada. Las series declaradas son autoinforme.

**Alimentación.** kind admite breakfast, lunch, dinner y snack. Envía timestamps ISO 8601 con offset. Para una porción indica quantity positiva y unit. Con food_item_id usa la unidad de referencia del catálogo. Para un alimento desconocido envía name. Un nutriente desconocido produce null en su total. Sin porciones, el total es cero. Las referencias se cargan cuando se disponga de una fuente. La corrección añade una versión.

**Agenda y límites.** Al abrir la aplicación consulta el dashboard para conciliar vencimientos. El scheduler también reconcilia cada minuto. Las preferencias de recordatorio admiten breakfast, lunch, dinner y training. Flutter consume la hora local y zona para programar sus alertas. El incremento físico por deuda empieza desactivado. Un administrador puede ejecutar `php artisan fit:penalty-limits --enabled=1 --max-extra=N` con un máximo previamente revisado, además del tope individual `user_preferences.max_extra_reps` y los límites por ejercicio. Configura la política inicial en `config/training.php`. La prescripción registra procedencia y vigencia. Son valores operativos revisables, sin certificación clínica.

**Gemini.** Configura GEMINI_API_KEY y GEMINI_MODEL en el servidor con un modelo habilitado en tu cuenta que admita salida estructurada. El backend envía el objetivo y el catálogo filtrado. Guarda la clave de idempotencia antes de solicitar un plan. Reutilízala al reintentar o tras perder la respuesta. Una nueva generación requiere una clave nueva y ausencia de un plan activo vigente o futuro. Una propuesta inválida conserva errors.generation_id y errors.reason. Una generación fallida requiere una clave nueva para otro intento. La cuota de generación es 5/minuto. El análisis de comidas tiene su propia cuota de 5 solicitudes por minuto. [Referencia oficial de generateContent](https://ai.google.dev/api/generate-content)

## Flujo HTTP reproducible

Con las migraciones y seeders aplicados a desarrollo, ejecuta `php artisan serve` y utiliza Bash, curl y jq. Para el paso de IA hacen falta credenciales válidas. Los comandos de entrenamiento se ejecutan en un día programado antes de su hora límite.

```bash
set -euo pipefail
base=http://127.0.0.1:8000/api/v1
guest=$(curl -fsS -H 'Content-Type: application/json' -d '{"name":"Ana"}' "$base/guests")
auth=(-H "X-Guest-Id: $(jq -r .data.public_id <<<"$guest")" -H "Authorization: Bearer $(jq -r .data.token <<<"$guest")")
api() { curl -fsS "${auth[@]}" -H 'Content-Type: application/json' "$@"; }

api -d '{"weight_kg":68,"height_cm":168,"age_years":29,"level":"beginner","timezone":"America/Bogota","goal":"strength_cardio","weekly_availability":[1,2,3,4,5,6,7],"session_minutes":20,"complete":true}' "$base/onboarding"
machine=$(api "$base/equipment" | jq -r '.data.data[] | select(.slug=="evo-500").id')
api -X PUT -d "$(jq -nc --arg m "$machine" '{equipment_ids:[$m]}')" "$base/profile/equipment"
key=$(php -r 'require "vendor/autoload.php"; echo Illuminate\Support\Str::uuid();')
api -d "$(jq -nc --arg k "$key" '{request_key:$k}')" "$base/ai/training-plans/generate"
api "$base/dashboard"
session=$(api "$base/workouts/today" | jq -er '.data.id')
workout=$(api -d '{}' "$base/workouts/$session/start")

# Tras realizar cada serie, sustituye el resultado por la cantidad realizada.
# Este ejemplo utiliza el objetivo de la serie para ilustrar la estructura.
jq -c '.data.workout_exercises[] as $e | range(1;$e.sets+1) as $n |
  {workout_exercise_id:$e.id,set_number:$n} +
  (if $e.base_reps then {reps:($e.workout_penalty_application.applied.base_reps // $e.base_reps)}
   else {duration_seconds:$e.duration_seconds} end)' <<<"$workout" |
while read -r outcome; do api -d "$outcome" "$base/workouts/$session/sets"; done
api -d '{}' "$base/workouts/$session/complete"
api "$base/penalties/summary"
meal=$(api -d "$(jq -nc --arg ts "$(date -Iseconds)" '{kind:"lunch",eaten_at:$ts}')" "$base/meals" | jq -r .data.id)
api -d '{"name":"Arroz","quantity":150,"unit":"g"}' "$base/meals/$meal/items"
api "$base/statistics/overview"
```

## Consumo desde Flutter

Con `package:http/http.dart` y las credenciales leídas del almacenamiento seguro:

```dart
Future<Map<String, dynamic>> dashboard(Uri base, String id, String token) async {
  final response = await http.get(base.resolve('dashboard'), headers: {
    'Accept': 'application/json',
    'X-Guest-Id': id,
    'Authorization': 'Bearer $token',
  });
  final body = jsonDecode(response.body) as Map<String, dynamic>;
  if (body['success'] != true) throw StateError(body['message'] as String);
  return body['data'] as Map<String, dynamic>;
}
```

Usa una base terminada en `/api/v1/`. Para crear, modificar o renovar envía `Content-Type: application/json` y `jsonEncode(payload)`. Nunca registres los encabezados de autorización ni el cuerpo de respuesta de creación o rotación.

## Verificación y despliegue

El 13 de noviembre de 2026, `php artisan test` ejecutó 28 pruebas con 178 aserciones sobre PostgreSQL 16.15 local. La suite incluye contención entre procesos, autenticación HTTP y generación con respuestas simuladas, incluida una pérdida de conexión. Se verificaron 48 operaciones v1 y la ruta previa de Sanctum. Para reproducir las pruebas configura FIT_TEST_HOST, FIT_TEST_USER y FIT_TEST_PORT según la [guía de PostgreSQL](fit-home.md).

La inspección de producción encontró cuatro migraciones originales aplicadas y ocho migraciones Fit Home pendientes. En las diez tablas públicas existentes, tanto anon como authenticated tenían SELECT con RLS desactivado. Si la Data API expone public, pueden quedar accesibles a clientes. La migración protectora habilita RLS y restringe estos roles en las tablas Laravel. El rol de Laravel inspeccionado es propietario. La verificación remota fue de solo lectura. Revisa `php artisan migrate --pretend` antes del despliegue autorizado. El rollback de la nueva migración requiere resolver antes cualquier instantánea con nutrientes desconocidos para restablecer NOT NULL.

Para listar los archivos exactos de esta etapa usa `git diff --name-status d3cfe44..HEAD` desde la raíz.

## Interpretar y confirmar una comida

Envía a `POST /api/v1/ai/nutrition/analyze` las mismas credenciales de invitado que al registrar una comida. Solo la descripción y las referencias de alimentos se envían a Gemini. El campo opcional meal_type admite breakfast, lunch, dinner o snack y tiene prioridad sobre el tipo interpretado. Envía como máximo 1000 caracteres.

```json
{"description":"Almorcé dos huevos","meal_type":"lunch"}
```

Ejemplo de respuesta cuando falta la preparación y el peso comestible:

```json
{
  "success": true,
  "message": "Operación realizada correctamente",
  "data": {
    "meal_type": "lunch",
    "requires_confirmation": true,
    "items": [{
      "name": "Huevos", "quantity": 2, "unit": "unidad", "grams": null,
      "missing": ["Aclarar alimento y preparación para seleccionar una referencia.","Confirmar la cantidad comestible en gramos."],
      "reference": null, "uncertain": true, "confirmation": null,
      "nutrients": {"kcal":null,"protein_g":null,"carbs_g":null,"fat_g":null}
    }]
  }
}
```

Gemini puede describir la preparación y señalar otras ambigüedades. Laravel admite conversiones exactas de kg a g, consulta referencias verificadas y calcula los nutrientes. Para una coincidencia confirmable devuelve la referencia con su fuente y un objeto confirmation con food_item_id, quantity y unit. Flutter debe mostrar el resultado para que la persona revise porciones, preparación y unidades. Solo tras su confirmación crea la comida y envía las porciones al endpoint existente de items. El dashboard consulta los totales del diario confirmado. Los errores del análisis mantienen el formato de la API, con 401 para credenciales, 422 para entradas, 429 para límite local, 502 para respuesta inválida y 503 para indisponibilidad del proveedor.
