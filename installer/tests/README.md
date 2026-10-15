## Pruebas del instalador Khironex

Este test verifica que los endpoints API del instalador respondan JSON valido y entreguen `status`.

### Como usar

1. Asegurate de que el instalador este accesible en `http://localhost/khironex/installer/`.
2. Si usas otra URL, define `KHIRONEX_INSTALLER_URL`.
3. Ejecuta desde la raiz del repositorio:

```bash
php installer/tests/api_check.php
```

4. El script imprime cada API chequeada. Si alguna falla, retorna codigo `1`.

### APIs evaluadas

- `system-check`: validacion de requisitos.
- `version`: informacion de edicion.
- `config`: valida si `config/khironex.php` existe.
- `instance`: metadatos de instancia.
- `installation-instance`: estado de instancia.
- `register`: endpoint placeholder para registro de datos.

