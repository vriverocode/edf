# Reporte de importación de reservas

Fuente: `reservas_new.csv` | Ejecutado: 2026-08-11 13:26:29 | Comando: `db:seed --class=BookingSeeder`

## Resumen

| Metrica | Valor |
| --- | --- |
| Filas leidas | 11 |
| Bookings creados | 10 |
| Omitidos | 1 |
| Duplicados | 1 |
| Areas no encontradas | 0 |
| Departamentos no encontrados | 0 |
| Sin usuario (depto sin user_id) | 0 |

## Mapeo usuario CSV -> dueño del departamento

Los usuarios del CSV (`depa405@pacifik.com`, etc.) no existen en la BD. Se usó el `user_id` (dueño) de cada departamento según `bookings` del seeder.

| Reserva CSV | Usuario CSV | Nombre CSV | Departamento | Usuario asignado (id) |
| --- | --- | --- | --- | --- |
| 6625 | depa1302@pacifik.com | Usuario 1302 | dpt-1302 | 531 |
| 6632 | depa1501@pacifik.com | Usuario 1501 | dpt-1501 | 554 |
| 6633 | depa1711@pacifik.com | Usuario 1711 | dpt-1711 | 587 |
| 6671 | depa312@pacifik.com | Usuario 312 | dpt-312 | 424 |
| 6674 | depa1011@pacifik.com | Usuario 1011 | dpt-1011 | 506 |
| 6675 | depa1011@pacifik.com | Usuario 1011 | dpt-1011 | 506 |
| 6701 | depa205@pacifik.com | Usuario 205 | dpt-205 | 407 |
| 6722 | depa1008@pacifik.com | Usuario 1008 | dpt-1008 | 503 |
| 6747 | depa1206@pacifik.com | Usuario 1206 | dpt-1206 | 523 |
| 6778 | depa208@pacifik.com | Usuario 208 | dpt-208 | 410 |

## Sin usuario (no importadas)

Ninguna: todos los departamentos tenían `user_id` asignado.
