# Plan — Modalidades de entrenamiento Fit Home

Estado: backend completo y desplegado; app móvil publicada en staging (parche #7).
Validación en dispositivo Android: **pendiente** (no hay dispositivo conectado).

## Backend (Laravel)

1. [x] Migración aditiva `training_mode` (user_preferences / ai_generations / training_plans) + checks + backfill determinista.
2. [x] `TrainingMode` service (valores, resolución, filtro de catálogo, equipamiento implícito).
3. [x] `ExerciseCatalog::eligible()` acepta modalidad; modo bodyweight excluye máquina/equipamiento.
4. [x] `PreferenceRequest` + `ProfileEditor` + `GET /preferences` (valor por defecto `null`).
5. [x] `PlanGeneration`: exige modalidad, la guarda en `ai_generations`, la incluye en contexto/prompt y en recuperación.
6. [x] `GeneratedPlanImporter`: valida contra catálogo filtrado y persiste `training_plans.training_mode`.
7. [x] `WorkoutReplacement`: usa la modalidad del plan (fallback derivado del plan para planes antiguos).
8. [x] `CatalogController`: filtra por modalidad guardada (o `?training_mode=`).
9. [x] Tests Pest (18 pruebas / 25 escenarios) + rollback de migración.
10. [x] Pint, suite completa (61 pruebas), commits.

## Producción

11. [x] Conexión Supabase vía pooler IPv4 y respaldo lógico verificado con restauración completa.
12. [x] `migrate --force` en Supabase (proyecto `nuylkjqbndpghpnzlqdc`) + verificación de columnas/registros.
13. [x] Push API (`07407cd`) → Render → verificación real (health, preferencias, catálogo filtrado, plan antiguo intacto).

## Flutter

14. [x] Pantalla de selección de modalidad post-onboarding (Gate).
15. [x] Configuración → Entrenamiento → Modalidad (mostrar/cambiar).
16. [x] Guardas en generación de plan + indicador de modalidad.
17. [x] Invalidación de caché selectiva.
18. [x] Tests Flutter (formato, analyze, 63 pruebas).
19. [x] Commits + push main (`8f0724a`).

## OTA

20. [x] Tag `ota/staging/v1.0.0-p7` → GitHub Actions → parche Shorebird **#7** en staging (ARM64).
21. [ ] Validación real en Android — **pendiente**: no hay dispositivo conectado; no se publica stable.
22. [ ] Tag stable — bloqueado hasta completar y verificar la validación en Android.
