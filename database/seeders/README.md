# Catálogo inicial de Fit Home

## Entrada y dependencias

Artisan invoca `DatabaseSeeder::run()`. La carga completa comparte una transacción. Los UUID se resuelven por slug en cada instalación.

| Orden | Seeder | Registros o relaciones |
| --- | --- | --- |
| 1 | EquipmentSeeder | Multigimnasio Evolution Fitness EVO 500 |
| 2 | EquipmentCapabilitiesSeeder | Press, mariposa, poleas alta y baja, extensión y apoyo predicador |
| 3 | MuscleGroupSeeder | Once grupos musculares |
| 4–5 | MachineExerciseSeeder y BodyweightExerciseSeeder | Seis ejercicios de máquina y veinte de entrenamiento en casa |
| 6 | ExerciseMuscleGroupSeeder | Un músculo principal y uno secundario por ejercicio, y alternativas cuando están definidas |
| 7 | ExerciseEquipmentSeeder | Equipo y capacidad necesarios para cada ejercicio de máquina |
| 8 | PenaltyRulesSeeder | Regla diaria FIFO con incremento nominal de cinco repeticiones |

## Invariantes y efectos

Los seeders crean los registros ausentes. Una segunda ejecución conserva UUID, cambios de catálogo y límites configurados. Las asociaciones se identifican por la pareja de claves externas. El seeder completa la alternativa de menor impacto cuando existe una alternativa en el catálogo y el campo está vacío.

Las capacidades proceden de la descripción facilitada. El curl predicador se carga inactivo hasta confirmar el accesorio. Las cargas efectivas, relaciones de poleas y enlaces multimedia requieren datos verificados.

## Verificación

La prueba de catálogo compara los UUID de los 26 ejercicios antes y después de repetir la carga y comprueba las asociaciones. [Pruebas de integración](../../tests/Feature/FitHomeTest.php)
