# Validaciones de formularios para El Salvador

El proyecto académico sigue funcionando sin conexión con Hacienda. Las validaciones se ejecutan en el servidor, incluso si se omiten las restricciones HTML. No se instalan nuevas dependencias ni se modifican automáticamente documentos ya guardados.

## Identificación y contacto

| Campo | Regla aplicada |
| --- | --- |
| Teléfono ordinario | 8 dígitos, prefijo 2, 6 o 7. Se admite `+503`, espacios o guion de separación en las posiciones previstas. Se guarda `NNNN-NNNN`. No admite extensiones ni números especiales o de emergencia. |
| Celular | Igual al teléfono, limitado a prefijos 6 y 7. |
| DUI | 8 dígitos y verificador; se acepta con o sin guion. Se comprueba el dígito de control y se rechaza el documento compuesto solamente por ceros. Se guarda `NNNNNNNN-N`. |
| NIT tradicional | 14 dígitos, con o sin separadores `4-6-3-1`. Comprueba fecha de calendario, correlativo distinto de cero y dígito de control según las rutinas tradicional y posterior. No se limita el código territorial a una lista municipal antigua. |
| NIT homologado | Para cliente de tipo natural admite el DUI válido. Si también se indica DUI, ambos deben coincidir. Un cliente jurídico debe utilizar NIT de 14 dígitos. En proveedores, cuyo modelo no distingue tipo de persona, se admiten ambas representaciones. |
| NRC | De 1 a 8 dígitos, opcionalmente con guion antes del último. Se rechaza el valor compuesto solo por ceros y se guarda sin guion. La regla es de formato, no una consulta de inscripción ni una comprobación de dígito de control. |
| Correo | Formato RFC y longitud máxima compatible con la base de datos. No hace consultas DNS para que el proyecto pueda funcionar sin red. |

La normalización no elimina letras para hacer parecer válido un dato incorrecto, ni añade ceros a documentos incompletos. Se preservan los ceros iniciales. Los campos opcionales continúan siendo opcionales; si se completan, deben ser válidos. Los requisitos de aprobación del crédito siguen dependiendo de su condición configurada.

La unicidad de DUI/NIT del cliente se comprueba también contra registros sin guiones, el DUI homologado guardado en la otra columna y registros eliminados lógicamente. Editar el mismo cliente no provoca un falso duplicado.

## Cobertura de formularios activos

| Formulario | Otras comprobaciones |
| --- | --- |
| Clientes: crear y editar | Nombre y razón social con contenido, límites de texto, 14 departamentos, municipio/distrito como texto acotado, estado civil permitido, selectores existentes y habilitados, ingresos y egresos no negativos con dos decimales. Un dato monetario opcional vacío se guarda como cero. |
| Personas jurídicas | Datos del representante, DUI, fechas no futuras, balance posterior a la constitución, empleados como entero no negativo, estructuras de balance y resultados con claves conocidas y valores numéricos. Se permiten pérdidas en utilidades retenidas. Guarda el expediente y sus datos jurídicos en una transacción. |
| Crédito y punto de venta | Fiador con nombre si se completa otro dato suyo, DUI y teléfono válidos, dirección acotada, primera fecha de pago desde hoy, número de cuotas sujeto a la condición y límite técnico de 120. |
| Pagos y decisiones | Importe positivo y dos decimales; fecha válida, no futura ni anterior a la aprobación/solicitud; medio de pago permitido, referencia acotada y motivos de 10 a 2.000 caracteres. Se mantiene el rechazo de sobrepagos. |
| Vendedores | Teléfono y correo, código único al crear o editar, zona existente y habilitada, meta monetaria no negativa y estado booleano. |
| Proveedores y catálogos | NIT y teléfono, plazo entero de 0 a 3.650 días como límite de la aplicación, nombres y descripciones acotados, códigos sin espacios o símbolos arbitrarios y unicidad por institución/unidad. Los IDs usados para ignorar duplicados no se pueden sobrescribir desde el navegador. |
| Movimientos de inventario | Tipo permitido; producto, proveedor y bodegas existentes y habilitados; destino diferente del origen; cantidad positiva y precisión compatible con la base; costo no negativo, lote, referencia, fecha y observaciones. Se permiten fechas de vencimiento pasadas para registrar inventario vencido. |
| Activos | Unidad y categoría existentes, fecha de adquisición no futura, importes acotados, valor residual entre cero y el valor de adquisición, banderas booleanas, años de uso coherentes y motivo de baja permitido. |
| Búsquedas y filtros | Longitudes acotadas, tipos esperados y opciones de estados/clasificación válidas. El filtro de agotados conserva la búsqueda y utiliza una consulta compatible con PostgreSQL. |
| Login | Correo y contraseña acotados, recordar sesión booleano, sin contraseña precargada. Mantiene los límites de intentos fallidos. |

Las longitudes y los máximos de importes/plazos son límites técnicos del proyecto y de su esquema de base de datos; no se presentan como topes legales salvadoreños de crédito o usura. Las fechas se evalúan en `America/El_Salvador` por defecto, configurable mediante `APP_TIMEZONE`.

Las pantallas incluyen ayudas de formato, límites HTML y resúmenes de errores. El plan de cuotas, la facturación local y los pagos conservan su funcionamiento anterior.

## Alcance normativo y fuentes

Estas reglas detectan formatos y verificadores inconsistentes. No certifican que un DUI haya sido expedido, que un número telefónico esté asignado, que un NIT/NRC esté inscrito, ni que su titular sea quien usa el sistema.

Referencias oficiales para ratificar la normativa y los catálogos antes del uso comercial:

- SIGET: https://www.siget.gob.sv/ — plan de numeración y asignación telefónica.
- RNPN: https://www.rnpn.gob.sv/ — identificación y DUI.
- Ministerio de Hacienda: https://www.mh.gob.sv/ — registro tributario y homologación NIT/DUI.

El acceso al sitio oficial consultado fue bloqueado por la política de red de este entorno (HTTP 403); no se afirma haber verificado la normativa vigente en esos portales. Los algoritmos de identificación se contrastaron con implementaciones públicas `@avalontechsv/idsv` 1.2.0 y `sivar-utils` 1.5.0, sin instalarlas ni incorporar su código como dependencia. La interpretación jurídica vigente debe ratificarse con las fuentes oficiales antes de comercializar el producto.

No se incorporó un catálogo oficial de los 44 municipios y sus distritos. El departamento sí utiliza el listado de 14 departamentos; municipio/distrito conserva texto con longitud y tipo validados. No se afirma que ese texto corresponda territorialmente al departamento seleccionado.

## Pruebas

`DatosSalvadorTest` cubre formatos, prefijos, verificadores y normalización, incluidos valores que no se deben corregir silenciosamente. `ValidacionFormulariosTest` envía datos válidos e inválidos a controladores y componentes Livewire, comprueba que los inválidos no se guarden y verifica las rutas de creación y edición. Las pruebas se deben ejecutar exclusivamente sobre una base PostgreSQL de pruebas.
