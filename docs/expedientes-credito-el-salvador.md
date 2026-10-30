# Expediente documental de crédito comercial en El Salvador

## Alcance de la investigación — 30 de octubre de 2026

El producto financia **compras de mercadería a plazo**. No desembolsa préstamos bancarios. Se preparó una matriz documental inicial para evaluar identidad, domicilio, capacidad de pago, representación y garantía. Es una **política interna configurable**, pendiente de contrastar con fuentes oficiales y con los requisitos concretos del negocio antes de uso comercial.

Se intentó consultar [Defensoría del Consumidor](https://www.defensoria.gob.sv/), [Superintendencia del Sistema Financiero](https://ssf.gob.sv/) y [Banco Agrícola](https://www.bancoagricola.com/). La política de red del entorno rechazó los tres accesos con HTTP 403 en el túnel CONNECT. No se pudo comprobar su contenido, formularios, vigencia normativa ni artículos. Por ello este cambio **no certifica requisitos legales actuales** ni atribuye a esas entidades la lista implementada. Se encontró además un repositorio público de transcripciones de normativa sobre prevención de lavado, pero no se utilizó para imponer obligaciones: no es una fuente oficial y no determina por sí mismo si este comercio está sujeto a ese régimen.

Antes de comercializar deben verificarse, según la actividad y el tipo de proveedor: legislación de protección al consumidor y sus reglas de información/contratación; reglas de identificación tributaria y homologación DUI/NIT; régimen de representación de sociedades y fianza; tratamiento de datos personales y consultas a burós; y obligaciones de prevención de lavado **solo cuando sean aplicables al sujeto y a la operación**. No se fijaron tasas máximas, plazos legales de retención o vigencias documentales que no se pudieron verificar. Una política de un banco no se presenta como ley aplicable a todo vendedor de mercadería.

## Matriz implementada

| Apartado | Política inicial para aprobación | Alternativas y documentos opcionales |
|---|---|---|
| Cliente natural | DUI de ambos lados, domicilio, evidencia de ingresos | DUI en un PDF/imagen o frente y reverso separados. Ingresos: constancia salarial/pensión, boletas, estado de cuenta, renta o estados financieros. NIT si corresponde, referencias y autorizaciones pertinentes como documentos adicionales. |
| Cliente jurídico | DUI del representante registrado, NIT de sociedad, constitución/personería, credencial/poder de representación, domicilio y estados financieros | NRC cuando corresponda y referencias comerciales. El DUI de la sociedad no se exige: la identidad personal corresponde al representante. |
| Cada crédito | Solicitud y contrato firmados correspondientes a esa operación | Autorización de consulta crediticia o constancia de información/consentimiento cuando corresponda. No se realizan consultas externas. |
| Cada fiador registrado | DUI de ambos lados, domicilio, evidencia de ingresos y fianza firmada para ese crédito | NIT si corresponde. Nombre, dirección, teléfono de El Salvador e ingresos/egresos completos. La política interna exige ingresos mayores a egresos; no se presenta como un umbral legal de solvencia. |

No se exige un fiador en todos los créditos: lo determina la condición seleccionada. Si se registra voluntariamente uno, debe completarse su expediente antes de aprobar. Los clientes naturales que usen NIT homologado no necesitan inventar un NIT de 14 dígitos; se conserva la validación existente. Adjuntar un comprobante no modifica automáticamente los datos de la ficha ni acredita autenticidad.

La política está en `config/expedientes.php`: catálogo, alternativas y requisitos por ámbito. Cada solicitud nueva conserva una copia de los requisitos, de modo que cambiar la configuración no altera silenciosamente sus condiciones. Si cambia el tipo natural/jurídico del cliente, debe registrarse una nueva solicitud con la política correspondiente. No hay excepciones documentales por un simple checkbox ni parámetros HTTP para desactivar el control.

## Uso

1. Registrar o completar la ficha del cliente, incluyendo el representante legal si es sociedad.
2. En la ficha, abrir **Expediente documental** y presentar comprobantes del cliente. Pueden utilizarse en varias solicitudes del mismo cliente mientras sean aceptables.
3. Registrar la venta/crédito. Las solicitudes nuevas quedan pendientes aunque el cliente sea frecuente o esté marcado el checkbox de DUI.
4. Abrir **Expediente documental** desde el crédito. Presentar solicitud y contrato de esa operación. Completar los datos y comprobantes de cada fiador; se admiten varios.
5. El revisor autorizado descarga e inspecciona el archivo y registra resultado y motivo. La carga sola queda `pendiente`. Puede validarse, rechazarse o anularse sin borrar evidencia.
6. Si hay correcciones, cargar una nueva versión seleccionando el comprobante y su misma categoría. La anterior mantiene archivo y revisión histórica; la nueva vuelve a pendiente.
7. La lista muestra los faltantes. La aprobación comprueba documentos actuales, revisados, sin vencer, disponibles y con hash íntegro. Conserva en bitácora los requisitos, IDs, versiones, hashes, revisores y datos de fiadores utilizados para la decisión.

Se respetan los vencimientos consignados; no se inventa un plazo de vigencia de recibos o constancias. Si cambian nombre o identificación del titular/representante, sus comprobantes dejan de contar y deben presentarse nuevamente. Cambiar domicilio o cifras/actividad de ingresos exige renovar los comprobantes de esas categorías; no se conserva como vigente un respaldo revisado contra otros datos. No se hace OCR, autenticación de DUI/NIT, validación de firmas ni comprobación de poderes ante registros públicos. La revisión es responsabilidad del operador autorizado.

Los contratos y documentos de fiadores no se reutilizan entre créditos. Los fiadores solo se modifican mientras la solicitud esté pendiente; los cambios quedan registrados. Los créditos ya otorgados antes de esta migración pueden completar su archivo sin reabrir la aprobación. Las solicitudes anteriores que sigan pendientes adoptan el control documental con un evento de migración, sin inventar revisiones o comprobantes. Los pagos, recibos e incobrables no se bloquean por vencimiento posterior de un documento.

## Archivos, permisos e historial

- Disco local privado `expedientes`, bajo `storage/app/expedientes`, sin enlace público ni URLs temporales. Todos los accesos requieren sesión y `expedientes.view`.
- Permisos adicionales: `expedientes.upload` para recepción/reemplazo, `expedientes.review` para revisión/anulación y `expedientes.manage` para altas/correcciones de fiadores.
- Administradores y gerentes de cobros reciben los cuatro; agentes de cobros reciben lectura/carga. Un usuario con solo `creditos.view`, un vendedor, un visor general o un visitante no obtiene acceso a los documentos. Los permisos documentales se aplican a los expedientes del sistema, sin aislamiento por sucursal/cartera; el sistema no tiene usuarios clientes con acceso propio.
- PDF, JPEG, PNG y WebP; extensión y MIME detectado deben concordar. Se comprueba estructura básica de PDF, dimensiones de imagen, tamaño y fechas. Se rechazan SVG/HTML/ejecutables. Límite inicial **2 MB por archivo**, coherente con el PHP actual (`upload_max_filesize=2M`, `post_max_size=8M`); hasta 500 versiones/documentos por cliente.
- Para ampliar el límite, ajustar `config/expedientes.php` y los límites efectivos del PHP web/proxy. `post_max_size` debe superar al archivo más los campos del formulario. No se modifica automáticamente la configuración del entorno.
- Nombre físico aleatorio, nombre original saneado como metadato, checksum SHA-256. Descargas como adjunto con `nosniff`, CSP y caché privada/no-store. Un archivo ausente o alterado no se valida ni entrega.
- La validación de formato **no es un antivirus** ni certifica que un PDF esté libre de contenido activo. Los archivos se entregan como adjuntos, sin incrustarlos en HTML. Antes de admitir cargas públicas o vender el producto se debe incorporar análisis antimalware y una política verificada de conservación, respaldo, cifrado en reposo y acceso a datos personales.
- Historial de recepción, reemplazo, revisión, cambios del fiador, descargas y aprobación. Solo inserción desde el modelo y protección adicional con trigger en PostgreSQL. No existe eliminación de documentos en la interfaz. Esto facilita revisión interna; no acredita por sí solo cumplimiento de una auditoría legal o fiscal.
- Si falla la transacción al subir, se elimina el archivo nuevo y se conserva la versión anterior. Los documentos originales no se sobrescriben. Respaldar juntos base de datos y disco privado; no borrar manualmente archivos. Una caída abrupta del proceso entre escritura y commit puede dejar un archivo huérfano; no se elimina automáticamente evidencia.

No depende de Hacienda, DTE, firma electrónica ni servicios de terceros.

## Instalación y pruebas

```bash
php artisan optimize:clear
php artisan migrate
npm run build
# Solo en una base local/QA; nunca en producción:
php artisan db:seed --class=PruebasIntegralesSeeder
```

La migración añade el archivo e historial, los campos financieros/NIT faltantes de fiadores, los permisos y las columnas de control de créditos. No borra ni reclasifica préstamos existentes. Aplique la migración con el procesamiento de solicitudes detenido para evitar que una versión anterior de la aplicación apruebe solicitudes durante la actualización. Si ya existen datos QA, el seeder agrega el suplemento documental sin reiniciar pruebas manuales. También puede ejecutarse `php artisan db:seed --class=ExpedientesPruebasSeeder` después de cargar el conjunto base.

Escenarios del suplemento:

- `QA-PENDIENTE`: expediente obligatorio incompleto, domicilio rechazado y segunda versión pendiente, ingresos vencidos y solicitud pendiente.
- `QA-CON-FIADOR`: archivo completo de cliente, crédito y fiador para practicar consultas y descargas. Es un caso histórico con documentos QA agregados posteriormente; no se inventa evidencia de revisión anterior a su aprobación.
- Todos los PDF están marcados **DOCUMENTO SINTÉTICO DE PRUEBA**; no contienen imágenes de documentos personales reales ni contratos utilizables. Los archivos QA no se restauran ni las revisiones se reinician al repetir el seeder.

`tests/Feature/ExpedientesTest.php` cubre el flujo obligatorio nuevo, alternativas DUI, personas jurídicas y representantes, fiadores, aislamiento por cliente/crédito, formatos/tamaños, permisos, vencimiento, cambio de identidad, integridad, revisiones/versiones, reversión de archivos y evidencia de aprobación. Las pruebas anteriores mantienen explícitamente el modo histórico para verificar compatibilidad.
