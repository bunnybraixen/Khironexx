# Khironex

Khironex es una aplicacion web de RRHH y capacitaciones con instalador propio y modulo de dashboard.

## Modulos (Etapa 1)
- Usuarios y Roles (administracion)
- Practicantes
- Relatores
- Capacitaciones
- Informes
- Tickets de soporte (registro inicial y seguimiento)

El modulo de tickets permite registrar problemas, consultar su seguimiento y buscar o filtrar por numero, texto, estado, prioridad y modulo. La conversacion se guarda en `tickets_mensajes` para conservar las respuestas anteriores.

## Estructura base
- `web/` UI principal y rutas del sistema
- `dashboard/` entrada al dashboard (usa `web/index.php`)
- `installer/` instalador web y APIs
- `config/` configuraciones del sistema
- `img/` logos y assets de marca
- `sql/` respaldos y scripts

## Configuracion rapida
1. Copia el proyecto en `C:\xampp\htdocs\khironex`.
2. Abre `http://localhost/tu-carpeta/` (o `/` si está en raíz) y sigue el instalador.
3. El instalador crea la base de datos y el admin inicial.

### Actualizar una instalacion local existente

Para agregar el modulo de tickets a una base de datos ya instalada, selecciona la base local de Khironexx en phpMyAdmin e importa `sql/tickets_soporte_migration.sql`. Para habilitar la conversacion, importa tambien `sql/tickets_mensajes_migration.sql`. Son tablas separadas y no alteran solicitudes de cambio. Hazlo primero en desarrollo y con respaldo autorizado.

## Despliegue cPanel
- Guia completa: `docs/CPANEL_DEPLOY.md`

## Dónde editar
- Menu y widgets del dashboard: `config/dashboard.php`
- Logica de modulos y rutas: `web/index.php`
- Tablas y seeds de BD: `installer/Util/AppSetupUtility.php`
- Modulo de tickets: `app/Support/tickets.php` y `app/Views/tickets/index.php`
- Configuracion final (incluye base_url): `config/khironex.php`

## Linea de trabajo
1. Mantener rutas en español (`practicantes`, `relatores`, `capacitaciones`, `informes`, `usuarios`).
2. Cada modulo debe tener su tabla y CRUD basico.
3. Cambios de estructura se documentan aqui.
