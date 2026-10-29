# Ronda 2: evidencias opcionales y ubicación de visitas

Implementado para nuevas visitas planificadas y fuera de plan. No modifica visitas históricas, establecimientos, asignaciones, solicitudes, reprogramaciones ni el servicio original `VisitRecorder`.

## Uso y límites

- Hasta 10 archivos, 10 MiB por archivo y 40 MiB por visita. Hasta 20 pendientes y 80 MiB por promotora para limitar formularios abandonados.
- JPG, PNG, WEBP, HEIC/HEIF, PDF, DOCX, XLSX, PPTX, ZIP y TXT UTF-8. El MIME real, estructura, extensión, tamaño y nombres se comprueban en el servidor. HEIC/HEIF se conserva y descarga; no se convierte ni previsualiza sin un decodificador adecuado.
- Cámara o galería en dispositivos compatibles; la cámara depende del navegador y dispositivo. Se muestra la lista, tamaño y vista previa de imágenes compatibles, con opción de quitar archivos.
- Los documentos se descargan como adjuntos. Solo JPG/PNG/WEBP tienen previsualización privada. No se ejecutan ni se extraen documentos a rutas del ZIP.
- Se rechazan ejecutables, scripts, HTML, SVG, doble extensión peligrosa, macros, cifrado, relaciones externas de Office, rutas ZIP inseguras, enlaces simbólicos, ZIP anidados y expansión abusiva. ZIP: máximo 200 entradas, 100 MiB expandidos y relación de expansión 100:1. Office: XML limitado a 2 MiB por entrada. Documentos legítimos con estas características deben exportarse a un formato admitido; no se relajan validaciones automáticamente.
- La inspección estructural no sustituye un antivirus ni garantiza ausencia de malware. No se incorporó un servicio antimalware externo ni se transmiten archivos a terceros. Antes de ampliar el acceso o admitir contenido más complejo, evaluar escaneo y cuarentena con el alojamiento.

## Autorización y consistencia

Los endpoints requieren sesión, rol Promotora activo, CSRF en cargas y límite de solicitudes. Los UUID pendientes están ligados a su propietaria y al identificador idempotente del formulario, bloqueados en Livewire. Una promotora solo puede ver evidencias de sus propias visitas, incluso tras una reasignación; no obtiene acceso al historial ajeno al compartir un establecimiento. La política central permite desarrollar Coordinadora posteriormente, sin concederle acceso en esta entrega.

Se reservan metadatos antes de escribir un objeto con UUID en disco privado. Se comprueban tamaño y SHA-256 después de cargar y antes de registrar. Visita, checklist, asociación y auditoría se confirman en una transacción. Si falla el registro, los pendientes permanecen disponibles para reintentar; no se añade información a una visita histórica. Repetir la misma confirmación es idempotente. Los pendientes abandonados se limpian explícitamente tras 24 horas; nunca se eliminan evidencias asociadas mediante ese proceso. Si falla una eliminación física, se conserva el registro para poder reintentar.

Los nombres originales saneados se usan como metadatos, nunca como rutas. Las respuestas no exponen disco ni ruta física; los enlaces comprueban visita, propietaria y archivo en cada solicitud. Las descargas incluyen `nosniff`, caché privada sin almacenamiento y disposición de adjunto.

## Mapa con consentimiento

El detalle usa únicamente coordenadas ya guardadas y válidas; no captura ubicación ni hace seguimiento. La precisión se muestra cuando existe. Antes de pulsar «Mostrar mapa de OpenStreetMap» no se crea el iframe ni se realizan solicitudes al proveedor. El aviso informa que se compartirán coordenadas e IP. Ocultar el mapa retira el iframe. No hay claves, nueva dependencia, pago ni bloqueo del registro si se deniega geolocalización.

OpenStreetMap ofrece la [incrustación mediante iframe](https://wiki.openstreetmap.org/wiki/Export). Se conserva atribución visible y referente limitado al origen, de acuerdo con la [política de teselas](https://operations.osmfoundation.org/policies/tiles/) y las [indicaciones de atribución](https://osmfoundation.org/wiki/Attribution_Guidelines). No existe garantía de disponibilidad del proveedor; el detalle y las evidencias funcionan sin él.

Consulte [las instrucciones de despliegue](promoter-round-two-deployment.md) antes de habilitar evidencias en una base real.
