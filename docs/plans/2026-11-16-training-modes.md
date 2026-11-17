# Plan — Modalidades de entrenamiento Fit Home

Estado: en ejecución.

## Backend (Laravel)

1. [ ] Migración aditiva `training_mode` (user_preferences / ai_generations / training_plans) + checks + backfill determinista.
2. [ ] `TrainingMode` service (valores, resolución, filtro de catálogo, equipamiento implícito).
3. [ ] `ExerciseCatalog::eligible()` acepta modalidad; modo bodyweight excluye máquina/equipamiento.
4. [ ] `PreferenceRequest` + `ProfileEditor` + `GET /preferences` (valor por defecto `null`).
5. [ ] `PlanGeneration`: exige modalidad, la guarda en `ai_generations`, la incluye en contexto/prompt y en recuperación.
6. [ ] `GeneratedPlanImporter`: valida contra catálogo filtrado y persiste `training_plans.training_mode`.
7. [ ] `WorkoutReplacement`: usa la modalidad del plan (fallback derivado del plan para planes antiguos).
8. [ ] `CatalogController`: filtra por modalidad guardada (o `?training_mode=`).
9. [ ] Tests Pest (25 escenarios) + rollback de migración.
10. [ ] Pint, suite completa, commits.

## Producción

11. [ ] Conexión Supabase (pooler IPv4) verificada y respaldo lógico previo.
12. [ ] `migrate --force` en Supabase + verificación de columnas/registros.
13. [ ] Push API → Render → verificación real (health + lectura/escritura + catálogo filtrado).

## Flutter

14. [ ] Pantalla de selección de modalidad post-onboarding (Gate).
15. [ ] Configuración → Entrenamiento → Modalidad (mostrar/cambiar).
16. [ ] Guardas en generación de plan + indicador de modalidad.
17. [ ] Invalidación de caché selectiva.
18. [ ] Tests Flutter (formato, analyze, test).
19. [ ] Commits + push main.

## OTA

20. [ ] Tag staging → GitHub Actions → parche Shorebird staging.
21. [ ] Validación real en Android (o dejar pendiente explícito).
22. [ ] Tag stable solo con validación real.
