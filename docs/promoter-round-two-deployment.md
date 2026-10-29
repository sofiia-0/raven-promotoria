# Despliegue de la Ronda 2 de Promotora

Este documento no autoriza despliegues ni migraciones en Laragon, staging o producción. Las migraciones de evidencias y categorías ya fueron aplicadas y verificadas únicamente en desarrollo local, con autorización y respaldo. No se han ejecutado ni autorizado aquí migraciones en staging o producción; el estado de cada entorno debe comprobarse antes de desplegar.

## Requisitos que debe verificar la persona que despliegue

1. Respaldar y verificar conjuntamente base de datos y objetos privados. Mantener el catálogo y las visitas existentes intactos.
2. PHP 8.4 o compatible con el proyecto, con `fileinfo`, `zip`, `dom`, `mbstring` y funciones de imagen habilitadas. Para generar las imágenes de pruebas se utiliza GD; la validación en producción usa `getimagesize`. No se requiere Imagick ni conversión HEIC.
3. En la SAPI web del alojamiento, `file_uploads=On`, `upload_max_filesize >= 10M` y `post_max_size >= 12M`; la recomendación operativa es 12M y 16M respectivamente, o superiores. Revisar límites del proxy, tiempo de petición y espacio temporal. Cada petición sube un único archivo y las cargas múltiples son secuenciales. `max_file_uploads >= 1` basta para este flujo.
4. PHP CLI local comprobado: 8.4.6, `upload_max_filesize=2G`, `post_max_size=2G`, `max_file_uploads=20`, con fileinfo/ZIP/DOM/GD. Esto no verifica la SAPI web ni la configuración futura de Laravel Cloud: esa comprobación permanece pendiente del despliegue autorizado.
5. Preparar almacenamiento **privado y persistente**. Por defecto el disco `visit_evidence` escribe en `storage/app/visit-evidence`, fuera de las rutas públicas y del disco local servido. No ejecutar `storage:link` para estas evidencias. Un disco efímero de un contenedor no basta para producción.
6. Si se configura otro disco mediante `VISIT_EVIDENCE_DISK`, debe admitir escritura, lectura por stream, tamaño, existencia y eliminación, además de visibilidad privada. Un disco S3 privado existente puede usarse tras verificar sus permisos y persistencia. No se crea ningún servicio externo o de pago automáticamente. No publicar rutas ni habilitar acceso anónimo al bucket. Respaldar los objetos junto con sus metadatos y hashes.

## Migraciones aditivas y estado por entorno

`2026_10_28_000001_create_visit_evidences_table.php` añade únicamente `visit_evidences`, con referencias a visita y persona que cargó el archivo, estados de carga, metadatos, hashes e índices. No transforma tablas ni datos históricos. Los borrados referenciados se restringen para proteger evidencia. Revisar previamente `php artisan migrate:status` y aplicar, con autorización y respaldo, únicamente las migraciones pendientes revisadas mediante el procedimiento habitual del proyecto.

`2026_10_29_000001_create_promoter_reason_categories_and_visit_objectives.php` crea únicamente `promoter_reason_categories` y `visit_objectives`; no rellena históricos ni inserta catálogos. Revisar también [las reglas y el sincronizador de motivos](promoter-round-two-final-adjustments.md). Ninguna autorización local se extiende a staging.

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

## Condiciones previas específicas de Laravel Cloud

Revisión documental: 29 de octubre de 2026. No se accedió al panel ni se modificó infraestructura de Cloud.

- El filesystem de las réplicas es efímero y no compartido. Evidencias, sesiones y caché no deben depender de persistencia local; preparar objetos privados y sesiones/caché en base de datos o Redis según el entorno. [Documentación oficial](https://laravel.com/cloud/docs/environments#filesystem).
- Object Storage requiere `league/flysystem-aws-s3-v3`; el proyecto actual **no incluye ese adaptador**. Antes de usarlo, aprobar su incorporación y verificar instalación y configuración con el almacenamiento elegido. Cloud configura el disco conectado; seleccionar bucket privado y comprobar el nombre efectivo del disco en Laravel. [Requisitos y discos](https://laravel.com/cloud/docs/resources/object-storage).
- `FILESYSTEM_DISK` no sustituye a `VISIT_EVIDENCE_DISK`: las evidencias usan su propia configuración y conservan `disk` en cada fila. Al trasladar datos, mantener resolubles los nombres de discos anteriores y copiar/verificar objetos y hashes. No reescribir esos metadatos automáticamente. No confundir `AWS_ENDPOINT` de una configuración manual con las variables que inyecte Cloud; comprobar la configuración efectiva sin imprimir secretos.
- No habilitar URL pública, `storage:link` ni CDN público para evidencias. El acceso sigue pasando por sus controladores autorizados. Verificar lectura por stream, tamaño, eliminación y visibilidad privada con el proveedor; no suponer compatibilidad de ACL por objeto. Una prueba local con `Storage::fake` no valida R2 o S3 real.
- Cloud puede iniciar despliegues al recibir pushes. Antes del merge que hará la propietaria, revisar rama vinculada y Push to deploy. Revisar también comandos existentes: un deploy command puede ejecutar migraciones. No fusionar ni activar despliegues hasta autorizar el procedimiento y el respaldo. [Despliegues](https://laravel.com/cloud/docs/deployments).
- El repositorio **no programa** `raven:evidence-prune --apply`. Primero revisar su simulación y autorizar la limpieza; después declarar la tarea y habilitar Scheduler. Con réplicas, considerar `onOneServer` y `withoutOverlapping` con caché compartida. Activar Scheduler sin declarar tareas no programa esta limpieza. [Programador de Cloud](https://laravel.com/cloud/docs/scheduled-tasks).

### Comprobación del runtime y orden seguro

1. Elegir PHP compatible con `composer.lock` (el entorno probado usa 8.4), MySQL 8.0.16 o superior por los CHECK, y Node compatible con Vite 8 (`^20.19.0 || >=22.12.0`). Comprobar extensiones y límites en la **SAPI web**, no solo CLI. `post_max_size` debe superar `upload_max_filesize`; el temporal debe ser escribible. [PHP](https://www.php.net/manual/en/ini.core.php#ini.upload-max-filesize).
2. Mantener DB, bucket y credenciales separados de producción. Respaldar y verificar base y objetos. No subir CSV, informes privados ni respaldos al repositorio, imagen de build o directorio público.
3. Instalar dependencias bloqueadas y compilar assets (`composer install --no-dev`, `npm ci`, `npm run build`). No utilizar `composer setup`: incluye operaciones de configuración y migración no apropiadas para este procedimiento controlado.
4. Verificar `APP_ENV`, `APP_DEBUG=false`, URL HTTPS, clave estable por entorno, cookies/sesiones y correo. No regenerar `APP_KEY` de una instalación existente. Revisar configuración efectiva y caches sin mostrar secretos. Confirmar `/up` y las rutas Livewire.
5. Consultar `migrate:status` en el entorno autorizado y comparar con el inventario del release. Probar primero sobre una copia aislada de su esquema. Detenerse ante diferencias; no asumir que staging tiene el mismo historial que Laragon. Aplicar solo las migraciones pendientes revisadas. Nunca `migrate:fresh`, `db:wipe`, seeders de demostración ni importaciones automáticas.
6. Los catálogos no se cargan mediante migraciones: inspeccionar checklist y motivos existentes; simular los sincronizadores de cada alcance, revisar conflictos e IDs, verificar respaldo y obtener autorización antes de aplicar con huella. No ejecutar `VisitCatalogSeeder` indiscriminadamente.
7. Probar carga, eliminación previa y descarga con cuentas y datos ficticios autorizados; verificar privacidad y persistencia después de otro deploy/reinicio y entre réplicas. Confirmar que otra promotora no accede a esos archivos. Verificar mapa con consentimiento, CSP y geolocalización HTTPS.
8. Conservar el release anterior, base y objetos sincronizados para recuperación. Volver al código anterior no deshace cambios de base. No ejecutar `down()` de evidencias/objetivos ni eliminar categorías utilizadas como supuesto rollback seguro.

El despliegue sigue **condicionado** a configurar y probar el almacenamiento persistente y el runtime reales. La verificación local no certifica una instalación de Cloud que no se ha inspeccionado.
