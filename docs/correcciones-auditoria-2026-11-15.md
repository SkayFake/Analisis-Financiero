# Correcciones de auditoría — 15 de noviembre de 2026

**Alcance confirmado por el usuario:** proyecto universitario, sin datos reales. El modo académico está activado por defecto (`ERP_MODO_ACADEMICO=true`). Usa un límite de ejemplo claramente marcado como simulación, comprobantes locales y JSON didáctico sin firma, sello ni transmisión. No exige datos empresariales ni topes BCR reales para demostrar los módulos. Las instrucciones de producción y cumplimiento que siguen son referencias para una eventual evolución, no requisitos para entregar el proyecto universitario.


## Alcance y estado

Se corrigieron fallas de autorización, costeo, correlativos, devengo, IVA local y activos fijos. Se agregaron pruebas de regresión y protecciones sobre datos históricos. Las pruebas automatizadas se ejecutan contra PostgreSQL en una copia con base desechable. Después de confirmar que todo es ficticio, se guardó una copia privada de recuperación y se aplicaron las migraciones nuevas, roles y escenarios QA en la base local del proyecto. No se ejecutó un reinicio ni borrado de tablas.

El sistema conserva su operación comercial local. **No está homologado para emitir DTE ni acredita cumplimiento ISO o legal integral.** La integración fiscal incompleta quedó bloqueada en el POS y en el servicio de transmisión. Bloquear la emisión incorrecta es una mitigación, no una implementación completa de facturación electrónica.

## 1. Autorización

- Clientes: lectura, alta, edición y borrado exigen su permiso; los formularios también autorizan la operación.
- Catálogos: lectura y acciones Livewire exigen `configuracion.manage`.
- Inventario: lectura exige `inventario.view`; movimientos exigen `inventario.create`.
- Activos: lectura, registro, depreciación y bajas exigen permisos separados.
- Cobros: tablero y reclasificación masiva exigen permisos diferentes.
- El menú oculta accesos no autorizados; esto complementa las comprobaciones del servidor.
- Se eliminó la atribución automática de movimientos al usuario 1.
- El administrador recibe permisos operativos al ejecutar el seeder de roles. El visor conserva solo lectura.
- Las bodegas con existencias o movimientos no se eliminan: se desactivan para conservar evidencia.

Archivos principales: `Acceso`, `ClienteRequest`, controladores de clientes/cobros, componentes de inventario/activos/catálogos y seeder de roles.

## 2. Inventario

- Bloqueo de productos en orden de ID y de existencias durante la transacción. El bloqueo del producto también serializa la creación de existencias que aún no existen.
- Cantidades, costos, bodegas, fechas y productos se validan en el servicio, además del formulario.
- PEPS: capas separadas por entrada con fecha, cantidad y costo; las salidas consumen primero las entradas más antiguas.
- Transferencias PEPS: conservan antigüedad y costo de cada capa. No recalculan costos como si fueran compras nuevas.
- Promedio ponderado: utiliza cantidad y valor previos más la entrada.
- Aligación directa: pondera el valor y unidades de las cinco últimas compras, agrupadas por movimiento; no promedia dos costos arbitrariamente.
- Una insuficiencia de stock revierte el movimiento y sus detalles.
- Existencias duplicadas o PEPS anterior sin capas se detienen. No se inventa el costo histórico a partir del precio actual.

Pendiente operativo: conciliar manualmente capas históricas por producto, bodega, lote y costo documental antes de habilitar sus salidas. La migración no reconstruye evidencia inexistente ni altera saldos anteriores.

## 3. Correlativos

`CorrelativoService` utiliza una fila por serie y bloqueo transaccional. Ventas locales, movimientos, créditos, recibos y activos usan este servicio. Las series existentes se consideran al inicializar el contador, incluyendo créditos eliminados lógicamente. Una prueba ejecuta dos procesos PHP contra PostgreSQL y comprueba 50 números sin duplicados.

## 4. Créditos, intereses y mora

- Los créditos nuevos quedan marcados con cálculo `diario`; las cuentas anteriores conservan `historico`.
- El plan nuevo distribuye capital; el interés cobrado se devenga hasta la fecha efectiva del abono, sin cobrar períodos futuros.
- El devengo reconstruye cambios de capital con las aplicaciones fechadas de los pagos.
- Interés simple sobre capital; separa años de 365 y 366 días. No calcula intereses sobre intereses o mora.
- Mora sobre capital vencido después de la gracia. La tasa mensual se expresa anualmente multiplicándola por 12 y se aplica al año calendario.
- Recalcular el mismo corte es idempotente. Se reconocen cuotas con estado `vencida`.
- No se acepta un pago anterior al último pago registrado ni anterior al inicio del crédito. Una regularización de esas fechas requiere revisión del historial.
- Se impide regenerar el plan cuando hay pagos.
- Los recibos históricos se conservan. Los anticipos históricos que entrarían en intereses futuros se detienen hasta una liquidación revisada; no se aplica una condonación o un reintegro silencioso.
- Lo ya cobrado se conserva como evidencia. Posibles cobros históricos indebidos deben analizarse y ajustarse documentalmente; la actualización no los declara correctos ni los devuelve automáticamente.
- `creditos:actualizar-cartera` actualiza devengos y clasificación. Se programa diariamente a las 00:15.

El interés mostrado para cuentas diarias es devengado al último cálculo, no el costo futuro total de un préstamo. Revisar los contratos y la presentación comercial con el asesor responsable antes de contratar créditos reales.

### Topes legales

Fuera del modo académico, los créditos nuevos con interés o mora requieren una entrada vigente en `ERP_CREDIT_LIMITS_JSON`. Debe identificar condición, segmento verificado, montos, fechas y fuente del BCR. La creación y aprobación validan las tasas copiadas al crédito; se conserva el límite usado como evidencia.

Formato de cada entrada (reemplazar todos los marcadores con datos comprobados; no copiar valores ficticios):

```json
{
  "condicion_credito_id": "ID numérico de la condición real",
  "segmento": "Segmento BCR verificado",
  "desde": "AAAA-MM-DD",
  "hasta": "AAAA-MM-DD",
  "monto_minimo": "Número, en USD",
  "monto_maximo": "Número, en USD",
  "tasa_maxima_efectiva": "Número porcentual vigente",
  "fuente": "URL del documento oficial"
}
```

Se exige una cota conservadora del costo anual efectivo combinando interés ordinario y mora. No se implementan cargos adicionales. La tabla debe verificarse por segmento, monto y vigencia: configurar un tope no certifica que el negocio fue correctamente clasificado. Las pruebas y el seeder QA usan topes sintéticos exclusivamente para sus escenarios; el seeder restaura la configuración y está prohibido en producción.

## 5. Ventas, IVA y DTE

En modo universitario se incluye `SimulacionDocumentoService` y la ruta protegida `/facturacion/{venta}/json-academico`. El JSON muestra identificación interna, emisor ficticio, receptor, líneas y totales, con `es_simulacion=true`, `validez_fiscal=false`, `transmitido=false`, firma y sello nulos. No es una validación contra el esquema oficial de un DTE. La ficha del crédito ofrece el enlace cuando el usuario tiene permiso para ver la venta.


- En `01` y ticket `TL`, el precio incluye IVA: por ejemplo, $113 registra $13 de IVA y $113 de total.
- En `03`, se conserva la convención de catálogo sin IVA: $100 registra $13 y $113 de total. La interfaz identifica esta convención.
- Los reportes que descuentan `total_iva` dejan de tratar el total bruto de `01` como venta neta.
- `TL` identifica tickets internos; `11` se reserva para factura de exportación. Los antiguos `11` locales se reclasifican como `TL`, preservando el tipo original y el número previo.
- Los números de nuevos documentos locales comienzan con `LOC`, sin aparentar ser números de control DTE.
- El POS no permite crear notas `05`/`06` como si fueran ventas nuevas. Requieren flujo de corrección y referencia al documento original.
- DTE: ningún JSON parcial se transmite; no hay ambiente de pruebas fijado dentro de una solicitud de producción.
- El trabajo de contingencia tiene intentos limitados y un fallo explícito para documentos sin firma, en lugar de descartarlos silenciosamente.
- El PDF conserva el aviso de documento local y usa el nombre configurado de la aplicación.
- Credenciales del firmador/API cifradas en base de datos y excluidas de serialización. **Conservar APP_KEY en el gestor de secretos y en el plan de recuperación; regenerarla haría ilegibles las credenciales.**

Pendientes fiscales: construir y validar emisor, receptor, cuerpo y resumen contra esquemas vigentes; números de control fiscales; firma; consulta/recepción idempotentes; eventos de contingencia e invalidación; entrega de JSON/PDF; exportaciones y notas; pruebas y autorización MH. No se afirma que los libros de IVA, anexos F-07 o declaraciones estén implementados. Se deben completar o integrar con el sistema contable de la empresa.

No se recalcula retrospectivamente el IVA de ventas existentes: hacerlo podría alterar créditos, recibos y declaraciones. Revisar esas operaciones por período antes de regularizarlas.

## 6. Activos fijos

- Se excluyen vendidos, donados y desechados de los totales de bienes aún mantenidos.
- Inicio de uso separado de adquisición.
- Se registra la clase fiscal y se comprueba el máximo de porcentaje configurado para esa clase. El porcentaje se copia al activo para evitar cambios retroactivos por editar una categoría.
- Para usados se compara el costo adquirido con el techo calculado a partir del precio documentado del bien nuevo y los años de uso. Para maquinaria exenta se registra y aplica el límite de aduana. El residual limita la base registrada.
- Depreciación proporcional a días de uso, con años de 365/366 días; transacción y bloqueo por activo evitan duplicar un período.
- La depreciación automática procesa el último mes cerrado. `activos:depreciar YYYY-MM` permite períodos anteriores, en orden cronológico.
- Activos anteriores sin fecha/base fiscal revisada no se deprecian automáticamente. Se reportan para conciliación.
- El tablero identifica el valor según el registro fiscal; no certifica un valor contable NIIF.

Validar clasificación, documentos, uso en generación de renta y diferencias contables/fiscales con el contador. No se sustituyen esa revisión ni una autorización especial de Hacienda.

## 7. Migraciones y despliegue

Las migraciones antiguas que reemplazan tablas ahora se detienen si encuentran datos. Se evita su eliminación silenciosa; no se inventa una conversión automática del modelo financiero antiguo.

Las dos nuevas migraciones añaden estructura y cifran credenciales. Sus reversiones no borran evidencia financiera ni descifran secretos automáticamente. Un despliegue fallido se recupera con una estrategia revisada de respaldo/restauración.

Secuencia de instalación en staging, con respaldo restaurable y escrituras/colas detenidas:

1. Conservar APP_KEY y la configuración existente por separado del repositorio.
2. Instalar dependencias bloqueadas (`composer install`, `npm ci`) con PHP/extensiones y PostgreSQL compatibles.
3. Ejecutar `php artisan migrate`, `php artisan db:seed --class=RolesAndPermissionsSeeder`, `php artisan optimize:clear` y `npm run build`.
4. Ejecutar `php artisan erp:verificar-integridad`. Código de salida 1 significa que hay pendientes operativos, no que deba ignorarse el aviso.
5. Revisar créditos históricos, capas PEPS, activos y topes legales. No ejecutar seeders demo/QA sobre producción.
6. Instalar el cron de Laravel (`schedule:run` cada minuto) y su worker. La programación en el código no instala servicios del servidor.
7. Probar roles reales, registrar una venta local y un pago de prueba, verificar saldos y reimpresión; luego reabrir escrituras.

## 8. Seguridad, conservación, datos personales e ISO

Se incluye `scripts/respaldar-erp.sh`: PostgreSQL más expedientes privados, manifiesto SHA-256 y cifrado `age`, con permisos privados. Requiere mantenimiento y workers detenidos, cliente PostgreSQL, `age`, `PGDATABASE`/`PGPASSFILE`, `ERP_BACKUP_DIR` y destinatario público `ERP_BACKUP_RECIPIENT`. No elimina copias ni exporta datos a un servicio externo. No incorpora `.env` ni APP_KEY: deben custodiarse separadamente.

La sintaxis del script se verifica; **no se ejecutó contra los datos originales ni se realizó una restauración de producción**. Ver `operacion-y-cumplimiento.md` para el procedimiento y pruebas de recuperación, privacidad e incidentes.

La revisión ISO identifica evidencias técnicas; siguen pendientes el alcance del sistema de gestión, responsables, riesgos, políticas aprobadas, métricas, auditorías y operación comprobada. No se añadió una insignia ni un certificado de conformidad.

## Fuentes y límites

- Código Tributario, artículos 142-A, 143 y 147: https://www.jurisprudencia.gob.sv/DocumentosBoveda/D/2/2000-2009/2000/12/886E5.PDF
- LISR, artículo 30: https://www.jurisprudencia.gob.sv/DocumentosBoveda/D/2/1990-1999/1991/12/888FC.PDF
- Requisitos oficiales DTE: https://factura.gob.sv/2020/09/02/ent1/
- Consumidor, cálculo de intereses: https://www.defensoria.gob.sv/noticias/por-cobros-indebidos-activamos-procedimiento-sancionatorio-contra-salvaprenda-y-maxiprestamos-maxiefectivo/
- Normas BCR: https://www.bcr.gob.sv/regulaciones/upload/Normas_Tecnicas_para_la_Aplicacion_de_la_Ley_Contra_la_Usura.pdf
- Datos personales: https://www.jurisprudencia.gob.sv/DocumentosBoveda/D/2/2020-2029/2024/11/10660E.PDF

Estos cambios no cubren todas las leyes de una empresa ni acreditan su actividad, registro tributario, contratos o declaraciones. La actividad concreta determina obligaciones adicionales laborales, municipales, mercantiles, de historial crediticio o de prevención de lavado.

## Resultado final de verificación

- Suite: 252 pruebas, 2.018 verificaciones, sin fallos.
- Compilación con Vite 7.3.8: correcta.
- `npm audit`: cero vulnerabilidades reportadas después de actualizar el archivo de bloqueo.
- `git diff --check` y sintaxis del script de respaldo: correctos.
- Base local: nuevas migraciones y roles aplicados; escenarios ficticios QA cargados sin reiniciar tablas.
- Acceso con perfil QA administrador y diez pantallas principales: HTTP 200.
- JSON didáctico: HTTP 200, simulación verdadera, validez fiscal falsa, sin transmisión.
- Diagnóstico local: sin errores; informa siete escenarios QA históricos intencionales para ensayar conciliación.
- No se declara una certificación ISO ni una homologación DTE. La simulación es el alcance acordado.
