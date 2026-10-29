# Ajustes funcionales finales de la Ronda 2

## Separación de conceptos y compatibilidad

`visit_reasons` conserva sus IDs, nombres, orden e inactivos. Este catálogo ya se comparte entre no realización y reprogramación; no se cambian sus relaciones ni reglas. Las siete entradas de `VisitCatalogSeeder::REASONS` se utilizan exclusivamente como propuestas de motivos. Nunca se ejecuta ese seeder desde el sincronizador y no se toca el checklist.

Los objetivos generales se aplican solo a visitas realizadas y son opcionales. No sustituyen ni duplican las seis actividades del checklist. Los tipos de planificación permanecen VISITA, REUNION, FERIADO y OTRA.

La presentación del checklist utiliza «Realizada», «Sin marcar» y «actividades registradas». No modifica valores `checked`, ítems ni resultados históricos. Una actividad sin marcar no se clasifica como incumplimiento.

Los motivos de alta y baja pertenecen a dominios distintos. Se conservan las observaciones existentes y sus límites de validación. La categoría opcional de una nueva solicitud se guarda como `data.reason_category`, separada de `reason`, con ID, dominio, código, nombre y condición provisional resueltos en servidor. Los metadatos enviados por el navegador no se aceptan como fuente de verdad. Las solicitudes históricas siguen funcionando sin esa clave.

Las solicitudes nuevas exigen una categoría cuando el catálogo de su dominio tiene opciones activas. Sin opciones se conserva el flujo histórico con justificación obligatoria. Las observaciones son opcionales para categorías ordinarias; si se indican, mantienen los límites previos (10–1500 caracteres). Sin observaciones, `reason` conserva el nombre seleccionado para los lectores históricos y la categoría queda estructurada en JSON. No se sobrescriben justificaciones anteriores. El objetivo de visitas realizadas sigue siendo opcional. Las opciones «Otro» requieren una explicación de al menos 10 caracteres. Una categoría desactivada, inexistente o de otro dominio se rechaza. Las etiquetas históricas se guardan como fotografía y no cambian al renombrar el catálogo.

## Migración separada y aditiva

`2026_10_29_000001_create_promoter_reason_categories_and_visit_objectives.php` crea:

| Tabla nueva | Propósito |
| --- | --- |
| promoter_reason_categories | Catálogos por dominio, código estable, nombre, orden, activo, provisional y Otro. Código único por dominio. |
| visit_objectives | Un objetivo opcional por visita nueva, referencia al catálogo, fotografía del código/nombre y explicación. |

No añade columnas a tablas existentes ni transforma datos. No inserta opciones automáticamente. Las claves foráneas restringen borrados que dejarían objetivos sin visita o categoría. Las visitas antiguas no reciben objetivos retrospectivos. Hasta habilitar el esquema y las opciones, las pantallas conservan su funcionamiento previo sin los controles nuevos.

No ejecutar esta migración sobre una base real hasta revisar impacto, respaldo y autorización específica. Toda aplicación requiere su propia autorización; el estado de la instalación local se registra solo en el informe privado de entrega. Se comprobó la migración en SQLite y MySQL aislados.

El `down()` elimina las dos tablas nuevas: no es una recuperación segura si ya contienen información. Ante un incidente, preservar base y archivos, realizar un nuevo respaldo y revisar una recuperación que proteja cambios posteriores. No borrar objetivos reales automáticamente.

## Propuestas iniciales para Coordinadora

Todas las opciones nuevas se crean con `is_provisional=true`. No se presentan como categorías oficiales. Su condición provisional se conserva en el catálogo sin advertencias técnicas repetidas en los formularios. Los códigos e IDs existentes no se renombran durante la sincronización; cambiar la configuración solo afecta a propuestas ausentes.

- Objetivo de visita: Seguimiento comercial; Verificación de información; Otro (especificar).
- Alta: Nuevo establecimiento identificado; Apertura de nueva sucursal; Ampliación de cobertura; Otro (especificar).
- Baja: Cierre del establecimiento; Establecimiento sin actividad comercial; Retiro de cobertura; Otro (especificar).

La administración futura puede utilizar el modelo y los estados activo/provisional del catálogo. No se desarrolla una interfaz de Coordinadora ni se cambia el proceso de aprobación de solicitudes.

## Sincronización revisable

```sh
php artisan raven:promoter-reasons --scope=unperformed
php artisan raven:promoter-reasons --scope=categories
```

Son simulaciones. `unperformed` inspecciona únicamente motivos de no realización; `categories` inspecciona los tres dominios nuevos. `all` combina ambos. La comparación evita duplicados por mayúsculas, tildes y espacios, pero conserva IDs, grafía e inactivos existentes. Las equivalencias ambiguas o códigos incompatibles bloquean toda la operación.

Después de aprobar una simulación y verificar respaldo del entorno, aplicar únicamente el alcance aprobado:

```sh
php artisan raven:promoter-reasons --scope=unperformed --apply --expect=HUELLA_REVISADA --backup=CARPETA_PRIVADA_EXISTENTE
```

`--backup` y la huella son obligatorios para aplicar. La carpeta debe estar fuera del repositorio. El comando escribe y verifica el estado anterior y un diario de los IDs creados dentro de la operación transaccional. No actualiza ni reactiva entradas. Un fallo de respaldo o conflicto revierte las inserciones. Repetir la sincronización con una simulación nueva no duplica entradas. Revisar copias y diario antes de una eventual reversión: no eliminar un ID si ya está relacionado con visitas o reprogramaciones.

No ejecutar `VisitCatalogSeeder` indiscriminadamente ni seeders de demostración sobre bases existentes. Las categorías nuevas requieren su migración y una sincronización específica; no quedan autorizadas por habilitar evidencias.

## Evidencias y comprobación local

La habilitación de evidencias utiliza únicamente `2026_10_28_000001_create_visit_evidences_table.php` y el disco privado ya definido. Antes de aplicarla: verificar entorno local, migraciones pendientes, esquema, respaldo completo con restauración comprobada, extensiones PHP y almacenamiento privado. Ante otra migración pendiente o discrepancia, detenerse y solicitar autorización. No ejecutar migraciones en staging o producción sin aprobación separada.

Para comprobar almacenamiento en una base real, una carga ficticia pendiente puede verificarse y eliminarse inmediatamente sin asociarla a visitas históricas. Registro completo y descarga se comprueban con datos ficticios en aislamiento; no crear visitas de demostración en el catálogo real. Conservar los límites, permisos y procedimiento de limpieza descritos en `promoter-visit-evidence-round-two.md` y `promoter-round-two-deployment.md`.

## Mapa durante el registro y en el detalle

El mismo componente presenta el punto capturado en el registro, con marcador fijo y precisión disponible. No permite editar las coordenadas desde el mapa. Sin coordenadas válidas no se carga iframe ni se bloquea el guardado.

La primera activación explica que OpenStreetMap recibirá coordenadas e IP. La autorización se guarda en la sesión Laravel, asociada a la persona autenticada. Permanece al navegar entre registro y detalle; puede retirarse para la sesión. No se comparte entre usuarios ni se conserva tras invalidar la sesión. El detalle nunca solicita geolocalización y no existe seguimiento continuo.

Un mapa de primera parte con calles exigiría almacenar/servir datos cartográficos y mantener infraestructura adicional. Se conserva la incrustación ligera con consentimiento por sesión, sin claves, pagos ni dependencias nuevas del proyecto. Referencias: [servidor propio de teselas](https://switch2osm.org/serving-tiles/) e [incrustación oficial](https://wiki.openstreetmap.org/wiki/Export). No se usa un proxy que oculte transferencias ni se descarga cartografía masiva.

## Explicación de Otro motivo

En registros nuevos de visitas no realizadas, «Otro motivo» exige al menos 10 caracteres en observaciones. La validación se aplica en el formulario y el servicio de contexto, sin cambiar las reglas de reprogramación ni revalidar retrospectivamente visitas antiguas o reintentos idempotentes.
