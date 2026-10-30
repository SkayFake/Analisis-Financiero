# Cartera de incobrables y recibos de pago

## Actualización

```bash
git pull origin main
php artisan migrate
php artisan optimize:clear
```

No requiere nuevos paquetes ni conexión con Hacienda. La migración crea `cuentas_incobrables` e incorpora los créditos que ya estén en estado incobrable. Para esos registros antiguos se indica que no están disponibles la fecha, el responsable y el motivo originales, sin inventarlos. Se conserva su saldo y plan. La reversión de la migración se bloquea cuando hay registros para evitar perder historial.

## Operación

- En el detalle del crédito, **Trasladar a incobrables** exige un motivo de 10 a 2000 caracteres. Admite créditos vigentes o vencidos con deuda pendiente. Es una decisión manual del responsable autorizado, sin un umbral automático de mora ni una exigencia legal de días incorporada.
- En **Cuentas incobrables** (menú lateral y acceso desde Cobros) se consultan cuentas abiertas, reactivadas o todo el historial. Permite buscar por cliente, DUI, código o crédito. Los importes son una fotografía al traslado: capital y total pendiente del plan más mora registrada; si el historial no tiene cuotas suficientes, se conserva como mínimo el capital pendiente.
- Cada traslado guarda estado anterior, motivo, responsable, fecha y saldos. No borra deuda ni modifica cuotas, pagos o factura de origen. No contabiliza una provisión, castigo ni condonación.
- Para volver a registrar pagos se usa **Reactivar cobro**, con motivo obligatorio. Se retoma el mismo saldo y plan: vencido si tiene cuotas impagas con vencimiento anterior a hoy; vigente en otro caso. No se regeneran cuotas, intereses ni recargos al reactivar. La mora posterior sigue el cálculo existente del sistema.
- La reactivación cierra el registro conservando motivo, fecha y responsable. Un nuevo traslado genera otro registro. La clasificación automática del cliente conserva D mientras tenga otro crédito incobrable.
- Las operaciones usan transacción y bloqueo del crédito, cliente y cuotas. El estado se vuelve a comprobar dentro de la transacción; una solicitud repetida no duplica el traslado ni la reactivación. La base impide dos registros abiertos para el mismo crédito.

Permisos existentes: `incobrables.view` consulta la cartera y su historial; `incobrables.classify` traslada; `incobrables.reactivate` reactiva. Para consultar el detalle completo del crédito y sus comprobantes se requiere `creditos.view`. Superadministradores tienen acceso. Se reutilizan los permisos que ya define el seeder; no es necesario volver a ejecutar el seeder de roles en una instalación existente.

## Comprobante por pago

En **Historial de Pagos → Imprimir recibo** se abre un PDF en otra pestaña. Desde el visor PDF del navegador puede imprimirse o guardarse. Funciona también después de liquidar un crédito o de trasladarlo a incobrables.

El recibo muestra número, fecha, cliente, crédito, factura de origen, forma de pago, referencia, responsable, capital, interés, mora, total y aplicación a cada cuota. El saldo es el capital guardado después de ese pago, aunque posteriormente se hayan registrado más abonos. Las observaciones se escapan como texto. La consulta no escribe ni concilia datos.

Si un pago histórico no tiene aplicaciones conciliadas, el PDF conserva sus importes e indica que falta ese detalle. Muestra la cuota original cuando existe y no inventa una asignación. No imprime los demás pagos ni permite acceder a un pago de otro crédito mediante una URL alterada.

Es un recibo interno del proyecto académico **sin autorización fiscal**. No genera una nueva factura de venta, no duplica el IVA ni transmite datos a Hacienda, incluso si se activa la integración DTE en el futuro.

## Validación

Pruebas de integración en PostgreSQL: traslado con abonos anteriores, estados y motivos inválidos, permisos separados, duplicados, reactivación, varios créditos del mismo cliente, historial de varios ciclos, incorporación de registros antiguos, búsqueda y filtros, clientes archivados, generación real de PDF, pago ajeno, reimpresión histórica y ausencia de llamadas externas. Se ejecutan junto con la suite existente.
