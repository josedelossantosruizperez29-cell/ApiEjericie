# Ilustraciones de Fit Home

## Catálogo local y dirección visual

Los seeders definen 20 movimientos domésticos y 6 de la multiestación EVO-500. El curl predicador está inactivo. Cada fila contiene el slug estable y el prompt de movimiento que se añade al siguiente encargo visual.

Render fitness 3D de un hombre adulto atlético, con camiseta azul grisácea, pantalón corto gris oscuro y zapatillas grises, encuadre de cuerpo completo y apoyos visibles, fondo neutro claro e iluminación suave. Composición horizontal 4:3, sin texto ni marcas. Elige una vista que muestre los apoyos y las articulaciones relevantes. Para movimientos que se distinguen por dirección o fase aérea, muestra dos fases en la misma lámina. En máquina, utiliza una referencia visual de la estación y accesorio reales antes de generar. Revisa articulaciones, contacto con suelo o equipo, dirección y fidelidad al ejercicio antes de aceptar cada archivo.

| Slug | Movimiento y criterio visual | Estado local |
| --- | --- | --- |
| flexion | Flexiones de pecho. Apoya manos y pies, desciende en bloque y empuja. Evita hundir la zona lumbar. | Revisada |
| flexion-rodillas | Flexiones con rodillas apoyadas. Apoya manos y rodillas y acerca el pecho al suelo con el tronco alineado. Acolcha las rodillas si lo necesitas. | Revisada |
| flexion-inclinada | Flexiones inclinadas. Apoya las manos en una superficie fija y flexiona y extiende los codos. Comprueba que el apoyo no se deslice. | Revisada |
| sentadilla | Sentadillas con peso corporal. Lleva la cadera atrás y abajo y vuelve a ponerte de pie. Mantén los talones apoyados. | Revisada |
| zancada | Zancadas. Avanza un pie, flexiona ambas rodillas y vuelve al inicio. Controla el equilibrio antes de alternar. | Revisada |
| zancada-atras | Zancadas hacia atrás. Retrocede un pie, desciende y empuja con la pierna adelantada. Utiliza un apoyo estable si pierdes equilibrio. | Revisada |
| puente | Puente de glúteos. Boca arriba con rodillas flexionadas, eleva la pelvis y baja despacio. Evita arquear la espalda. | Revisada |
| talones | Elevaciones de talones. Eleva y baja los talones manteniendo las rodillas estables. Usa un apoyo si falta equilibrio. | Revisada |
| plancha | Plancha abdominal. Apoya antebrazos y puntas de los pies y mantén el cuerpo alineado. Respira y termina cuando pierdas la alineación. | Revisada |
| plancha-lateral | Plancha lateral. Coloca el codo bajo el hombro y eleva la cadera manteniendo la línea corporal. Acorta la palanca apoyando las rodillas si es necesario. | Revisada |
| dead-bug | Abdominal dead bug. Boca arriba, extiende brazo y pierna opuestos, vuelve y alterna. Reduce el recorrido si se arquea la zona lumbar. | Revisada |
| bird-dog | Bird dog en cuadrupedia. Desde cuatro apoyos extiende brazo y pierna opuestos y alterna. Mantén la pelvis nivelada. | Revisada |
| jumping-jacks | Saltos de tijera. Abre las piernas al saltar mientras elevas los brazos y vuelve al centro. Aterriza con suavidad. | Revisada |
| salto-estrella | Saltos de estrella. Desde semiflexión salta abriendo brazos y piernas y recógelos antes de aterrizar. Requiere tolerancia al impacto y control de la recepción. | Revisada |
| escaladores | Escaladores. Desde apoyo de manos lleva una rodilla al pecho y alterna. Reduce el ritmo si pierde estabilidad el tronco. | Revisada |
| rodillas-altas | Rodillas altas en el sitio. Alterna las piernas elevando las rodillas a una altura controlada. Adapta la altura y evita impactos bruscos. | Revisada |
| marcha | Marcha rápida en el lugar. Eleva los pies de forma alterna acompañando con los brazos. Mantén libre el espacio a tu alrededor. | Revisada |
| burpee | Burpees. Flexiona las piernas, apoya las manos, pasa a plancha, recoge los pies e incorpórate con un salto. Solo con capacidad física y tolerancia al impacto adecuadas. | Revisada |
| sentadilla-salto | Sentadillas con salto. Flexiona las piernas, salta verticalmente y amortigua la caída. Requiere control de rodillas y aterrizaje. | Revisada |
| jacks-sin-salto | Saltos de tijera sin salto (pasos laterales). Da un paso lateral mientras elevas los brazos, vuelve y alterna. Ajusta la amplitud del paso a tu equilibrio. | Revisada |
| press-sentado | Press de pecho sentado. Sitúa las empuñaduras a la altura del pecho, empuja y regresa con control. Mantén la espalda apoyada. | Revisada |
| mariposa | Aperturas de pecho con mariposa. Apoya los brazos, acércalos delante del pecho y vuelve despacio. Evita abrir los brazos más allá de un rango cómodo. | Revisada |
| jalon | Jalón al pecho en polea alta. Sujeta la barra, lleva los codos hacia abajo y devuelve la carga lentamente. Lleva la barra delante de la cabeza. | Revisada |
| remo | Remo sentado en polea baja. Siéntate frente a la polea y acerca el agarre al tronco manteniendo la espalda estable. Evita balancear el tronco. | Revisada |
| extension-piernas | Extensión de piernas. Alinea las rodillas con el eje, extiende las piernas y baja despacio. Ajusta el rodillo y evita golpear el tope. | Revisada |
| curl-predicador | Curl de bíceps con apoyo predicador. Apoya los brazos y flexiona los codos manteniendo los hombros estables. Verifica el accesorio de carga antes de utilizarlo. | Revisada · ejercicio inactivo |

Los 26 WebP revisados se enumeran con su SHA-256 y tamaño en el [inventario de carga](inventario.csv). La colección masculina usa la versión 2. La muestra de sentadilla de la entrega anterior se conserva aparte como referencia histórica. Los archivos de imagen se entregan fuera del árbol de Git. [Fotografía de referencia del multigimnasio](https://evolutionfitness.co/wp-content/uploads/2022/08/EVO-500.jpg)

La revisión visual contrasta las posturas con las instrucciones y, para la multiestación, la geometría y el accesorio con la referencia. El ejercicio predicador conserva su estado inactivo. La disponibilidad y las cuotas del bucket de producción requieren verificación con acceso al proyecto.

## Candidatas descartadas

| Ejercicio | Motivo | Selección |
| --- | --- | --- |
| Mariposa | En la primera composición cambiaba el contacto del antebrazo con el rodillo entre las fases. | Se eligió una toma frontal con ambos antebrazos apoyados. |
| Dead bug | En la primera composición la cabeza aparecía elevada. La segunda vista lateral dificultaba identificar los lados. | Se eligió la vista cenital que permite comprobar el patrón contralateral. |
| Marcha | Las dos figuras repetían la misma pierna. | Se pidió una vista frontal con alternancia de apoyos. |
| Rodillas altas | Las dos fases repetían la misma rodilla. | Se pidió alternancia en vista frontal. |

## Preparar y comprobar Supabase Storage

1. En el panel de la organización, consultar plan, uso del ciclo, almacenamiento disponible, transferencia y transferencia en caché. Como referencia, Free incluye 1 GB de Storage y 5 GB de egress. Para 26 objetos con un máximo de 512 KiB cada uno, reservar hasta 13 MiB por versión completa. Estimar transferencia con el peso efectivo y las descargas mensuales. [Cuotas oficiales](https://supabase.com/docs/guides/platform/billing-on-supabase)
2. En un proyecto de pruebas, crear el bucket público `exercise-illustrations` en Storage, restringido a `image/webp`, con máximo de 524288 bytes por objeto. Usar el panel administrativo para la carga. Revisar las políticas de `storage.objects` para los roles anon, authenticated y public, incluidas las políticas generales que pudieran permitir escrituras en todos los buckets. La lectura pública de objetos es compatible con restringir su modificación. [Acceso a buckets](https://supabase.com/docs/guides/storage/buckets/fundamentals)
3. En pruebas, cargar un archivo de control como administrador. Verificar que GET público devuelve 200 sin token y que una carga, reemplazo y eliminación de ese archivo son rechazados con las credenciales públicas y de usuario ordinario. Comprobar que el contenido permanece intacto. Borrar el objeto de control desde el panel. Si hay políticas generales que conceden escritura, revisar su alcance antes de habilitar el bucket.
4. Subir los WebP aprobados bajo `exercises/<slug>-v<N>.webp`, conservando versiones anteriores. Usar un TTL largo para objetos inmutables. La aplicación obtiene el WebP original vía CDN. Copiar del panel la base pública hasta el nombre del bucket en `EXERCISE_ILLUSTRATIONS_BASE_URL` del backend. Las claves administrativas permanecen fuera de Flutter.
5. Con la migración aplicada al entorno de pruebas, ejecutar `php artisan fit:exercise-image sentadilla 2 --id=UUID_DEL_ENTORNO --sha256=HUELLA_DEL_INVENTARIO`. Comprueba el objeto real por HTTPS, su tipo y dimensiones. Para asignarlo, ejecutar la misma orden con `--apply`. Confirmar la referencia mediante el detalle del ejercicio y de una sesión. Si ya existe otra URL, comprobarla y pasar su valor exacto con `--previous-url`. El comando compara UUID, slug y referencia previa al escribir, y admite reintentos. Para revertir una imagen, reasignar una versión anterior verificada de la misma manera. El rollback de la migración elimina la columna y sus referencias, por lo que conviene exportar el mapa slug/URL antes de revertir.

## Publicaciones independientes

Laravel requiere migración, configuración de la base pública y asociación de los objetos revisados. PostgreSQL guarda únicamente la URL. La serialización de Eloquent ya incluye este atributo tanto en catálogo como en sesiones mediante la relación precargada. Los identificadores, instrucciones existentes y el uso de media_url se conservan.

El cambio Flutter está escrito en Dart y usa la caché de imágenes en memoria de Flutter. Los datos JSON siguen los tiempos de vida e invalidaciones de ResourceCache. La imagen se solicita al abrir el detalle o la sesión. En sesión activa también se precarga la siguiente. La siguiente visita puede descargar de nuevo si Flutter expulsó la imagen o se reinició el proceso.

La entrega móvil requiere comprobar el parche sobre un Android que tenga la release Shorebird 1.0.0+1 antes de distribuirlo. La ilustración viaja por HTTPS y el icono alternativo ya se utilizaba en la aplicación. La publicación en Render, Supabase o Shorebird requiere autorización específica. [Compatibilidad de parches](https://docs.shorebird.dev/code-push/faq/)

## Verificación previa al despliegue del 16 de noviembre de 2026

Se comprobaron los 26 archivos del inventario. Todos decodifican como WebP de 1024 × 768, ocupan 857 572 bytes en total y coinciden en tamaño y SHA-256 con el CSV. En PostgreSQL local se cotejaron los 26 pares UUID/slug con el registro de asociaciones, sus instrucciones, descripciones y URL. Las 26 descargas HTTPS locales devolvieron el MIME y el hash esperados. El único ejercicio inactivo es el curl predicador. Laravel pasó 37 pruebas con 237 aserciones.

La conexión efectiva local usa PDO PostgreSQL con libpq 16.15, conexión directa por IPv6 en el puerto 5432, rol postgres y SSL requerido. No se detectó precedencia de DB_URL, contraseña del shell ni caché de configuración. Hubo negociación TLS 1.3 y la base rechazó la autenticación antes de ejecutar la primera consulta. El almacén de confianza del sistema no verificó por sí solo la CA de Supabase. Para usar verify-full se necesita la CA del proyecto. El endpoint de salud del servicio Render respondió 200 y su implementación devuelve un JSON estático.

Storage devolvió HTTP 400 y el error interno 404 «Bucket not found» al GET anónimo de sentadilla-v2.webp. El panel pidió iniciar sesión. La existencia administrativa del bucket, el plan, las cuotas, las políticas RLS, los UUID y el estado de migraciones en producción quedan sin verificar.

Antes de solicitar la aprobación de escritura:

1. Contrastar el destino de la conexión efectiva de Render con la referencia en Supabase. Con credenciales vigentes, consultar en modo de solo lectura el catálogo, las columnas, las claves foráneas y el registro de migraciones. Exportar UUID, slug, estado y URL previa por ejercicio. Resolver las diferencias de UUID por entorno antes de aprobar el mapa de producción.
2. Consultar el bucket, objetos, restricciones MIME y políticas efectivas, incluidas las globales de Storage. Consultar Storage Size y Egress del ciclo en la organización. Cotejar por hash cada objeto ya publicado. Ante una colisión con contenido diferente, preparar otra versión.
3. Presentar las cantidades exactas a crear, reutilizar y asociar. Incluir en la aprobación cualquier migración pendiente, las políticas que realmente requieran intervención y el alcance de la recuperación. El conjunto máximo son 26 objetos y 26 referencias.
4. Tras la autorización, registrar los objetos creados por la operación, verificar GET público y hash de todo el lote y aplicar las asociaciones por UUID en una transacción con comparación de URL anterior. Para recuperar las referencias, restaurar el mapa exportado únicamente donde la URL siga coincidiendo con la publicada. Una carga parcial se puede reanudar reutilizando objetos idénticos. La retirada de objetos creados precisa comprobar que carecen de referencias y autorizar la eliminación.
