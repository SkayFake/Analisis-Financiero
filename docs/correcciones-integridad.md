# Correcciones de integridad y acceso

## Aplicación en un entorno existente

Conserve una copia de seguridad de la base antes de aplicar migraciones. No use `migrate:fresh` sobre datos existentes.

Desde la raíz del repositorio, con PHP y las credenciales de su base configurados:

```bash
php artisan migrate
php artisan creditos:conciliar-pagos
```

El segundo comando comprueba los recibos históricos sin guardar cambios. Para reconstruir los que concilian exactamente:

```bash
php artisan creditos:conciliar-pagos --aplicar
```

Puede limitar ambos comandos a un crédito mediante `--credito=ID`. La repetición es idempotente. El comando informa de los créditos bloqueados y devuelve un estado de error si alguno requiere revisión.

Los nuevos abonos también intentan conciliar los recibos antiguos del mismo crédito dentro de la transacción. Se mantienen intactos los importes de los recibos, saldos y pagos acumulados de cuotas. Si un recibo no identifica su cuota, los conceptos exceden lo previsto en ella, o los totales no cuadran, se bloquea el nuevo abono. Esos casos requieren reconstrucción contable a partir de sus comprobantes; no se inventan distribuciones entre cuotas.

La migración del historial añade los campos de clasificación por cliente y conserva los registros existentes por crédito, incluyendo sus observaciones y responsables. Su reversión se bloquea si ya existen eventos exclusivamente de cliente, para evitar pérdida de información.

## Permisos de facturación

- Acceso al punto de venta y PDF: `facturacion.view` o `facturacion.emitir`.
- Registrar ventas, incluidas las de contado: `facturacion.emitir`.
- Ventas al crédito: además requieren `creditos.create`.
- Cambiar el precio de catálogo: `facturacion.modificar_precio`, nuevo permiso registrado por migración. No se asigna automáticamente a los operadores existentes. El superadministrador conserva el acceso.

Las verificaciones se ejecutan en el servidor. El servicio valida cada producto, cantidad, precio, bodega y cliente, aunque sea llamado sin Livewire.

## Documentos fiscales

Se eliminaron las firmas y sellos ficticios. Las pruebas deben usar mocks o `Http::fake`, nunca una configuración de simulación que pueda activarse en operación real.

El proyecto funciona por defecto sin Hacienda (`ERP_DTE_ENABLED=false`, también por defecto cuando no existe la variable). Se registran ventas al contado, ventas al crédito, cuotas y pagos; los PDF son comprobantes locales y no necesitan servicios ni credenciales externos. La pantalla y los comprobantes muestran el estado **local**, sin sellos ni firmas ficticios. Tampoco se ejecutan transmisiones de contingencia mientras la integración esté deshabilitada.

Para la futura integración existe `ERP_DTE_ENABLED=true`. Ese modo requiere configuración DTE y del firmador; si falta, la operación se revierte y muestra el error. Los documentos locales existentes no se convierten automáticamente en DTE al habilitar la integración. La migración identifica los sellos ficticios de demostraciones anteriores y los reclasifica como documentos locales, preservando las autorizaciones históricas que no tengan ese sello ficticio.

La integración futura todavía requiere completar el constructor del JSON DTE y validar los esquemas y el ambiente de pruebas oficiales antes de su uso real. Esto no bloquea las funciones locales del proyecto académico. Las pruebas automatizadas no transmiten documentos.

## Acceso y pruebas

El login limita a cinco intentos fallidos por cuenta y veinte por IP durante un minuto. Las cuentas comparten contador independientemente de mayúsculas; un acceso correcto limpia el contador de la cuenta. Las respuestas bloqueadas incluyen HTTP 429 y `Retry-After`.

Prepare una base PostgreSQL exclusiva para pruebas y ejecute, sustituyendo el nombre y las credenciales por los de esa base:

```bash
DB_DATABASE=analisis_financiero_testing php artisan test
npm run build
```

`CorreccionesIntegridadTest` cubre recibos históricos y casos ambiguos, conservación del historial, permisos y entradas manipuladas en Livewire, precios, configuración DTE, ficha de cliente, reclasificación y límites del login. La suite usa migraciones y puede vaciar la base de pruebas: nunca la dirija a producción ni a una base con datos del usuario.
