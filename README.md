# Canasta Básica CR · Inflación y costo de vida

Dashboard que cruza varias fuentes oficiales de Costa Rica en un solo análisis:

| Fuente | Datos | Cómo se obtiene |
|---|---|---|
| **BCCR** (API SDDE) | IPC general, 13 divisiones de consumo, regulados/no regulados, bienes/servicios, inflación subyacente, expectativas de inflación, salario mínimo real, tipo de cambio | `php artisan canasta:sync` (programado a diario) |
| **INEC** | Costo per cápita de la Canasta Básica Alimentaria (total, urbana, rural), 17 subgrupos de alimentos, líneas de pobreza | Excel de series históricas, importado desde el panel admin |

## Qué calcula

Todas las variaciones se derivan de los **niveles** de cada índice, así son consistentes entre fuentes:

- **Mensual, interanual y acumulada** por división del IPC, más un mapa de calor de 12 meses.
- **Evolución comparada** de divisiones, como índice con base a elegir o como variación interanual.
- **Contribución de cada subgrupo de alimentos** a la variación de la canasta, en puntos porcentuales:
  `(costo_sub(t) − costo_sub(t−12)) / CBA(t−12) × 100`. Las contribuciones suman la variación total.
- **Canasta en US$** con el tipo de cambio promedio de venta de cada mes (INEC × BCCR).
- **Canasta INEC vs. IPC de alimentos**: correlación y diferencia por composición.
- **Calculadora de poder adquisitivo** con ajuste por IPC, y el salario mínimo real contra la inflación.

## Stack

Laravel 13 (PHP 8.5) · MariaDB · Bootstrap 5 · jQuery (AJAX) · Chart.js 4 · PhpSpreadsheet

## Instalación

```bash
composer install
cp .env.example .env        # completar DB_*, BCCR_API_TOKEN y ADMIN_KEY
php artisan key:generate
php artisan migrate --seed
php artisan canasta:sync                                  # historial BCCR desde 2015
php artisan canasta:importar-cba ruta/seEconomCBA....xlsx  # Excel del INEC
```

El token del BCCR se solicita gratis en el [SDDE del BCCR](https://sdd.bccr.fi.cr/). El Excel de la CBA se descarga
de [inec.cr › Costo Canasta Básica Alimentaria](https://inec.cr/tematicas/listado?topics=134,135)
(«Cuadros de series históricas de resultados de la CBA»).

Para la actualización diaria, agregar al programador de tareas:
`php artisan schedule:run` cada minuto.

> **XAMPP / Apache con PHP thread-safe:** ejecutar `php artisan config:cache` (y repetirlo cada vez que cambie
> el `.env`). Sin la caché, las peticiones simultáneas pueden arrancar Laravel sin leer el `.env`
> («No application encryption key»), porque las variables de entorno se comparten entre hilos.
> Los tests usan su propia ruta de caché (`APP_CONFIG_CACHE` en `phpunit.xml`), así que nunca tocan la base real.

## Estructura

```
app/Services/BccrService.php          Cliente cURL del API SDDE
app/Services/SincronizadorBccr.php    Descarga y guarda los cuadros configurados
app/Services/CanastaImportService.php Lee el Excel del INEC (cuadros 1, 2 y 3)
app/Services/InflacionService.php     Cálculos y endpoints (con caché versionada)
config/bccr.php                       Catálogo de indicadores y meta de inflación
public/assets/js/dashboard.js         Gráficos y AJAX
```

## Tests

```bash
php artisan test
```
