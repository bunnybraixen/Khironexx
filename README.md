# Khironex

Khironex es una aplicacion web de RRHH y capacitaciones con instalador propio y modulo de dashboard.

## Modulos (Etapa 1)
- Usuarios y Roles (administracion)
- Practicantes
- Relatores
- Capacitaciones
- Informes

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

## Despliegue cPanel
- Guia completa: `docs/CPANEL_DEPLOY.md`

## Dónde editar
- Menu y widgets del dashboard: `config/dashboard.php`
- Logica de modulos y rutas: `web/index.php`
- Tablas y seeds de BD: `installer/Util/AppSetupUtility.php`
- Configuracion final (incluye base_url): `config/khironex.php`

## Linea de trabajo
1. Mantener rutas en español (`practicantes`, `relatores`, `capacitaciones`, `informes`, `usuarios`).
2. Cada modulo debe tener su tabla y CRUD basico.
3. Cambios de estructura se documentan aqui.
# Khironexx
