# Documento Técnico de Implementación - Irma Trail Running

Este documento detalla la arquitectura técnica, las modificaciones de base de datos, el sistema de permisos y las integraciones de API realizadas en el proyecto **Irma Trail Running**.

---

## 🛠️ Arquitectura y Stack Tecnológico
- **Lenguaje:** PHP 8.1+ / 8.2 (Modo estricto desactivado para deprecaciones de parámetros null).
- **Base de Datos:** MySQL 8.0+ / MariaDB.
- **Frontend:** HTML5, CSS3 (con variables CSS premium), Bootstrap 5.3.2 y FontAwesome 6.4.0 para íconos.
- **Servidor Web:** Apache / Servidor incorporado de PHP para desarrollo local.

---

## 1. Ajustes del Entorno y Base de Datos

### Sincronización de Zona Horaria (Offset)
Para evitar desfases horarios en el planificador (donde el servidor tomaba el día siguiente debido a la diferencia UTC/GMT local), se centralizó la zona horaria en [`config/db.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/config/db.php):
```php
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo->exec("SET time_zone = '-03:00'");
```

### Modificación de Tablas de Base de Datos
Se agregaron nuevos campos a la estructura de la base de datos para almacenar la información de pagos automatizados y actividades físicas provenientes de Strava:

1. **Tabla `rutina_asignada` (Nuevas columnas de métricas de rendimiento y Strava):**
   - `distancia_real` (DECIMAL 5,2): Almacena los kilómetros corridos reportados por el reloj o la app.
   - `desnivel_real` (INT): Almacena los metros de desnivel positivo acumulados.
   - `strava_activity_id` (VARCHAR 50): Almacena el ID único de la actividad en Strava para armar hipervínculos dinámicos.
   - `ritmo_real` (VARCHAR 50): Almacena el ritmo promedio calculado de la sesión (en minutos/kilómetro).
2. **Tabla `strava_tokens` (Almacenamiento de tokens OAuth):**
   - `alumno_id` (INT - PRIMARY KEY, FOREIGN KEY en relación con `alumno_perfil(id)`).
   - `access_token` (VARCHAR 255): Token portador para realizar peticiones de lectura a la API de Strava.
   - `refresh_token` (VARCHAR 255): Token de actualización de larga duración.
   - `expires_at` (INT): Timestamp Unix con la fecha de expiración del token actual.
3. **Módulo de Certificados Médicos (`alumno_perfil` extendida):**
   - `certificado_medico_url` (VARCHAR 255): Ruta al archivo subido por el alumno.
   - `certificado_medico_estado` (ENUM): Posibles estados: `pendiente`, `aprobado`, `rechazado`.
   - `certificado_medico_comentario` (TEXT): Justificación del entrenador en caso de rechazo del documento.

---

## 2. Integración de API - Mercado Pago (Checkout Pro)

La pasarela fue desarrollada utilizando llamadas HTTPS nativas al SDK REST de Mercado Pago sin dependencias externas complejas, asegurando velocidad y bajo consumo de recursos.

### Flujo de Preferencia y Redirección
1. **Archivo de Configuración ([`config/mercadopago.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/config/mercadopago.php)):** Almacena las llaves públicas y privadas en entorno Sandbox.
2. **Acción de Creación ([`actions/crear_preferencia_mp.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/actions/crear_preferencia_mp.php)):**
   - Recibe vía `POST` el mes a pagar y el monto.
   - Genera una llamada HTTP POST a `https://api.mercadopago.com/v3/checkout/preferences` con las credenciales de autorización Bearer.
   - Serializa en `external_reference` los metadatos de la sesión en el formato: `alumno_id::mes_pagado::monto`.
   - Redirige al alumno a la dirección devuelta `init_point` (interfaz oficial de pago).

### Callback y Activación Automática ([`actions/pago_mp_success.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/actions/pago_mp_success.php))
- Recibe los parámetros de retorno de Mercado Pago (`collection_id`, `collection_status`, `external_reference`).
- Valida que el estado sea `'approved'`.
- Decodifica la referencia para extraer el ID del alumno, el mes y el monto.
- Registra el pago en la tabla `pago_registro` con el estado `'aprobado'` y guarda en `comprobante_url` el ID de la transacción prefijado como `'MERCADOPAGO-' . $payment_id`.
- Ejecuta una consulta para actualizar `esta_activo = 1` y prolongar la fecha de vencimiento (`fecha_vencimiento`) en la tabla `alumno_perfil`.
- Redirige al alumno a su panel con una alerta de éxito.

---

## 3. Integración de API - Strava v3

El módulo de Strava automatiza la obtención de entrenamientos realizados por el deportista y los asocia a sus tareas del día en la base de datos de Irma Trail Running.

### Helper de Sincronización Centralizado ([`includes/strava_sync_helper.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/includes/strava_sync_helper.php))
Para evitar la duplicidad de lógica entre la sincronización manual y el webhook en tiempo real, se encapsuló todo el proceso en la función `sincronizarActividadesStrava($alumno_id, $pdo)`. Esta función:
1. **Renovación de Credenciales:** Compara el `time()` actual contra `expires_at`. Si el token expiró, realiza una consulta HTTP enviando el `refresh_token` para actualizar el set de claves en la BD de forma transparente.
2. **Descarga de Datos:** Ejecuta un GET al endpoint `/athlete/activities` solicitando las actividades de los últimos 14 días.
3. **Procesamiento de Métricas y Ritmo:**
   - Filtra actividades deportivas de tipo `Run`, `TrailRun`, `Hike` o `Walk`.
   - Agrupa los valores del mismo día y acumula distancia y tiempo de movimiento.
   - Calcula el ritmo promedio por kilómetro:
     $$\text{Segundos por Km} = \frac{\text{Tiempo de Movimiento (segundos)}}{\text{Distancia (metros)} / 1000}$$
     El cociente resultante se descompone en minutos y segundos dando formato al string final: `MM:SS min/km`.
4. **Almacenamiento y Enlace:** Busca una fila en la tabla `rutina_asignada` donde coincidan el ID del alumno y la fecha local de la corrida. Si existe, actualiza los campos `distancia_real`, `feedback_tiempo_minutos`, `desnivel_real`, `strava_activity_id`, `ritmo_real` y marca `completada = 1`.

### Loop de Autorización OAuth ([`actions/strava_auth.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/actions/strava_auth.php))
- Redirige al alumno a la pasarela de consentimiento de Strava con el scope `activity:read_all`.
- Tras autorizar, Strava redirige de vuelta con un parámetro temporal `code`.
- Se genera una petición POST a `https://www.strava.com/oauth/token` intercambiando el código temporal por el `access_token`, `refresh_token`, `expires_at` y el identificador de deportista de Strava `data['athlete']['id']`.
- Guarda o actualiza los datos en la tabla `strava_tokens` (incluyendo la columna `athlete_id`).

### Sincronización Manual ([`actions/strava_sync.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/actions/strava_sync.php))
- Endpoint seguro que se ejecuta cuando el alumno hace clic en el botón "Sincronizar". Invoca al helper centralizado para el alumno de la sesión actual y lo redirige de vuelta al dashboard con una alerta de éxito.

### Sincronización en Tiempo Real - Webhook ([`actions/strava_webhook.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/actions/strava_webhook.php))
Permite recibir notificaciones de eventos desde Strava de forma asíncrona e instantánea tan pronto como el deportista guarda la corrida en su app o reloj.
1. **Validación de la Suscripción (GET):**
   - Responde al handshake inicial de Strava verificando que `hub_verify_token` sea igual al token local (`irma_trailrunning_verify_token_2026`).
   - Si es válido, devuelve el `hub_challenge` enviado por Strava en formato JSON con código `200 OK`.
2. **Procesamiento de Eventos (POST):**
   - Recibe la notificación en formato JSON.
   - Si `object_type` es `'activity'` y `aspect_type` es `'create'` o `'update'`, extrae el identificador del atleta (`owner_id`).
   - Busca el `alumno_id` correspondiente en la tabla `strava_tokens` usando `athlete_id`.
   - Ejecuta la función `sincronizarActividadesStrava($alumno_id, $pdo)` para actualizar de forma transparente e inmediata el calendario del alumno y el planificador del entrenador.

#### 🔧 Cómo Suscribirse en Producción:
Una vez que el sitio esté hosteado públicamente con HTTPS, se debe crear la suscripción en la API de Strava ejecutando un comando cURL (por ejemplo desde Postman o consola):
```bash
curl -X POST https://www.strava.com/api/v3/push_subscriptions \
  -F client_id=TU_CLIENT_ID \
  -F client_secret=TU_CLIENT_SECRET \
  -F callback_url=https://tusitio.com/actions/strava_webhook.php \
  -F verify_token=irma_trailrunning_verify_token_2026
```

---

## 4. Control de Acceso y Reglas de Permisos

Los archivos controladores de rutinas y certificados del panel del entrenador presentaban una restricción fija al rol literal `'entrenador'`. Esto bloqueaba el guardado de datos a los nuevos roles parametrizados. 
Se corrigió la validación de seguridad en [`actions/admin_rutina_action.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/actions/admin_rutina_action.php) y [`actions/admin_certificado_action.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/actions/admin_certificado_action.php) reemplazándola por:
```php
require_rol(['admin', 'entrenador_total', 'entrenador_limitado']);
```
Con esta modificación, cualquier usuario que posea alguno de estos tres roles asignados puede interactuar de forma segura con la planificación del alumno.

---

## 5. Mejoras de Interfaz de Usuario y Estilos (CSS)
- **Visualización Centrada:** Se añadieron anidaciones HTML basadas en clases de Bootstrap (`.col-lg-8.col-md-10.mx-auto`) en [`admin/planificador.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/admin/planificador.php) y [`admin/plantillas.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/admin/plantillas.php) para evitar el estiramiento o desalineación visual en pantallas de alta resolución.
- **Esquema de Colores Dinámico:**
  - El nombre del usuario en sesión se pinta en color Verde `#388e7a` (para denotar estabilidad y estado al día) y el tipo de rol o plan asignado en color Rojo `#d16b5a` (resaltado administrativo).
- **Botón "Ir Arriba":** Construido en HTML y CSS nativo con animaciones fluidas y un script escuchador (`scroll listener`) que calcula la posición de la barra de scroll vertical (`window.scrollY > 300`) para renderizar el botón y realizar un retorno suave.
- **Portapapeles Dinámico:** En [`alumno/reportar_pago.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/alumno/reportar_pago.php) se implementó la función en Javascript `copiarTexto()` que interactúa con la API de portapapeles del navegador (`navigator.clipboard.writeText`) para ofrecer una experiencia intuitiva al copiar CBU, CVU o Alias bancarios sin necesidad de seleccionarlos manualmente.

---

## 6. Sistema de Auditoría y Logs de Transacciones (Log de Cambios)

El módulo de auditoría garantiza la trazabilidad e integridad de los datos sensibles en la base de datos (planificaciones, usuarios, perfiles, etc.).

### Lógica de Registro (`includes/audit_helper.php`)
- **`registrarAuditoria($pdo, $params)`**: Centraliza la inserción en la tabla `audit_log`. Extrae datos por defecto de la sesión actual (`$_SESSION['user_id']`, `user_nombre`, `user_rol`). Si el nombre de usuario o del alumno no se provee, la función realiza búsquedas secundarias optimizadas en `usuarios` y `alumno_perfil` antes de insertar.
- **Snapshots JSON (`datos_anteriores` / `datos_nuevos`)**:
  - Al modificar o eliminar un registro (ej. una rutina, un alumno, un perfil o una carrera), la base de datos almacena el estado completo previo codificado como un objeto JSON en `datos_anteriores`.
  - Al crear o modificar, almacena el estado posterior en `datos_nuevos`. Esto posibilita la reversión de cambios en caso de accidentes o eliminación de datos.

### Mecanismo de Restauración de Planificaciones (`actions/admin_rutina_action.php` -> `restore_rutina`)
1. El usuario administrador solicita la restauración enviando el `log_id` de la eliminación original.
2. El backend busca el registro en `audit_log` y decodifica el objeto JSON de `datos_anteriores`.
3. **Validación de Ocupación**: Realiza una consulta `SELECT id FROM rutina_asignada` filtrando por el `alumno_id` y la `fecha` recuperados. Si el día ya tiene otra rutina asignada, detiene la operación y lanza un error `date_occupied` para evitar solapamientos.
4. **Reinserción**: Re-inserta la rutina original con los parámetros respaldados (`titulo`, `descripcion`, `tipo_sesion`, `distancia_km`, `ritmo_sugerido`, `terreno`).
5. **Auditoría de Reversión**: Registra una nueva acción `restaurar_rutina` en la tabla `audit_log` para dejar constancia de la recuperación.
6. **Notificación**: Inserta una alerta para el alumno de que su rutina fue restaurada.

---

## 7. Importación Masiva y Plantillas Relativas

### Plantillas de Entrenamiento (Días Relativos)
Se eliminó la dependencia de fechas específicas (`fecha_inicio` y `fecha_fin`) en el constructor de plantillas (`plantillas.php`).
- La tabla `plantillas` almacena únicamente la `duracion_dias` (múltiplo de 7, por semanas).
- El calendario del editor renderiza la vista utilizando un cálculo relativo (`Día 1`, `Día 2`, `Día N`).
- Esto evita que una plantilla caduque en el tiempo. Las rutinas se vuelcan en masa recién cuando el entrenador presiona **"Aplicar Plantilla"**, donde se establece la fecha base real (`fecha_inicio`) sobre la cual iterar para el alumno.

### Importación de Alumnos por Lotes (CSV Parser)
- Se desarrolló el flujo de lectura y parsing nativo con `fgetcsv()` en `admin_alumno_action.php`.
- La función de descarga inyecta cabeceras especiales (incluido un *BOM* UTF-8) usando `ob_clean()` y `fputs($output, "\xEF\xBB\xBF")` para garantizar la correcta apertura en aplicaciones de ofimática de Windows (Excel) previniendo caracteres corruptos.
- Durante la iteración, el script inserta un nuevo `usuario` estableciendo el DNI como `password_hash` predeterminado y luego vincula el `alumno_perfil` con su estado activo.

---

## 8. Arquitectura del Sistema de Notificaciones

Las notificaciones operan de manera síncrona mediante base de datos y se renderizan de forma interactiva en la interfaz web.

### Envío de Notificaciones (`includes/audit_helper.php` -> `crearNotificacion`)
- Inserta registros en la tabla `notificaciones` indicando el `usuario_id` receptor, el `titulo`, el `mensaje` de texto y la URL de redirección (`enlace`).
- El sistema notifica al instante en base a reglas de eventos de negocio:
  - **Acciones del Entrenador:** Disparan notificaciones al alumno afectado (carga de rutina, modificación, borrado, estado del certificado de apto médico).
  - **Acciones del Alumno:** Disparan notificaciones a su entrenador asignado (carga de feedback manual, entrenamientos extras voluntarios, carga de apto médico, o sincronizaciones asíncronas de Strava).

### Interfaz Interactiva (`includes/navbar.php`)
- **Lazy Loading de Conexión**: Dado que la barra de navegación se incluye en portales públicos que no cargan base de datos (ej: la landing page pública `index.php`), el script verifica la existencia del objeto `$pdo`. Si no existe pero el usuario está logueado, carga de forma dinámica y diferida `config/db.php`.
- **Dropdown dinámico de Bootstrap**: Renderiza las últimas 5 notificaciones ordenadas por fecha en orden descendente. Si hay avisos no leídos (`leido = 0`), muestra un indicador visual numérico de Bootstrap Badge.
- **Acciones Javascript Asíncronas (fetch API):**
  - **Marcar leída (`marcarLeida(id)`):** Se ejecuta al hacer clic en un elemento de notificación del dropdown. Envía una petición `POST` en segundo plano a [`actions/notificaciones_action.php`](file:///c:/Users/Sebastian/Desktop/Proyectos%20Antigravity/IBTrailrunning/actions/notificaciones_action.php) antes de redirigir al enlace de destino.
  - **Marcar todas como leídas (`marcarTodasNotif(event)`):** Se ejecuta al hacer clic en el botón superior del dropdown. Envía un `POST` en segundo plano al mismo endpoint con la acción `marcar_todas`. Tras recibir una respuesta exitosa, actualiza la interfaz eliminando el badge rojo y las clases CSS de estilo negrita (`fw-semibold`) y fondo de resalte de todos los items en tiempo real.

---

## 9. Módulo de Galería y Carrusel de Fotos Premium (`index.php`)

Se incorporó un carrusel dinámico y adaptativo para mostrar fotografías del grupo de entrenamiento, posicionado exactamente entre las secciones de introducción y planes de precios.

### Implementación y Estructura
- **Estructura HTML:** Implementa un carrusel de Bootstrap 5 con la clase `.carousel-fade` para transiciones de desvanecimiento suave de 5 segundos. Las diapositivas apuntan a las imágenes estáticas (`c1.jpg`, `c2.jpg`, etc.) cargadas en la carpeta de recursos `/assets/img/`.
- **Estilos Visuales Premium (`styles.css`):**
  - **Efecto Zoom Ken Burns:** La clase `.carousel-item.active .carousel-premium-img` aplica un escalado progresivo de `scale(1.08)` mediante transiciones CSS de 12 segundos, logrando dinamismo visual.
  - **Leyenda Glassmorphism:** Diseñada con una mezcla de color de fondo oscuro semitransparente (`rgba(25, 26, 26, 0.65)`), sombra de tarjeta premium y filtros de desenfoque nativos del navegador (`backdrop-filter: blur(12px)`).
- **Adaptabilidad y UX Móvil (Captions Sync):**
  - Para evitar que la leyenda tape a los corredores en dispositivos móviles, se oculta la tarjeta superpuesta (`d-none d-md-block`) y se traslada dinámicamente un bloque de texto descriptivo debajo del carrusel (`#mobile-caption-container`).
  - Se desarrolló un controlador de eventos JavaScript (`slide.bs.carousel`) que captura el cambio de slide, modifica la opacidad de los textos de la descripción y actualiza el título y texto de forma fluida con una transición de 250ms.

