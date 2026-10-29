# Presentación uniforme de establecimientos

`EstablishmentPresentation` es una proyección de lectura. No normaliza ni guarda registros. El formato compartido es nombre comercial, departamento y municipio, dirección disponible. Cuando ambos campos geográficos tienen el mismo texto se muestran las dos posiciones (por ejemplo, Managua, Managua): representan campos distintos.

`Pharmacy::display_name` omite el departamento agregado y los fragmentos posteriores al separador histórico. `branch_summary` conserva los fragmentos no equivalentes a la geografía actual. No se deducen direcciones a partir de esos fragmentos: números, nombres de sucursal y referencias ambiguas siguen siendo identificadores secundarios. Las fichas, detalles, cronograma, visitas e historial no muestran esos identificadores ni etiquetas de referencia. Se conservan internamente. Solo si dos opciones de un selector tienen el mismo nombre, ubicación y dirección, se añade una distinción textual en esas opciones; no se ocultan ni fusionan registros.

`display_locality`, `display_address` y `location_summary` utilizan la misma proyección. La dirección conserva el texto original salvo segmentos completos duplicados o exactamente equivalentes a los campos geográficos; nunca elimina coincidencias parciales (por ejemplo, León no elimina Calle Leónidas). `neighborhood` solo se antepone a la dirección mostrada cuando no coincide con la geografía ni aparece como frase completa en ella, sin distinguir tildes o mayúsculas. Esto no certifica la integridad ni exactitud de una dirección histórica.

`x-promoter.location` es la representación común de tarjetas, solicitudes, cronograma, visitas e historial. `x-promoter.visit-location` delega a ella. El autocompletado usa las mismas proyecciones y conserva el ID real como selección. Sus permisos, consultas y búsqueda continúan usando los datos originales. Los identificadores se calculan sobre las opciones ya autorizadas, sin consultar establecimientos ajenos ni cambiar su orden o cantidad.

## Edición conservadora

El formulario solo presenta departamento, municipio y dirección detallada como campos de ubicación. No hidrata ni envía `neighborhood`; el servicio no actualiza campos ausentes. La dirección editable contiene el valor realmente guardado, sin copiar automáticamente el resumen mostrado. La información histórica adicional continúa disponible en la dirección proyectada de la cabecera, sin avisos internos ni cambios de datos.

El nombre editable es el nombre comercial proyectado. Si no cambia, se omite del guardado para evitar normalizar el original. Si la persona lo cambia expresamente, `PharmacyEditor` conserva el sufijo histórico original, verifica la longitud del resultado y registra el cambio completo en la auditoría existente. No se agregan sufijos a establecimientos nuevos ni se reescriben nombres por visitar una pantalla.

No se exige retroactivamente una dirección completa ni se modifica el flujo de alta. Abrir y guardar sin cambios debe conservar todos los atributos, `updated_at` y el historial.

## Consolidación futura

No hay migración ni proceso automático de consolidación en esta entrega. Cualquier limpieza de `neighborhood` y `address` requiere un manifiesto privado aprobado, fotografía y SHA-256, respaldo verificable, comparación de ID y `source_reference` y de todos los valores esperados, transacción y recibo con estado anterior y posterior. Los casos discrepantes se omiten. La reversión debe comprobar que no existan cambios posteriores y conservar la auditoría.

Los manifiestos, catálogos, respaldos y operadores locales privados no deben incorporarse al repositorio. Las pruebas usan establecimientos sintéticos y bases aisladas.
