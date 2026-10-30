# Datos para pruebas integrales

`PruebasIntegralesSeeder` crea un conjunto identificado con **QA** para recorrer los módulos del ERP. Funciona sobre una base migrada vacía o con datos existentes. Es independiente del antiguo `DemoDataSeeder` y no lo ejecuta.

## Ejecutar

Desde la carpeta del proyecto, con `.env` apuntando a la base local donde desea probar:

```bash
git pull origin main
php artisan migrate
php artisan db:seed --class=PruebasIntegralesSeeder
```

**No necesita `migrate:fresh`: ese comando borraría la base.** El seeder no trunca tablas, no borra datos y no cambia cuentas, roles, activos ni configuración DTE anteriores. Solo añade registros propios y permisos faltantes. Se bloquea si el entorno es `production` porque crea accesos de prueba con contraseña conocida.

La carga completa se realiza en una transacción. Si falla, se revierte. Las ejecuciones simultáneas se serializan en PostgreSQL. Al terminar se imprime una tabla que relaciona cada escenario con el número de crédito generado, su estado y saldo.

Una segunda ejecución conserva el conjunto existente: **no duplica datos ni deshace pagos, ediciones o reactivaciones que haya realizado al probar**. La existencia de `qa.admin@example.test` identifica el conjunto ya cargado. No cambie ese correo si desea conservar esta detección. No se reparan conjuntos parcialmente borrados; para empezar de cero use otra base de pruebas vacía y migrada.

No se agrega a `DatabaseSeeder` para evitar cargarlo accidentalmente o mezclarlo con el demo antiguo, que genera algunas identidades aleatorias.

## Accesos

Contraseña para los ocho usuarios: **`PruebasSV!2026`**.

| Correo | Perfil para probar |
| --- | --- |
| `qa.admin@example.test` | Todos los permisos del sistema |
| `qa.gerente@example.test` | Créditos, aprobación, cobros, declaración y reactivación de incobrables |
| `qa.cobros@example.test` | Consulta de créditos e incobrables; registro de pagos |
| `qa.facturacion@example.test` | Venta al contado con precio de catálogo; sin creación de crédito ni cambio de precio |
| `qa.inventario@example.test` | Operaciones de inventario |
| `qa.activos@example.test` | Activos fijos y bajas |
| `qa.lector@example.test` | Permisos de consulta |
| `qa.sinpermisos@example.test` | Sin permisos; para comprobar rechazos de acceso |

Se crean roles propios `qa-*`; no se asignan permisos nuevos a los roles existentes del usuario.

## Créditos y pagos

Busque el nombre del caso en Clientes o Créditos, por ejemplo **PARCIAL-300**. Las observaciones del crédito contienen `QA-<CASO>`. Los números de crédito, recibo y factura se generan mediante los servicios; no dependen de IDs fijos.

| Caso | Situación inicial y prueba sugerida |
| --- | --- |
| SIN-ABONOS | $600, seis cuotas sin interés; registrar el primer pago |
| UNA-CUOTA / MINIMO | Una sola cuota de $100 o $0.01; comprobar importe mínimo y sobrepago |
| PARCIAL-300 | $1,129.98; abono de $300: primera cuota pagada, segunda abonada $111.67; quedan $76.66 en esa cuota y $829.98 de capital |
| CON-INTERES | $1,000 al 18% anual; abono de $100 por transferencia; separar capital e interés |
| SEMANAL / QUINCENAL | Planes de 12 y 8 cuotas con sus frecuencias |
| PLAZO-120 | Plan de 120 cuotas para comprobar el límite y una tabla larga |
| REDONDEO | $100 en tres cuotas; después de abonar $99.99 queda $0.01 |
| FIN-MES / BISIESTO | Primera cuota al cierre del mes o el próximo 29 de febrero válido |
| LIQUIDADO | Crédito con interés completamente pagado; historial con efectivo, transferencia, cheque y tarjeta |
| HISTORICO | Pago anterior al detalle por cuota, con conceptos y saldos conciliables; el PDF informa que falta detalle y el conciliador puede reconstruirlo |
| PENDIENTE | Revisión manual por el límite de autoaprobación |
| NUEVO-REVISION | Sin compras canceladas previas; la condición exige una para autoaprobar |
| RECHAZADO | Solicitud rechazada con responsable y motivo; sin plan activo |
| APROBADO-TRANSITORIO | Aprobado todavía sin activar; fixture de consulta y bloqueo de cobros |
| APROBACION-MANUAL | Aprobado por el servicio con motivo, responsable y plan generado |
| EXPEDIENTE-INCOMPLETO / SIN-DUI | Verificación, documento o fiador pendientes; no debe poder aprobarse |
| CON-FIADOR | Expediente completo con DUI y fiador; aprobado manualmente |
| ANTECEDENTE / FRECUENTE-AUTO | Compra previa cancelada y nueva compra autoaprobada del mismo cliente |
| MORA-A / MORA-B / MORA-C / MORA-D | Cuatro clasificaciones de mora; MORA-B también tiene un abono |
| CORPORATIVO | Crédito de $2,500 de un cliente jurídico |
| REFINANCIADO / REF-NUEVO | Crédito original y nuevo saldo, sin crear una segunda venta de mercadería |
| EMBARGO-ACTIVO / EMBARGO-EJECUTADO / EMBARGO-LIBERADO | Tres estados de embargo vinculados a créditos vencidos |

Los créditos normales nacen de ventas del servicio local y rebajan inventario. Cuotas, aprobaciones, rechazos y pagos usan los servicios existentes. Las operaciones antiguas se generan bajo un reloj temporal que se restaura; no se desactivan validaciones de fechas. Los escenarios de mora usan fechas relativas al día de carga.

El estado `aprobado`, el refinanciamiento y los embargos son **fixtures de tablas y relaciones**: la aplicación aún no expone un flujo completo de activación, refinanciamiento o ejecución judicial. El seeder no implementa esos flujos. Un embargo ejecutado no registra automáticamente una venta del bien ni un abono. No existe el estado de crédito `embargado` en el esquema comercial actual; esos créditos permanecen vencidos y tienen su embargo relacionado.

Para probar el caso histórico:

```bash
php artisan creditos:conciliar-pagos
php artisan creditos:conciliar-pagos --credito=ID_DEL_CREDITO_HISTORICO --aplicar
```

El primer comando solo comprueba. Obtenga el ID de la URL del crédito. El conciliador también se ejecuta antes de otro abono y conserva los recibos originales.

## Cuentas incobrables

| Caso | Situación inicial |
| --- | --- |
| INCOBRABLE | Cuenta abierta con interés, mora y abono previo; conserva la deuda |
| REACTIVADO | Traslado de hace cinco días, reactivación actual y nuevo abono; registro anterior conservado |
| REINCIDENTE | Dos traslados del mismo crédito con una reactivación y abono entre ambos |
| DOS-INCOBRABLES | Dos créditos incobrables del mismo cliente; reactivar uno no debe quitar D mientras quede el otro |

Inicialmente hay **cuatro registros abiertos y dos cerrados por reactivación**. Pruebe los filtros de abiertas, reactivadas y todo el historial. Un crédito incobrable no admite pagos hasta reactivarlo con motivo y permiso adecuados.

## Clientes, catálogos e inventario

- Naturales con contactos salvadoreños, DUI con verificador y NIT homologado; ejemplos sin DUI, inactivo y archivado. Son identidades sintéticas que pasan formato y verificador, sin afirmar que fueron emitidas por una autoridad.
- Jurídicos SOLVENTE, PERDIDA y SIN-OPERACION con NIT, NRC, representante, balances equilibrados y ratios calculados. El último permite probar ratios no disponibles y divisiones por cero.
- Zonas, vendedores, carteras, políticas y condiciones activas e inactivas; proveedores, categorías, marca, unidades y bodegas.
- `QA-BASE`, `QA-ULTIMA`, `QA-ALIGACION`, `QA-PEPS`: los cuatro métodos de costeo; los tres últimos tienen dos entradas a costos distintos.
- `QA-BAJO`: 2.5 unidades con mínimo de cinco; `QA-AGOTADO`: cero; `QA-INACTIVO`: deshabilitado; `QA-FRACCION`: cantidades fraccionarias; `QA-VENCE` y `QA-VENCIDO`: lotes por vencer y vencidos.
- Entradas, salidas por ventas y merma, transferencia y ajuste positivo documentado. Los detalles y existencias concilian por producto, bodega y lote. El ajuste es un fixture porque no tiene acción propia en la interfaz.

El costeo se ejecuta como lo implementa el servicio actual. Los dos lotes no certifican que el sistema ya implemente valoración PEPS por costo individual de cada lote.

## Ventas y Hacienda

Hay ventas de contado y crédito, documentos locales 01, 03 y 11 y detalles de mercadería. En Historial de Pagos puede imprimir recibos PDF, incluso en créditos liquidados e incobrables.

Se añaden fixtures de documento pendiente, contingencia y rechazo; un lote pendiente y una invalidación rechazada. Se rotulan **QA SIMULACIÓN LOCAL**, sin firma ni autorización de Hacienda. El esquema de eventos exige el sello original: ese único registro contiene el marcador inválido `QA-SIN-SELLO-NO-TRANSMITIR`, no un sello MH. No se crean aceptaciones fiscales ficticias.

Si la configuración DTE está vacía, se crea un emisor sintético de ambiente de pruebas, sin credenciales y con URL `.invalid`. Si ya existe, se conserva. La integración se deshabilita solo en memoria durante el seeder y se restaura al salir, incluso si falla. **No se envían solicitudes ni se encolan transmisiones.** No cambia `.env`.

## Activos fijos

Una institución QA, dos unidades, cuatro categorías propias y once activos: nuevo, usados de uno a cuatro años, maquinaria importada, edificio, completamente depreciado, vendido, donado y botado.

El servicio calcula el historial mensual hasta el mes anterior, limitado a los activos QA; los anteriores quedan intactos. El mes actual queda disponible para probar el cálculo desde la interfaz. Las bajas guardan fecha y motivo.

## Control de vendedores

El conjunto incluye metas mensuales, bitácora y gestiones. La extensión se añade una sola vez si QA ya existía y no restablece sus movimientos. Consulte [Control de vendedores](control-vendedores.md) para probar QA-META, reasignaciones que conservan recibos anteriores y promesas cumplidas, pendientes e incumplidas.

## Tablas y pruebas automatizadas

Se pueblan **44 tablas de negocio y permisos**: usuarios y roles/permisos; catálogos de cobros; clientes y datos jurídicos; créditos, cuotas, pagos, aplicaciones, fiadores, clasificaciones, incobrables, refinanciamientos y embargos; catálogos de inventario, existencias, movimientos y detalles; ventas y detalles; instituciones, unidades, categorías de activos, activos y depreciaciones; configuración DTE, contingencias e invalidaciones.

Las tablas de infraestructura —migraciones, caché, sesiones, trabajos y trabajos fallidos— se dejan a Laravel. No se insertan sesiones falsas ni trabajos que puedan ejecutarse. `productos_credito` fue eliminada por las migraciones comerciales; su sustituto es `condiciones_credito`.

Las entradas inválidas se comprueban con las pruebas existentes de formularios y permisos; no se guardan deliberadamente como registros normales.

En una base PostgreSQL **exclusiva para pruebas automatizadas**:

```bash
DB_DATABASE=analisis_financiero_testing php artisan test --compact
```

La suite puede vaciar esa base. No la dirija a la base de pruebas manuales que desee conservar. `PruebasIntegralesSeederTest` comprueba cobertura de tablas, identidades, balances, capital/cuotas/pagos, inventario, depreciaciones, estados, reejecución, conservación de datos anteriores, reversión, ausencia de llamadas externas, consulta de módulos y PDFs. También carga los casos en fin de mes y en un 29 de febrero.


## Expedientes documentales

El seeder también agrega comprobantes PDF sintéticos privados del cliente, crédito y fiador, con versiones, rechazos, vencimientos y pendientes. Consulte [la matriz y el flujo documental](expedientes-credito-el-salvador.md). Los nuevos archivos se agregan incrementalmente al repetir el seeder; no se restablecen las revisiones manuales.
