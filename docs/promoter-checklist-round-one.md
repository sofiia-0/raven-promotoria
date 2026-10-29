# Checklist oficial de Promotora: actualización controlada

Esta entrega prepara el catálogo oficial; no ejecuta actualizaciones al abrir páginas ni durante migraciones. No requiere cambios de esquema.

## Seis ítems activos esperados

1. Push Money.
2. Capacitación o revisión de plan de exhibición.
3. Pedido generado.
4. Entrega de material promocional.
5. Entrega de muestra médica.
6. Entrega de Plan paciente.

La puntuación de la lista no se almacena como parte del nombre. No se crea «Entrega de Plan de recomendación».

## Revisión y autorización

Después de aprobar el código y autorizar el cambio de datos, ejecute primero:

```sh
php artisan raven:checklist
```

El comando predeterminado **solo consulta y simula**. Devuelve el SHA-256 de la fotografía, las acciones propuestas y los conflictos. Revise todos los casos antes de aplicar. No utilice `DatabaseSeeder` para actualizar un entorno existente.

La aplicación necesita la autorización explícita del responsable y una carpeta privada absoluta que ya exista, sea escribible y esté fuera del repositorio:

```sh
php artisan raven:checklist --apply --expect=SHA256_APROBADO --backup=CARPETA_PRIVADA_ABSOLUTA
```

Sustituya ambos valores por los de la revisión. El comando solo permite aplicar en entornos `local` o `testing`; no implementa un procedimiento para staging o producción. No hay opciones de fuerza para saltar conflictos.

La fotografía se compara otra vez dentro de una transacción con bloqueo del catálogo. Si cambiaron las etiquetas o las respuestas desde la simulación, no se actualiza nada y debe revisarse una simulación nueva. Se guarda y verifica el respaldo antes de modificar filas. El resultado de la transacción se comprueba y acompaña de un recibo con el estado posterior, también verificado con SHA-256. Un error antes del commit revierte los cambios de catálogo de esa transacción.

## Conservación del historial

- Los seis ítems genéricos anteriores y el ítem excluido, si existen, se **desactivan**; no se borran ni renombran.
- Sus IDs, nombres, descripciones y respuestas históricas permanecen disponibles en el detalle de cada visita.
- Un ítem oficial ya existente reutiliza su ID. No se reescriben sus descripciones.
- Una variante de escritura sin respuestas puede adoptar el nombre oficial conservando su ID. Si ya tiene respuestas y cambiar el nombre afectaría el historial, se bloquea para revisión.
- Dos etiquetas equivalentes después de normalizar espacios, mayúsculas y tildes requieren revisión; no se fusionan IDs automáticamente.
- Los ítems activos desconocidos bloquean la actualización. Los desconocidos inactivos se conservan sin cambios.
- Repetir la operación cuando ya se alcanzó el estado oficial no crea filas ni actualiza timestamps.
- Las visitas y `visit_checklist` no reciben actualizaciones. Los registros nuevos continúan usando el mecanismo existente de `VisitRecorder`, que guarda las respuestas de los ítems activos en el momento del registro.

El respaldo contiene el catálogo anterior, las respuestas y el plan. El recibo registra los IDs y valores posteriores. Una recuperación futura debe comparar esos valores con el estado actual antes de restaurar metadatos: no deben sobrescribirse cambios posteriores ni eliminarse ítems que ya tengan respuestas nuevas. No se implementa ni ejecuta una reversión automática.

## Seeder

`VisitCatalogSeeder::CHECKLIST` referencia la lista oficial. El seeder puede poblar un checklist vacío y repetirse cuando ese catálogo ya está en el estado esperado. Si encuentra un catálogo que necesita transformación, se detiene antes de modificarlo o poblar motivos y remite al procedimiento de revisión y respaldo. Los motivos genéricos existentes en ese seeder no fueron aprobados ni modificados por esta entrega.

No ejecute seeders de demostración en una base real para probar esta funcionalidad.

## Búsquedas

El autocompletado de planificación y registro conserva un ID real, separado del texto escrito. `PharmacyAutocomplete` usa `EstablishmentSearch::forPromoter` para limitar resultados y selección a establecimientos activos con asignación vigente. El permiso se vuelve a validar al guardar mediante los servicios existentes.

El historial usa `EstablishmentSearch::apply` dentro de la consulta de visitas propias. No se usa el filtro de asignación actual para el historial, porque una visita propia debe seguir disponible aunque posteriormente se transfiera o desactive el establecimiento. Se conservan los filtros de fechas, resultado y origen.

La búsqueda combina términos parciales separados por espacios o comas, sin distinguir mayúsculas ni tildes. Los comodines `%` y `_` se tratan como caracteres literales. No se modifica ningún nombre ni ubicación de establecimientos.
