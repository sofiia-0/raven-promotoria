# Pendiente de Ronda 2: múltiples evidencias por visita

Requerimiento documentado, sin implementación, migraciones ni endpoints de carga en esta entrega.

Se necesita adjuntar varias evidencias a una visita. Los formatos por evaluar son imágenes, PDF, documentos, presentaciones y ZIP. No deben habilitarse todos por defecto ni confiar únicamente en la extensión o el MIME declarado por el navegador.

## Decisiones pendientes

- Definir una lista explícita de extensiones y tipos comprobados en servidor para cada formato aprobado; decidir si se admitirán archivos con macros, contenido activo o cifrado. ZIP requiere tratamiento específico de archivos anidados, tamaño descomprimido, rutas internas y contenido no permitido. No extraerlo automáticamente.
- Acordar número de archivos por visita, tamaño por archivo y acumulado, cuotas y restricciones de imágenes y documentos. Rechazar incompatibilidades entre contenido, extensión y tipo declarado.
- Evaluar análisis antimalware, cuarentena y estado de revisión antes de permitir descarga o previsualización. No ejecutar documentos ni servir contenido activo como parte de la aplicación.
- Almacenar en disco privado con nombres generados, conservar metadatos y nombre original saneado. No crear rutas públicas ni utilizar el nombre enviado como ruta de almacenamiento.
- Autorizar cada carga, consulta y descarga a partir de la visita y los permisos vigentes. Resolver expresamente el acceso al historial tras una reasignación, según las reglas actuales. Evitar accesos directos por IDs de otra promotora.
- Definir auditoría, retención, eliminación y recuperación; comprobar qué ocurre si falla el guardado de la visita o una carga parcial. Decidir si las evidencias se agregan durante el registro o también después.
- Diseñar un selector múltiple accesible con estados de carga, errores por archivo y alternativa en móvil, conservando la composición actual.
- Probar formatos manipulados, archivos con doble extensión, contenido malicioso, ZIP de expansión abusiva, cuotas, permisos, descargas y recuperación de fallos usando almacenamiento y bases aislados.

Los límites concretos, formatos finales y herramientas de inspección deben aprobarse antes de implementar. Este requerimiento continúa en Ronda 2 junto con carga rápida, copia de semanas, calendario y mapa.
