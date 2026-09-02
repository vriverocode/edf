# Resolución de Issues en `laravel.log` (Línea 3466 en adelante)

Al revisar el archivo de log `frontend/dist/laravel.log` a partir de la línea 3466, se detectaron los siguientes dos problemas principales en el backend:

### 1. Error: `Call to undefined method App\Models\User::quotas()`
**Log original**: 
```text
[2026-09-01 16:22:14] production.ERROR: Call to undefined method App\Models\User::quotas()
[2026-09-02 14:32:00] production.ERROR: Call to undefined method App\Models\User::quotas()
```
- **Origen del problema**: En el archivo `app/Http/Controllers/Api/ReportController.php` (específicamente en el método `delinquentsMetrics()` en la línea 516), se está ejecutando la consulta Eloquent `->orWhereHas('quotas', ...)`. Sin embargo, el modelo `App\Models\User` no tenía definida una relación llamada `quotas()`, lo cual causaba una excepción `BadMethodCallException` al intentar calcular las métricas de morosidad.
- **Solución implementada**: Se ha agregado la relación `quotas()` en el modelo `App\Models\User.php` utilizando `hasManyThrough`. Esto permite que Laravel navegue correctamente de `User` a `Quota` a través de la tabla intermedia `Departament`.
```php
public function quotas()
{
    return $this->hasManyThrough(Quota::class, Departament::class, 'user_id', 'departament_id');
}
```

---

### 2. Error: `Class "Maatwebsite\Excel\Facades\Excel" not found`
**Log original**:
```text
[2026-09-02 14:37:36] production.ERROR: Class "Maatwebsite\Excel\Facades\Excel" not found
```
- **Origen del problema**: En el archivo `app/Http/Controllers/Api/ReportController.php` (método `exportDelinquents()`, línea 535), se invoca a `Excel::download()` para exportar un archivo `.xlsx`. Aunque en tu archivo `composer.json` el paquete `maatwebsite/excel` sí se encuentra definido, el servidor (entorno `production`) no encuentra la clase en el autoload. Esto sucede típicamente cuando los paquetes no se han sincronizado o instalado completamente en producción.
- **Solución / Acción requerida**: Como el código está bien importado, para resolverlo debes conectarte a la consola del servidor (producción) y ejecutar:
```bash
composer install --optimize-autoloader --no-dev
# O en su defecto, para regenerar el mapa de clases:
composer dump-autoload
```
Si estuvieses en desarrollo local y presentaras este error, la solución sería igualmente correr un `composer install` para asegurar que las dependencias estén bien vinculadas.
