# Promotora: productividad de Planificación · Ronda 2

## Alcance

Calendario mensual y agenda, selección múltiple de farmacias y copia de semanas dentro de un cronograma propio en borrador. Sin migraciones, dependencias de calendario, nuevas rutas ni cambios en asignaciones, visitas, reprogramación o checklist. `SchedulePlanner` conserva sus reglas y su implementación; `PlanningProductivity` coordina las nuevas operaciones utilizando `addActivity` dentro de una transacción exterior.

## Carga de visitas

Seleccione la fecha y busque con el autocompletado existente. En Planificación, su callback opcional añade el resultado a una selección temporal; en otras pantallas el componente conserva su comportamiento individual. La selección admite retirar una farmacia o vaciar la lista. Revisar no inserta filas: la vista previa muestra establecimientos, fecha, notas y conflictos. Confirmar vuelve a validar todo antes de incorporar las visitas.

Solo se aceptan IDs distintos de establecimientos activos con asignación vigente a la promotora. Se impide repetir una farmacia en la misma fecha, tanto dentro de la selección como contra actividades existentes. Los registros distintos que comparten nombre y dirección no se consolidan. Los identificadores históricos se utilizan solo para diferenciar selecciones visualmente iguales.

Las notas son comunes a las visitas seleccionadas y se muestran antes de confirmar. El límite técnico es 100 farmacias por operación, no un límite diario. Las reuniones, feriados y otras actividades siguen utilizando el flujo individual original.

## Copia de semanas

Origen y destino deben ser semanas distintas del mismo cronograma. Se conservan el día de la semana, tipo, farmacia y notas. No se copian visitas registradas, estados de ejecución ni historial de reprogramación. No se sobrescribe ninguna actividad.

- Una visita ya existente en el destino o repetida dentro del plan de copia bloquea toda la operación.
- Una actividad sin farmacia se considera duplicada en la copia si coinciden fecha, tipo y notas. Actividades diferentes siguen las reglas actuales; no se inventan incompatibilidades entre tipos.
- Una farmacia que dejó de estar activa o asignada bloquea la operación.
- Los días ausentes en semanas parciales se muestran como excluidos. Copiar únicamente las filas válidas exige confirmar expresamente esas exclusiones. Sin esa confirmación no se inserta nada.
- Raven mantiene un máximo de cinco semanas persistidas, uniendo el tramo inicial al siguiente cuando el mes ocupa seis filas civiles. Si un día de la semana aparece dos veces en un tramo de origen o destino, no se elige arbitrariamente: esas filas se muestran sin fecha compatible y requieren revisión de la exclusión.

La cuadrícula del calendario siempre representa lunes a domingo y deja vacías las casillas ajenas al mes. Esta cuadrícula de lectura no cambia las semanas guardadas. En móvil se utiliza selector de fecha, navegación por semanas y franja de días de tamaño táctil, con agenda legible.

## Protección y auditoría

Las vistas previas se conservan como propiedades Livewire bloqueadas. Su huella incluye la solicitud, cronograma, semanas, actividades existentes y resultado validado. La confirmación reconstruye el plan y compara la huella. Cambios posteriores en fuente, destino, datos presentados o elegibilidad exigen revisar de nuevo; no se intenta aplicar una vista previa antigua.

La transacción bloquea el cronograma propio y las farmacias/asignaciones autorizadas implicadas, vuelve a validar y reutiliza `SchedulePlanner::addActivity`. Cualquier excepción revierte todas las inserciones y la auditoría de esa operación. No hay inserciones parciales por conflictos. Las exclusiones de fechas solo se aplican cuando fueron mostradas y aceptadas.

Se añaden eventos a la bitácora existente: `schedule.visits_added` y `schedule.week_copied`, con IDs creados, origen/destino y exclusiones. No se reescriben eventos anteriores. Repetir una confirmación ya aplicada resulta en una vista previa desactualizada o duplicados, sin nuevas inserciones.

No se puede enviar el cronograma con selección o vista previa de copia pendientes: se deben confirmar o descartar primero. Cambiar de mes limpia las selecciones y vistas previas; la navegación advierte si hay visitas seleccionadas sin guardar. No cambia ninguna actividad guardada.

Los cronogramas enviados conservan su contenido y solo permiten consultar calendario, semanas y estados existentes. El servicio verifica el estado otra vez al confirmar, además de ocultar los controles de edición. La reprogramación original permanece en su módulo y no fue modificada.

## Validación

`PlanningProductivityTest` cubre 15 visitas, duplicados, fechas inválidas, permisos compartidos o retirados, operaciones atómicas ante fallos, copias y semanas incompletas, tramos iniciales extendidos, huellas antiguas, cronogramas enviados, navegación y conservación de registros distintos. Las comprobaciones deben ejecutarse en SQLite en memoria o en el servidor MySQL aislado explícitamente permitido por el trait de pruebas. Nunca usar los datos de desarrollo como fixtures.

Las capturas, bases ficticias, operadores locales y resultados de revisión quedan fuera del repositorio. El mapa, los adjuntos múltiples y cualquier consolidación del catálogo quedan fuera de esta entrega.
