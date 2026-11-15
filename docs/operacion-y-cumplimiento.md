# Operación, privacidad y evidencias de cumplimiento

**Alcance confirmado por el usuario:** proyecto universitario, sin datos reales. El modo académico está activado por defecto (`ERP_MODO_ACADEMICO=true`). Usa un límite de ejemplo claramente marcado como simulación, comprobantes locales y JSON didáctico sin firma, sello ni transmisión. No exige datos empresariales ni topes BCR reales para demostrar los módulos. Las instrucciones de producción y cumplimiento que siguen son referencias para una eventual evolución, no requisitos para entregar el proyecto universitario.


Estado: procedimiento preparado para adopción y revisión por los responsables de la empresa. No acredita que ya esté ejecutado. Complementa el detalle técnico de `correcciones-auditoria-2026-11-15.md`.

## Responsables que deben designarse

- Dirección: aprobar alcance, actividad, riesgos y políticas de conservación.
- Contabilidad: clasificar operaciones, verificar topes BCR, revisar activos, IVA, libros y declaraciones.
- Responsable de datos personales: aprobar aviso, fundamentos y atención de derechos; coordinar incidentes.
- Operaciones: accesos, respaldos, cron, colas, restauración y evidencias.

Registrar nombre, cargo, suplente y contacto de cada responsable en un archivo privado. El repositorio no proporciona esos datos y no debe inventarlos.

## Privacidad y derechos de las personas

Antes de recopilar datos, completar y entregar un aviso escrito que identifique responsable/contacto, datos tratados, finalidades, fundamento legal, conservación, destinatarios/encargados y medios para ejercer ARCO-POL. Distinguir lo necesario para el contrato/obligación legal de finalidades que requieren consentimiento. La categoría documental de consentimiento disponible en expedientes no sustituye por sí sola este proceso.

Procedimiento de atención, incluso si se opera fuera de la interfaz:

1. Recibir la solicitud en el canal publicado; asignar folio y fecha/hora sin exponer el documento de identidad en un registro público.
2. Verificar identidad y representación, consultando solo la información necesaria.
3. Registrar derecho solicitado, alcance, responsables y vencimiento revisado con calendario hábil aplicable. La ley consultada contempla veinte días hábiles de respuesta y posible prórroga justificada, sin asumir que todos los fines de semana/feriados se calculan automáticamente.
4. Revisar conservación tributaria, contratos y otras restricciones. Un derecho de supresión no autoriza borrar pruebas que deben conservarse legalmente.
5. Ejecutar lo procedente con acceso restringido y registrar datos afectados, fundamento, actor y evidencia. No borrar recibos, documentos ni bitácoras para ocultar errores.
6. Comunicar resolución motivada por el canal autorizado; conservar constancia de entrega y comunicar rectificaciones a destinatarios cuando corresponda.

Registro privado mínimo: folio, fecha de recepción, titular/referencia interna, derecho, evidencia de identidad verificada, responsable, fecha límite, prórroga/motivo, resolución/fundamento, fecha de ejecución, destinatarios notificados y constancia de respuesta.

Las consultas o comunicaciones a burós requieren revisar la ley especial de historial crediticio y la autorización aplicable. El sistema no realiza esas consultas; no se afirma que el documento de autorización sea obligatorio para todo crédito.

## Conservación y acceso

- Elaborar inventario por categorías: contabilidad/DTE, ventas, pagos, contratos, expedientes personales, logs y copias.
- Aplicar los plazos legales a la categoría correspondiente. El artículo 147 del Código Tributario contempla diez años para la información que enumera; no convertirlo en retención indiscriminada de todo dato personal.
- Documentar finalidad, fundamento, inicio del cómputo, período, bloqueo legal, revisión y eliminación autorizada.
- Conservar juntos base de datos, expedientes, versiones del programa necesarias para lectura y claves de recuperación.
- Verificar altas/bajas de usuarios y permisos con periodicidad definida; retirar accesos al terminar una relación laboral.
- HTTPS, protección del servidor, cifrado de discos y almacenamiento privado son controles del despliegue que deben comprobarse. El cifrado de credenciales en Laravel depende de APP_KEY; custodiar esa clave por separado.
- Se mantienen los controles de tamaño/MIME/hash/descarga privada de expedientes. No se añadió un motor antivirus: evaluar e integrar análisis antimalware para documentos antes de su uso comercial.

## Respaldos y recuperación

El script `scripts/respaldar-erp.sh` exige `pg_dump`, `age` y credenciales PostgreSQL suministradas mediante los mecanismos del cliente. La clave privada de recuperación de `age` nunca debe guardarse junto con los respaldos. Definir destino fuera de la raíz pública, acceso mínimo, conservación por categoría y una copia independiente del servidor.

Procedimiento:

1. Validar capacidad del destino y acceso a la clave de recuperación; no depender de una sola persona o del mismo disco.
2. Detener workers y otras escrituras; activar mantenimiento. El script exige que mantenimiento esté activo y no cambia ese estado por su cuenta.
3. Configurar `PGDATABASE`, `PGPASSFILE` y conexión, `ERP_BACKUP_DIR` y destinatario público `ERP_BACKUP_RECIPIENT`. Ejecutar el script.
4. Confirmar salida exitosa y registrar fecha, versión del código, destino, tamaño, responsable y hash del archivo cifrado. El manifiesto interno contiene hashes del dump y de los expedientes.
5. Retirar mantenimiento y reanudar workers tras verificar la operación; registrar incidencias. No eliminar respaldos por antigüedad sin política y fundamento revisados.
6. En una máquina o base desechable separada, descifrar con `age`, inspeccionar el contenido y extraerlo únicamente en un directorio privado vacío. Verificar `sha256sum --check SHA256SUMS`.
7. Restaurar `database.dump` con `pg_restore` en una **base nueva destinada a recuperación**; restaurar expedientes privados y recuperar APP_KEY desde su custodia independiente. No ejecutar restauración de ensayo sobre producción.
8. Comparar cantidades, sumas de capital/pagos, referencias, hashes de documentos, permisos y reimpresión de recibos. Medir tiempo de recuperación y pérdida de datos respecto al objetivo aprobado.
9. Conservar acta del ensayo y acciones correctivas. Leer el archivo de respaldo no demuestra que el sistema se pueda restaurar.

No se ejecutó el script cifrado ni un ensayo completo de restauración. Para actualizar la demostración local se conservó previamente una copia privada del clúster detenido, configuración y expedientes. Los objetivos de recuperación, ubicación externa y calendario deben ser aprobados según la operación real.

## Incidentes de seguridad

1. Registrar hora de detección, alcance inicial y responsable. Contener accesos comprometidos conservando logs y evidencia.
2. Identificar información afectada, personas, encargados y exposición; preservar originales con hashes y acceso restringido.
3. Coordinar análisis técnico y legal. La ley consultada contempla notificación de vulneraciones dentro de setenta y dos horas desde su conocimiento a las entidades y personas que establece; confirmar aplicación al incidente y no esperar al cierre del análisis para activar el procedimiento.
4. Documentar notificaciones, medidas inmediatas, recuperación y seguimiento.
5. Revisar causa, permisos, claves, copias y controles; realizar un ejercicio de este procedimiento y conservar evidencia.

## ISO: matriz inicial de evidencias

| Referencia | Evidencia técnica disponible | Evidencia organizativa pendiente |
|---|---|---|
| ISO/IEC 25010:2023 | Pruebas de permisos, capital/interés/mora, PEPS, transacciones, correlativos, compilación | Requisitos y criterios de aceptación, rendimiento/carga, accesibilidad, métricas y evaluación del producto completo |
| ISO/IEC 27001 | Autorización en servidor, archivos privados, trazas, credenciales cifradas, diagnóstico y respaldo preparado | Alcance del SGSI, riesgos, tratamiento, declaración de aplicabilidad, formación, proveedores, continuidad, incidentes, auditoría y revisión de dirección |
| ISO 9001 | Código versionado, pruebas y documentación de correcciones | Procesos aprobados, objetivos/indicadores, competencia, control documental, no conformidades, auditorías y mejora continua |

Cada control necesita responsable, periodicidad, criterio de aceptación y registro de ejecución. Las pruebas automatizadas no constituyen certificación. Validar ediciones, alcance y transición aplicables con el organismo evaluador.

## Condiciones de salida a producción

Además de pruebas aprobadas: resolver hallazgos del diagnóstico, conciliar datos históricos, cargar topes verificados, revisar contratos y aviso de privacidad, comprobar recuperación y permisos reales, desplegar HTTPS/cron/colas y cerrar la integración fiscal si se va a emitir DTE. Hasta entonces operar únicamente con el alcance local y los controles disponibles, sin ofrecerlo como sistema fiscal autorizado.
