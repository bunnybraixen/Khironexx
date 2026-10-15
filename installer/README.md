# Installer Khironex

Este directorio contiene el instalador **activo** del proyecto.

## Flujo actual (runtime)

1. `installer/index.php` ejecuta el wizard web por pasos (`welcome -> requirements -> db -> admin -> finish`).
2. `web/index.php` redirige a `installer/` cuando `config/khironex.php` no esta instalado.
3. El esquema y seeds base se aplican desde `installer/Util/AppSetupUtility.php`.
4. La conexion de instalacion se maneja en `installer/Util/Connection.php`.

## Archivos activos

- `installer/index.php`
- `installer/assets/style.css`
- `installer/Util/AppSetupUtility.php`
- `installer/Util/Connection.php`
- `installer/Controller/Installer/Api/*.php` (solo endpoints usados por `index.php`)
- `installer/scripts/setup_db.php` (instalacion por CLI)
- `installer/tests/api_check.php`
- `installer/.htaccess`

## Limpieza aplicada

Se eliminaron archivos vacios heredados que no estaban conectados al runtime.
Esto reduce ruido, evita falsa complejidad y facilita mantenimiento.

## Criterio para nuevos archivos

- Si un archivo no esta referenciado por `installer/index.php` o scripts CLI reales, no debe agregarse como stub vacio.
- Si se prepara una funcionalidad futura, incluir implementacion minima y referencia en este README.

