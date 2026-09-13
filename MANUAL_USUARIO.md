# Manual de Usuario - ERP Análisis Financiero

Bienvenido al manual de usuario del sistema ERP de Análisis Financiero. Este documento le guiará a través de cada módulo del sistema, abarcando tanto el paso a paso operativo como el significado gerencial de los datos, con especial énfasis en las políticas de crédito y el proceso de facturación electrónica.

---

## 1. Dashboard Principal

El Dashboard es el centro de control del sistema y le da la bienvenida al iniciar sesión.

### Nivel Operativo (Cómo usarlo)
- **Navegación:** En el panel izquierdo (sidebar) encontrará los accesos directos a todos los módulos: Clientes, Inventario, Créditos, Cobros, Facturación POS y Activos Fijos.
- **Visualización:** La pantalla principal muestra tarjetas de resumen con las métricas más importantes actualizadas en tiempo real.

### Nivel Gerencial (Qué significa)
- **Resumen Financiero:** Las cifras principales como "Total Cartera Activa", "Ventas del Mes" y "Mora" le permiten medir la salud financiera de la empresa de un vistazo. Un aumento repentino en la mora, por ejemplo, es un indicador para revisar el módulo de Cobros y ajustar políticas.

---

## 2. Módulo de Clientes (Cuentas por Cobrar)

Este módulo centraliza la información de los clientes con los que la empresa tiene relaciones comerciales.

### Nivel Operativo
1. **Crear Cliente:** Haga clic en "Nuevo Cliente". Llene los datos generales.
2. **Datos Fiscales (¡Crítico para Facturación!):** Asegúrese de ingresar correctamente el NIT, el NRC (si aplica), giro y dirección. 
3. **Mantenimiento:** Puede editar o eliminar (si no tiene transacciones asociadas) los registros desde la tabla principal.

### Nivel Gerencial
- **Base de Datos Confiable:** Mantener esta base depurada garantiza que la facturación electrónica DTE (Ministerio de Hacienda) no arroje errores por falta de datos, y que los análisis de riesgo crediticio se asignen al contribuyente correcto.

> [!WARNING]
> Un cliente sin NIT o NRC válido no podrá ser receptor de un Comprobante de Crédito Fiscal (CCF) en el módulo de Facturación.

---

## 3. Módulo de Créditos y Amortizaciones

Aquí se gestionan los préstamos y financiamientos otorgados a los clientes. 

### Política de Otorgamiento de Créditos (REGLA DE NEGOCIO)
> [!IMPORTANT]
> Todo crédito otorgado en el sistema debe pasar por una evaluación previa. El sistema registrará el monto, la tasa de interés aplicable y el plazo en meses. 
> - **Frecuencia de pago:** Semanal, Quincenal o Mensual.
> - **Intereses:** El sistema genera automáticamente la tabla de amortización distribuyendo capital e intereses según el método establecido.

### Nivel Operativo
1. **Nuevo Crédito:** Vaya a "Créditos" > "Nuevo Crédito". Seleccione el cliente, ingrese el monto, tasa y plazo. El sistema proyectará la tabla de cuotas.
2. **Tabla de Amortización:** Al visualizar un crédito, verá el calendario de pagos proyectado.
3. **Pago de Cuotas:** Seleccione la cuota a pagar y haga clic en "Registrar Pago". El estado de la cuota cambiará a "Pagado" y el saldo del capital se reducirá.

### Nivel Gerencial
- **Capital vs. Intereses:** La tabla de amortización permite proyectar los ingresos financieros (intereses) futuros mes a mes, crucial para el flujo de caja.

---

## 4. Módulo de Cobros y Mora

Este módulo controla la salud de la cartera y advierte sobre cuentas vencidas.

### Nivel Operativo
- **Dashboard de Cobranza:** Muestra la cartera dividida en: Vigente, Mora (1-30 días, 31-60 días, etc.).
- **Reclasificación Masiva:** Existe un botón de acción para ejecutar la reclasificación automática. Al presionarlo, el sistema evalúa todas las cuotas impagas y mueve los créditos de categoría según los días de retraso reales.

### Nivel Gerencial
- **Riesgo de Liquidez:** La clasificación por días de mora (Aging) permite identificar rápidamente qué proporción del capital está en riesgo. Las cuentas con más de 90 días requieren acciones legales o provisiones por incobrabilidad que impactan directamente el balance de la empresa.

---

## 5. Módulo de Inventario

Gestión de existencias, valorización y entradas/salidas de productos.

### Nivel Operativo
- **Catálogo:** Puede crear productos, asignando un código SKU, costo unitario y precio de venta (incluyendo el cálculo automático de IVA).
- **Movimientos:** Para ingresar nueva mercadería, registre una "Entrada" (compra). Para ajustes o pérdidas, registre una "Salida". Las ventas del módulo POS rebajan el inventario de forma automática.

### Nivel Gerencial
- **Control de Capital Inmovilizado:** El indicador de "Valor Total de Inventario" le muestra cuánto capital está invertido en mercadería. Un inventario alto sin rotación puede significar pérdida de rentabilidad.

---

## 6. Módulo de Facturación DTE (Punto de Venta)

El módulo más transaccional, conectado directamente con la facturación electrónica del Ministerio de Hacienda (MH).

### Nivel Operativo (Paso a paso)
1. **Buscar y Agregar Productos:** Utilice el buscador para seleccionar artículos. Modifique las cantidades desde el carrito de compras a la derecha.
2. **Selección de Documento:** Elija si emitirá Factura de Consumidor Final (Ticket) o Comprobante de Crédito Fiscal (CCF).
3. **Selección del Cliente:** 
   - Para Ticket: Puede dejarlo en "Consumidor Final" o seleccionar uno.
   - Para CCF: Es **obligatorio** seleccionar un cliente con NRC y NIT registrados.
4. **Condición de Pago:** Elija "Contado" o "Crédito". Si es crédito, el sistema generará una Cuenta por Cobrar.
5. **Emitir y Firmar:** Haga clic en "Emitir Factura DTE". El sistema validará los datos, calculará el IVA, transmitirá el documento al MH, aplicará la firma electrónica y descontará el inventario.

### Reglas de Negocio en Facturación (¡Atención!)
> [!CAUTION]
> - **Cálculos de IVA:** El sistema calcula el Débito Fiscal (13%) automáticamente al emitir CCF.
> - **Contingencia Automática:** Si el servicio del MH está caído temporalmente, el sistema aplicará la contingencia permitida por ley, pero **usted debe asegurarse** de que los datos del cliente estén completos para evitar rechazos en el lote posterior.
> - **Restricción de Stock:** No se puede facturar un producto si la cantidad requerida supera el stock disponible en inventario.

### Nivel Gerencial
- **Ventas y Fiscalidad:** Cada DTE emitido representa un ingreso formal. Este módulo alimenta directamente el cálculo de impuestos mensuales. Asegura el cumplimiento legal y la trazabilidad de cada centavo vendido.

---

## 7. Módulo de Activo Fijo

Control del patrimonio de la empresa en bienes de uso.

### Nivel Operativo
- **Alta de Activos:** Registre escritorios, computadoras, vehículos, etc., indicando la fecha de adquisición y el costo inicial.
- **Cálculo de Depreciación:** El sistema aplicará las fórmulas de depreciación mensual o anual según la vida útil de cada bien.

### Nivel Gerencial
- **Valor en Libros:** Permite conocer cuánto valen realmente los bienes de la empresa hoy, restando la depreciación acumulada. Este dato es vital para los estados financieros formales y el cálculo de impuestos sobre la renta.
