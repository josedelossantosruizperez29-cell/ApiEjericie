# Ilustraciones de Fit Home

## Catálogo local y dirección visual

Los seeders definen 20 movimientos domésticos y 6 de la multiestación EVO-500. El curl predicador está inactivo. Cada fila contiene el slug estable y el prompt de movimiento que se añade al siguiente encargo visual.

Render fitness 3D con la estética de la muestra de sentadilla. Persona adulta con ropa deportiva lisa, encuadre de cuerpo completo y apoyos visibles, fondo neutro claro e iluminación suave. Composición horizontal 4:3, sin texto ni marcas. Elige una vista que muestre los apoyos y las articulaciones relevantes. Para movimientos que se distinguen por dirección o fase aérea, muestra dos fases en la misma lámina. En máquina, utiliza una referencia visual de la estación y accesorio reales antes de generar. Revisa articulaciones, contacto con suelo o equipo, dirección y fidelidad al ejercicio antes de aceptar cada archivo.

| Slug | Movimiento y criterio visual | Estado local |
| --- | --- | --- |
| flexion | Flexiones de pecho. Apoya manos y pies, desciende en bloque y empuja. Evita hundir la zona lumbar. | Pendiente |
| flexion-rodillas | Flexiones con rodillas apoyadas. Apoya manos y rodillas y acerca el pecho al suelo con el tronco alineado. Acolcha las rodillas si lo necesitas. | Pendiente |
| flexion-inclinada | Flexiones inclinadas. Apoya las manos en una superficie fija y flexiona y extiende los codos. Comprueba que el apoyo no se deslice. | Pendiente |
| sentadilla | Sentadillas con peso corporal. Lleva la cadera atrás y abajo y vuelve a ponerte de pie. Mantén los talones apoyados. | [WebP revisado](exercises/sentadilla-v1.webp) |
| zancada | Zancadas. Avanza un pie, flexiona ambas rodillas y vuelve al inicio. Controla el equilibrio antes de alternar. | Pendiente |
| zancada-atras | Zancadas hacia atrás. Retrocede un pie, desciende y empuja con la pierna adelantada. Utiliza un apoyo estable si pierdes equilibrio. | Pendiente |
| puente | Puente de glúteos. Boca arriba con rodillas flexionadas, eleva la pelvis y baja despacio. Evita arquear la espalda. | Pendiente |
| talones | Elevaciones de talones. Eleva y baja los talones manteniendo las rodillas estables. Usa un apoyo si falta equilibrio. | Pendiente |
| plancha | Plancha abdominal. Apoya antebrazos y puntas de los pies y mantén el cuerpo alineado. Respira y termina cuando pierdas la alineación. | Pendiente |
| plancha-lateral | Plancha lateral. Coloca el codo bajo el hombro y eleva la cadera manteniendo la línea corporal. Acorta la palanca apoyando las rodillas si es necesario. | Pendiente |
| dead-bug | Abdominal dead bug. Boca arriba, extiende brazo y pierna opuestos, vuelve y alterna. Reduce el recorrido si se arquea la zona lumbar. | Pendiente |
| bird-dog | Bird dog en cuadrupedia. Desde cuatro apoyos extiende brazo y pierna opuestos y alterna. Mantén la pelvis nivelada. | Pendiente |
| jumping-jacks | Saltos de tijera. Abre las piernas al saltar mientras elevas los brazos y vuelve al centro. Aterriza con suavidad. | Pendiente |
| salto-estrella | Saltos de estrella. Desde semiflexión salta abriendo brazos y piernas y recógelos antes de aterrizar. Requiere tolerancia al impacto y control de la recepción. | Pendiente |
| escaladores | Escaladores. Desde apoyo de manos lleva una rodilla al pecho y alterna. Reduce el ritmo si pierde estabilidad el tronco. | Pendiente |
| rodillas-altas | Rodillas altas en el sitio. Alterna las piernas elevando las rodillas a una altura controlada. Adapta la altura y evita impactos bruscos. | Pendiente |
| marcha | Marcha rápida en el lugar. Eleva los pies de forma alterna acompañando con los brazos. Mantén libre el espacio a tu alrededor. | Pendiente |
| burpee | Burpees. Flexiona las piernas, apoya las manos, pasa a plancha, recoge los pies e incorpórate con un salto. Solo con capacidad física y tolerancia al impacto adecuadas. | Pendiente |
| sentadilla-salto | Sentadillas con salto. Flexiona las piernas, salta verticalmente y amortigua la caída. Requiere control de rodillas y aterrizaje. | Pendiente |
| jacks-sin-salto | Saltos de tijera sin salto (pasos laterales). Da un paso lateral mientras elevas los brazos, vuelve y alterna. Ajusta la amplitud del paso a tu equilibrio. | Pendiente |
| press-sentado | Press de pecho sentado. Sitúa las empuñaduras a la altura del pecho, empuja y regresa con control. Mantén la espalda apoyada. | Pendiente |
| mariposa | Aperturas de pecho con mariposa. Apoya los brazos, acércalos delante del pecho y vuelve despacio. Evita abrir los brazos más allá de un rango cómodo. | Pendiente |
| jalon | Jalón al pecho en polea alta. Sujeta la barra, lleva los codos hacia abajo y devuelve la carga lentamente. Lleva la barra delante de la cabeza. | Pendiente |
| remo | Remo sentado en polea baja. Siéntate frente a la polea y acerca el agarre al tronco manteniendo la espalda estable. Evita balancear el tronco. | Pendiente |
| extension-piernas | Extensión de piernas. Alinea las rodillas con el eje, extiende las piernas y baja despacio. Ajusta el rodillo y evita golpear el tope. | Pendiente |
| curl-predicador | Curl de bíceps con apoyo predicador. Apoya los brazos y flexiona los codos manteniendo los hombros estables. Verifica el accesorio de carga antes de utilizarlo. | Pendiente, inactivo |

La muestra de sentadilla pesa 20 498 bytes y mide 800 × 600 px. La revisión visual comprobó el apoyo de ambos pies, la flexión de cadera y rodillas y el encuadre completo. El inventario corresponde a los repositorios locales. Ninguna ilustración de esta entrega tiene URL de producción verificada.

## Preparar y comprobar Supabase Storage

1. En el panel de la organización, consultar plan, uso del ciclo, almacenamiento disponible, transferencia y transferencia en caché. Como referencia, Free incluye 1 GB de Storage y 5 GB de egress. Para 26 objetos con un máximo de 512 KiB cada uno, reservar hasta 13 MiB por versión completa. Estimar transferencia con el peso efectivo y las descargas mensuales. [Cuotas oficiales](https://supabase.com/docs/guides/platform/billing-on-supabase)
2. En un proyecto de pruebas, crear el bucket público `exercise-illustrations` en Storage, restringido a `image/webp`, con máximo de 524288 bytes por objeto. Usar el panel administrativo para la carga. Revisar las políticas de `storage.objects` para los roles anon, authenticated y public, incluidas las políticas generales que pudieran permitir escrituras en todos los buckets. La lectura pública de objetos es compatible con restringir su modificación. [Acceso a buckets](https://supabase.com/docs/guides/storage/buckets/fundamentals)
3. En pruebas, cargar un archivo de control como administrador. Verificar que GET público devuelve 200 sin token y que una carga, reemplazo y eliminación de ese archivo son rechazados con las credenciales públicas y de usuario ordinario. Comprobar que el contenido permanece intacto. Borrar el objeto de control desde el panel. Si hay políticas generales que conceden escritura, revisar su alcance antes de habilitar el bucket.
4. Subir los WebP aprobados bajo `exercises/<slug>-v<N>.webp`, conservando versiones anteriores. Usar un TTL largo para objetos inmutables. La aplicación obtiene el WebP original vía CDN. Copiar del panel la base pública hasta el nombre del bucket en `EXERCISE_ILLUSTRATIONS_BASE_URL` del backend. Las claves administrativas permanecen fuera de Flutter.
5. Con la migración aplicada al entorno de pruebas, ejecutar `php artisan fit:exercise-image sentadilla 1`. Comprueba el objeto real por HTTPS, su tipo y dimensiones. Para asignarlo, ejecutar la misma orden con `--apply`. Confirmar la referencia mediante el detalle del ejercicio y de una sesión. Para revertir una imagen, reasignar una versión anterior verificada. El rollback de la migración elimina la columna y sus referencias, por lo que conviene exportar el mapa slug/URL antes de revertir.

## Publicaciones independientes

Laravel requiere migración, configuración de la base pública y asociación de los objetos revisados. PostgreSQL guarda únicamente la URL. La serialización de Eloquent ya incluye este atributo tanto en catálogo como en sesiones mediante la relación precargada. Los identificadores, instrucciones existentes y el uso de media_url se conservan.

El cambio Flutter está escrito en Dart y usa la caché de imágenes en memoria de Flutter. Los datos JSON siguen los tiempos de vida e invalidaciones de ResourceCache. La imagen se solicita al abrir el detalle o la sesión. En sesión activa también se precarga la siguiente. La siguiente visita puede descargar de nuevo si Flutter expulsó la imagen o se reinició el proceso.

La entrega móvil requiere comprobar el parche sobre un Android que tenga la release Shorebird 1.0.0+1 antes de distribuirlo. La ilustración viaja por HTTPS y el icono alternativo ya se utilizaba en la aplicación. La publicación en Render, Supabase o Shorebird requiere autorización específica. [Compatibilidad de parches](https://docs.shorebird.dev/code-push/faq/)
