# Modalidades de entrenamiento (máquina / casa)

## Valores

| Valor | Significado |
| --- | --- |
| `machine` | Entrena con la máquina multiestación EVO 500 y complementos compatibles con el equipamiento disponible. |
| `bodyweight` | Entrena en casa sin máquinas, pesas, mancuernas, bancos ni equipamiento obligatorio. |

Dificultad (`easy` / `intermediate` / `hard`) y modalidad son preferencias **independientes**.
Cualquier combinación es válida y el backend no limita la dificultad según la modalidad.

## Persistencia

- `user_preferences.training_mode` — preferencia vigente del usuario/invitado (nullable).
- `ai_generations.training_mode` — modalidad con la que se solicitó un plan (snapshot).
- `training_plans.training_mode` — modalidad con la que se generó el plan (snapshot).

Las tres columnas son `varchar` nullable con `CHECK (training_mode IN ('machine','bodyweight'))`
en PostgreSQL. `null` significa «todavía no elegida» (perfiles anteriores a la funcionalidad).

Reglas:

- Cambiar la modalidad **no** modifica planes, sesiones, series, estadísticas, penalizaciones
  ni dificultad. Solo afecta a los planes generados después.
- Cada plan conserva su modalidad; los reemplazos usan la modalidad del plan, no la preferencia actual.
- Los clientes anteriores siguen funcionando: `training_mode` es un campo adicional y los campos
  existentes no cambian.

## Migración y compatibilidad con perfiles existentes

Migración aditiva `2026_11_18_000000_add_training_mode`:

1. Añade las columnas y sus `CHECK`.
2. Deriva la modalidad de los perfiles existentes solo cuando es inequívoca:
   - tiene equipamiento `multigym` (EVO 500) → `machine`;
   - no tiene ningún equipamiento → `bodyweight`;
   - cualquier otro caso queda `null` y Flutter pide la selección una única vez.
3. No elimina ni reescribe filas, UUID, ilustraciones, planes, sesiones ni claves foráneas.

Los planes y generaciones anteriores quedan con `training_mode = null`. Para ellos
`TrainingMode::forPlan()` deriva la modalidad de los ejercicios del plan
(`machine` si contiene algún ejercicio de categoría `machine`; `bodyweight` en caso contrario).

`down()` elimina los `CHECK` y las columnas; el resto del esquema y los datos permanecen.

## Catálogo

La clasificación existente se reutiliza sin duplicarla:

- `exercises.category` (`machine`, `bodyweight`, `cardio`);
- `exercise_equipment` + `equipment.category = multigym` para la EVO 500;
- `exercises.active`.

Regla de compatibilidad (`TrainingMode::scope`):

- `bodyweight`: excluye `category = machine` y cualquier ejercicio con equipamiento requerido.
- `machine`: sin restricción adicional; los ejercicios de máquina siguen exigiendo que el usuario
  tenga el equipamiento activo (regla previa de `ExerciseCatalog`).

`curl-predicador` permanece inactivo: no se activa ni se usa en planes.

## Selección y equipamiento

Al guardar `training_mode = machine` con un perfil sin equipamiento, el backend asocia la
EVO 500 activa (`TrainingMode::attachMachine`). Es idempotente y no altera perfiles que ya
tienen equipamiento.

## Generación con Gemini

`PlanGeneration` exige una modalidad guardada; si falta responde `422` con
`errors.training_mode` y no llama a Gemini.

El contexto enviado incluye `training_mode` y solo los ejercicios elegibles para esa modalidad.
El prompt pide: con `machine`, priorizar las estaciones de la EVO 500 y complementos compatibles;
con `bodyweight`, usar solo ejercicios sin máquina, pesas, mancuernas, banco ni accesorio.

Validación en backend (`GeneratedPlanImporter`, no solo prompt): cada ejercicio debe existir,
estar activo, estar en el catálogo elegible de la modalidad, respetar límites de dificultad,
restricciones del usuario, prescripción y duración. Un ejercicio incompatible invalida el plan
completo (`validation_status = rejected`, `generation.status = invalid`, `error_code = exercise`)
y no se persiste ningún plan ni sesión.

La recuperación ante fallos de Gemini solo reutiliza planes anteriores con la misma modalidad
(fallback a `training_mode IS NULL` para generaciones antiguas).

## Reemplazos

`WorkoutReplacement` obtiene la modalidad del plan (`TrainingMode::forPlan`) y limita los
candidatos a ese catálogo. Se mantienen las reglas existentes: máximo tres reemplazos por sesión,
solo sesiones en curso, sin series registradas, sin duplicados, sin consumir cupo si no hay
alternativa y sin alterar dificultad, progreso ni penalizaciones.

## Endpoints

| Endpoint | Cambio |
| --- | --- |
| `GET /api/v1/preferences` | Incluye `training_mode` (`null` si no se ha elegido). |
| `PUT /api/v1/preferences` | Acepta `training_mode: machine\|bodyweight`; valida y persiste. |
| `GET /api/v1/exercises/eligible` | Filtra por la modalidad guardada; admite `?training_mode=` validado. |
| `GET /api/v1/exercises` | Sin cambios; admite `training_mode` solo como filtro de consulta. |
| `POST /api/v1/ai/training-plans/generate` | Exige modalidad; la guarda en generación y plan. |
| `GET /api/v1/training-plans[/current\|/{id}]` | Expone `training_mode` del plan. |
| `POST /api/v1/workouts/{id}/exercises/{assignment}/replace` | Sustitutos limitados a la modalidad del plan. |

## Pruebas

`tests/Feature/TrainingModeTest.php` (Pest + PostgreSQL local, sin consumir Gemini real):

- modalidad ausente, selección, persistencia, cambio y valores inválidos;
- aislamiento entre invitados;
- filtrado del catálogo por modalidad y exclusión de inactivos;
- generación con máquina y en casa con `easy`/`intermediate`/`hard`;
- rechazo de ejercicios incompatibles;
- reemplazos dentro de la modalidad y sin consumo de cupo cuando no hay sustituto;
- conservación de dificultad, progreso, penalizaciones y sesión activa;
- planes antiguos sin modalidad;
- contrato JSON;
- migración aplicada y revertida conservando registros.

Ejecución local:

```bash
FIT_TEST_HOST=127.0.0.1 FIT_TEST_PORT=5432 FIT_TEST_USER=postgres \
  php artisan test
```

## Despliegue y recuperación

1. `php artisan test`, `./vendor/bin/pint --dirty`.
2. Migración en Supabase con `php artisan migrate --force` usando una conexión válida
   (si la conexión directa es solo IPv6, usar el pooler IPv4 de Supabase con el usuario
   `postgres.<project-ref>`), tras verificar proyecto, esquema y copia de seguridad.
3. Verificar columnas, valores derivados y que planes/sesiones anteriores siguen intactos.
4. Push a `main` del repositorio de la API y despliegue en Render.
5. Comprobar `/health`, lectura/escritura de `preferences`, catálogo filtrado y planes antiguos.

Ante un fallo: no ejecutar `migrate:fresh`, `db:wipe` ni borrados masivos. Si la migración debe
revertirse, `php artisan migrate:rollback --step=1` elimina solo las columnas y sus `CHECK`.
