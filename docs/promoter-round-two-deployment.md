# Despliegue de la Ronda 2 de Promotora

Este documento no autoriza despliegues ni migraciones en Laragon, staging o producción. En la entrega solo se ejecutaron migraciones y escrituras sobre entornos de pruebas aislados y datos ficticios.

## Requisitos que debe verificar la persona que despliegue

1. Respaldar y verificar conjuntamente base de datos y objetos privados. Mantener el catálogo y las visitas existentes intactos.
2. PHP 8.4 o compatible con el proyecto, con `fileinfo`, `zip`, `dom`, `mbstring` y funciones de imagen habilitadas. Para generar las imágenes de pruebas se utiliza GD; la validación en producción usa `getimagesize`. No se requiere Imagick ni conversión HEIC.
3. En la SAPI web del alojamiento, `file_uploads=On`, `upload_max_filesize >= 10M` y `post_max_size >= 12M`; la recomendación operativa es 12M y 16M respectivamente, o superiores. Revisar límites del proxy, tiempo de petición y espacio temporal. Cada petición sube un único archivo y las cargas múltiples son secuenciales. `max_file_uploads >= 1` basta para este flujo.
4. PHP CLI local comprobado: 8.4.6, `upload_max_filesize=2G`, `post_max_size=2G`, `max_file_uploads=20`, con fileinfo/ZIP/DOM/GD. Esto no verifica la SAPI web ni la configuración futura de Laravel Cloud: esa comprobación permanece pendiente del despliegue autorizado.
5. Preparar almacenamiento **privado y persistente**. Por defecto el disco `visit_evidence` escribe en `storage/app/visit-evidence`, fuera de las rutas públicas y del disco local servido. No ejecutar `storage:link` para estas evidencias. Un disco efímero de un contenedor no basta para producción.
6. Si se configura otro disco mediante `VISIT_EVIDENCE_DISK`, debe admitir escritura, lectura por stream, tamaño, existencia y eliminación, además de visibilidad privada. Un disco S3 privado existente puede usarse tras verificar sus permisos y persistencia. No se crea ningún servicio externo o de pago automáticamente. No publicar rutas ni habilitar acceso anónimo al bucket. Respaldar los objetos junto con sus metadatos y hashes.

## Migración aditiva, pendiente en bases reales

`2026_10_28_000001_create_visit_evidences_table.php` añade únicamente `visit_evidences`, con referencias a visita y persona que cargó el archivo, estados de carga, metadatos, hashes e índices. No transforma tablas ni datos históricos. Los borrados referenciados se restringen para proteger evidencia. Revisar previamente `php artisan migrate:status` y aplicar, con autorización y respaldo, únicamente las migraciones pendientes revisadas mediante el procedimiento habitual del proyecto.

Hasta que exista la tabla, Registro y Detalle funcionan sin la sección de evidencias. Registrar sin adjuntos conserva el servicio y flujo previos. La migración no se ejecuta desde la aplicación ni desde una petición web.

## Limpieza de pendientes

```sh
php artisan raven:evidence-prune
```

Simula y devuelve candidatos, eliminados y fallos. No elimina archivos sin `--apply`.

```sh
php artisan raven:evidence-prune --apply
```

Solo tras revisar la simulación, puede programarse esa segunda orden cada hora mediante el programador del alojamiento. No se añadió ni ejecutó una programación sobre entornos reales. El proceso afecta únicamente pendientes de más de 24 horas, nunca evidencias asociadas. Revisar fallos y espacio disponible: si no se programa la limpieza, formularios abandonados consumen cuota y pueden impedir cargas nuevas. No borrar objetos manualmente por prefijo.

## Mapa y seguridad web

La aplicación no solicita mapas hasta el consentimiento explícito. Si el alojamiento aplica CSP, permitir en `frame-src` únicamente `https://www.openstreetmap.org` además de los orígenes ya necesarios. Los recursos del mapa se cargan dentro del iframe del proveedor. Conservar atribución y el aviso de IP/coordenadas. Verificar acceso HTTPS y política de geolocalización para el registro; denegar ubicación no impide guardar visitas. La vista del detalle no vuelve a solicitarla.

## Comprobación y recuperación

- Probar en una base y almacenamiento aislados: cero/uno/varios adjuntos, rechazo de formatos y cuotas, quitar un pendiente, permisos, descargas, fallo de almacenamiento/transacción, idempotencia, mapa antes/después de activar y regresiones de planificación.
- En una actualización, verificar primero almacenamiento persistente y migración, después cargas y descarga autorizada con datos ficticios. El servicio no añade adjuntos retrospectivamente.
- Si se necesita deshabilitar la función, preservar tabla y objetos y retirar el acceso mediante una corrección revisada; no ejecutar una reversión destructiva de migración con evidencias reales. Restaurar un respaldo únicamente mediante un procedimiento autorizado que mantenga sincronizados base y objetos y proteja cambios posteriores. El `down()` elimina la tabla y no constituye una reversión segura de archivos reales.
- La validación estructural limita contenido activo y abuso de comprimidos, pero no es un antivirus. Revisar escaneo y cuarentena antes de ampliar formatos o acceso futuro de Coordinadora.
