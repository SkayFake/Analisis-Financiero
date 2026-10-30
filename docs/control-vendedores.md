# Control comercial y de cartera por vendedor

## Actualizar

```bash
git pull origin main
php artisan migrate
php artisan optimize:clear
```

La migración añade metas mensuales, bitácora, gestiones y atribución del vendedor en recibos. No cambia importes, cuotas, saldos ni documentos fiscales y no requiere Hacienda.

En una instalación existente importa `meta_mensual` únicamente para el mes actual, como meta de ventas; cobros empieza sin objetivo. No inventa metas de meses anteriores.

Para probar los nuevos casos:

```bash
php artisan db:seed --class=PruebasIntegralesSeeder
```

Si QA ya existía, se añade su extensión de vendedores una sola vez sin restablecer pagos ni ediciones. También puede ejecutar `php artisan db:seed --class=VendedoresPruebasSeeder` después del principal. **QA-META / QA Metas y seguimiento** muestra metas cumplidas, un recibo conservado tras una reasignación y promesas cumplidas, pendientes e incumplidas. Se conserva el escenario del abono de $300.

## Flujo operativo

1. Cree el vendedor con código, contacto y zona. Su meta inicial de ventas se registra para el mes actual. La ficha permite fijar metas de ventas y cobros por separado.
2. Asigne el asesor del cliente. El POS lo propone al seleccionar el cliente y exige un vendedor activo al facturar. Puede elegirse otro vendedor para esa venta.
3. El crédito conserva el vendedor de la venta y el responsable de cartera por separado. Un crédito manual exige un responsable si no puede tomarlo de la venta o del cliente. La cartera debe corresponder al responsable o no tener vendedor exclusivo.
4. Desde la ficha consulte productos vendidos, documentos, recibos, clientes con cuotas vencidas, contactos y último pago. Registre gestiones de teléfono, visita, mensaje, correo u oficina, con resultado y notas.
5. Un supervisor puede reasignar un crédito abierto a otro vendedor activo, indicando motivo y cartera de destino si corresponde. Queda registro para ambos vendedores. No cambia venta original, recibos anteriores, cuotas, saldos ni ficha del cliente.
6. Revise cumplimiento de metas, compromisos y bitácora; exporte el informe para conservar evidencia del corte.

Editar, desactivar, modificar metas y reasignar exige motivo. El vendedor no se elimina. Desactivar impide nuevas ventas y asignaciones, conserva historial y permite cobrar deudas anteriores. La cartera de un vendedor inactivo puede transferirse desde su ficha.

Cambiar el asesor del cliente requiere permiso y motivo y afecta su ficha y nuevas operaciones. No traslada créditos existentes. Si no se elige una cartera compatible para el nuevo asesor, la asignación anterior de cartera se retira; no se conserva una cartera de otro encargado por accidente.

## Indicadores

| Indicador | Definición |
| --- | --- |
| Ventas del mes sin IVA registrado | `total_pagar - total_iva` de documentos 01, 03 y 11 locales o procesados, por fecha de emisión y vendedor de la venta. Al crédito, cuenta una vez activado el crédito. Excluye solicitudes pendientes/rechazadas y documentos sin emisión válida. |
| Cobros del mes | Recibos por fecha de pago y vendedor a cargo al registrarlos; incluye capital, interés y mora. No suma ventas de contado. |
| Persona que recibió el pago | `recibido_por`, independiente del vendedor al que se atribuye el recibo. |
| Capital de cartera actual | Saldo de créditos vigentes, vencidos e incobrables actualmente a cargo. Excluye cerrados y refinanciados para no duplicar deuda transferida. |
| Deuda total actual | Cuotas pendientes con interés y mora registrada, conservando al menos el capital si faltara un plan consistente. |
| Cuotas vencidas actuales | Solo lo pendiente de cuotas con vencimiento anterior a hoy, descontando abonos. No equivale a todo el capital del crédito. Se calcula desde cuotas, sin depender de `dias_mora` actualizado. |
| Capital incobrable | Parte de la cartera en estado incobrable; conserva su deuda. |
| Clientes con atraso | Clientes distintos con cuotas vencidas; dos créditos del mismo cliente no duplican su conteo en la ficha. |
| Productos | Primeros 20 productos por importe de líneas de las ventas computables; los documentos permiten consultar el detalle completo. |

Un importe de meta cero significa sin objetivo; un mes sin registro se muestra sin meta. Cambiar una meta del mes actual no sustituye las anteriores.

Se usa el IVA **registrado** por la facturación existente: no se recalculan impuestos incluidos en precios ni se implementa compensación de notas de crédito/débito. Los documentos 05 y 06 no se computan como ventas. Este cálculo no sustituye una determinación fiscal de ventas netas.

El mes filtra ventas, recibos y metas. La cartera siempre es **actual**, con fecha y hora visibles, y no se presenta como un saldo histórico de cierre. Las ventas reflejan el estado actual de documentos y aprobaciones. La exportación conserva el corte y su resumen en bitácora; no implementa un cierre contable mensual irreversible.

## Promesas de pago

Solo el resultado `promesa_pago` admite fecha e importe: fecha de hoy o posterior e importe positivo, de dos decimales, no superior a la deuda pendiente. Gestiones y promesas no modifican deuda ni generan pagos.

Se comprueban recibos nuevos del mismo crédito: ID posterior al último pago existente al registrar la gestión y fecha de pago desde el día de la gestión. Los abonos anteriores y los recibos ingresados tarde con fecha anterior no cumplen una promesa nueva.

- **Pendiente:** no vence y no se cubrió el importe.
- **Cumplido:** recibos fechados hasta el compromiso cubren el importe.
- **Incumplido:** venció sin cubrirse.
- **Cumplido fuera de plazo:** se cubrió con recibos posteriores al vencimiento.

Son compromisos independientes de seguimiento: no reservan dinero ni se suman a la deuda. Para ampliar o corregir se registra otra gestión, conservando la anterior. Al reasignar, el nuevo responsable puede consultar las gestiones previas; también permanecen en la ficha del vendedor que las registró.

## Revisión de datos anteriores

Antes del módulo, los recibos no tenían fotografía del responsable. La migración toma la asignación actualmente documentada en el crédito y la marca **atribución histórica reconstruida**. Debe revisarse si hubo traslados anteriores no registrados.

El origen del crédito se toma de la venta. Si falta, se utiliza la asignación del crédito con una marca de reconstrucción. Sin evidencia queda sin documentar; no se elige un vendedor cualquiera.

**Control de vendedores → Revisar asignaciones** permite:

- Documentar el vendedor de ventas sin atribución y el origen faltante o reconstruido de sus créditos. Conserva el responsable actual de cobro.
- Asignar responsables a créditos abiertos sin encargado.
- Revisar recibos sin atribución o reconstruidos, con evidencia y motivo. Puede corregirse su atribución histórica, conservando la marca de reconstrucción y registrando fecha y responsable en bitácora. No cambia importes ni saldo.

Un recibo revisado sale de pendientes. Desde su ficha puede volver a **Revisar atribución** si aparece evidencia nueva, registrando otro evento. Los recibos nuevos con atribución capturada al cobrar no admiten regularización histórica. Reasignar un crédito tampoco traslada sus recibos anteriores.

## Bitácora e informes

Se conserva acción, entidad e ID, valores anteriores/posteriores, motivo, usuario con nombre registrado y fecha del servidor: altas, cambios, metas, asignaciones, gestiones, regularizaciones y exportaciones.

Los modelos no editan ni eliminan bitácoras o gestiones. PostgreSQL también rechaza `UPDATE` y `DELETE` directos sobre esas tablas mediante triggers. La reversión de la migración se bloquea si existe historial. Es trazabilidad interna, sin firma digital, sellado externo o almacenamiento independiente de administradores de base de datos.

CSV de resumen, cartera, ventas, cobros y bitácora y PDF de control incluyen período y corte. PostgreSQL toma una fotografía consistente en la transacción de exportación. Se registra quién exportó y los totales; el CSV protege celdas interpretables como fórmulas.

| Permiso | Operación |
| --- | --- |
| `vendedores.view` | Consultar panel, fichas y pendientes |
| `vendedores.manage` | Alta, edición y desactivación |
| `vendedores.metas` | Registrar o cambiar metas |
| `vendedores.assign` | Reasignaciones y regularizaciones |
| `vendedores.gestionar` | Gestiones y compromisos |
| `vendedores.export` | Exportar, además del permiso de consulta |
| `vendedores.audit` | Consultar bitácora; exportarla requiere exportación |

La migración habilita el conjunto para administradores y gerentes existentes; agentes reciben consulta/gestión y lectores reciben consulta. Se aplica también a los perfiles QA. No necesita volver a ejecutar todos los seeders de roles.

## Pruebas

`ControlVendedoresTest` cubre cálculos sin duplicación, IVA registrado, exclusiones, metas por mes, atribución y cartera tras reasignar, permisos HTTP/Livewire, desactivación, clientes, promesas y fechas, regularización, migración conservadora, bitácoras protegidas y CSV/PDF reales. La suite integral comprueba el seeder ampliado y su repetición sin restablecer datos manuales.

Ejecute tests únicamente en una base PostgreSQL exclusiva para pruebas: sus migraciones pueden vaciarla.
