# Interfaz de El Tablazo

La presentación comparte una paleta crema y gris cálido, roble para acciones principales, ámbar para acentos y petróleo para información. Se emplean fuentes del sistema para evitar depender de servicios externos.

El menú organiza el trabajo en panel general, clientes y cobranza, ventas y existencias, equipo y administración. En móvil se abre desde la cabecera y se cierra con el fondo, Escape o al navegar. El panel incorpora accesos frecuentes y la cabecera permite cerrar sesión. Se conservan rutas, directivas de permisos y lógica financiera.

Los formularios comparten controles de al menos 44 px, las columnas del punto de venta se apilan en pantallas pequeñas y las tablas pueden desplazarse con teclado. Se incluye enlace para saltar al contenido, foco visible y respeto a la preferencia de movimiento reducido.

## Validación

- `npm run build`: correcta.
- `php artisan view:cache`: correcta.
- `php artisan test`: 234 pruebas correctas, 1 fallo previo en `PruebasIntegralesSeederTest::test_puede_repetirse_y_respeta_datos_usuarios_roles_y_configuracion_existentes`, línea 223: depreciación masiva devuelve 8 activos frente a 1 esperado. El mismo fallo se reprodujo con el código original de main (`8836edf`) en una copia temporal.
- No se modifican archivos del entorno, servicios financieros, controladores, modelos ni políticas.

Los recursos gráficos de marca deben proceder de los archivos suministrados por el propietario. Hasta recibirlos se usa el nombre de la mueblería en texto.
