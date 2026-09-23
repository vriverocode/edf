-- ============================================================
-- Eliminar departaments duplicados por padding de ceros
-- (EST-43 vs EST-043, EST-2 vs EST-002, etc.)
--
-- Conserva los NUEVOS con 0/00; elimina los ORIGINALES sin 0/00.
-- Primero reasigna hijos (quotas, pagos, etc.) al ID con ceros
-- para no perder historial por CASCADE.
--
-- Ejecutar en: objetivo_edf_app  (NO en edf_app de desarrollo)
-- ============================================================
-- 1) PREVISUALIZAR pares que se van a tocar:
SELECT o.id   AS old_id,
       o.number AS old_number,
       n.id   AS new_id,
       n.number AS new_number
FROM departaments o
JOIN departaments n
  ON n.type = o.type
 AND n.deleted_at IS NULL
 AND o.deleted_at IS NULL
 AND n.number = CONCAT(
       SUBSTRING_INDEX(o.number, '-', 1), '-',
       LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
     )
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$'
  AND n.number REGEXP '^[A-Za-z]+-[0-9]{3}$'
ORDER BY o.number;

-- 2) REASIGNAR hijos al ID con ceros (solo pares confirmados arriba)
--    Tablas con departament_id:
SET FOREIGN_KEY_CHECKS = 0;

-- credit_balances (UNIQUE departament_id):
-- A) borrar balance del NUEVO (placeholder) si existe par con original
DELETE cb
FROM credit_balances cb
JOIN departaments n ON n.id = cb.departament_id
WHERE n.number REGEXP '^[A-Za-z]+-[0-9]{3}$'
  AND EXISTS (
    SELECT 1 FROM departaments o
    WHERE o.type = n.type
      AND o.deleted_at IS NULL
      AND n.deleted_at IS NULL
      AND o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$'
      AND n.number = CONCAT(
            SUBSTRING_INDEX(o.number, '-', 1), '-',
            LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
          )
      AND o.id <> n.id
  );

-- B) mover el balance del ORIGINAL al nuevo ID
UPDATE credit_balances cb
JOIN departaments o ON o.id = cb.departament_id
SET cb.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.deleted_at IS NULL
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$'
  AND EXISTS (
    SELECT 1 FROM departaments n
    WHERE n.type = o.type
      AND n.deleted_at IS NULL
      AND n.number = CONCAT(
            SUBSTRING_INDEX(o.number, '-', 1), '-',
            LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
          )
  );

UPDATE quotas q
JOIN departaments o ON o.id = q.departament_id
SET q.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
    AND n.deleted_at IS NULL
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$';

UPDATE peoples_x_departments p
JOIN departaments o ON o.id = p.departament_id
SET p.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
    AND n.deleted_at IS NULL
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$';

UPDATE visits v
JOIN departaments o ON o.id = v.departament_id
SET v.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
    AND n.deleted_at IS NULL
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$';

UPDATE airbnb_rents a
JOIN departaments o ON o.id = a.departament_id
SET a.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
    AND n.deleted_at IS NULL
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$';

UPDATE water_readings w
JOIN departaments o ON o.id = w.departament_id
SET w.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
    AND n.deleted_at IS NULL
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$';

UPDATE multas m
JOIN departaments o ON o.id = m.departament_id
SET m.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
    AND n.deleted_at IS NULL
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$';

UPDATE bookings b
JOIN departaments o ON o.id = b.departament_id
SET b.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
    AND n.deleted_at IS NULL
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$';

UPDATE credit_transactions ct
JOIN departaments o ON o.id = ct.departament_id
SET ct.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
    AND n.deleted_at IS NULL
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$';

UPDATE department_charges dc
JOIN departaments o ON o.id = dc.departament_id
SET dc.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
    AND n.deleted_at IS NULL
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$';

UPDATE expenses e
JOIN departaments o ON o.id = e.departament_id
SET e.departament_id = (
  SELECT n.id FROM departaments n
  WHERE n.type = o.type
    AND n.number = CONCAT(
          SUBSTRING_INDEX(o.number, '-', 1), '-',
          LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
        )
    AND n.deleted_at IS NULL
  LIMIT 1
)
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$';

-- 3) ELIMINAR originales sin 0/00 que tengan par con ceros
DELETE o
FROM departaments o
JOIN departaments n
  ON n.type = o.type
 AND n.deleted_at IS NULL
 AND o.deleted_at IS NULL
 AND n.number = CONCAT(
       SUBSTRING_INDEX(o.number, '-', 1), '-',
       LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
     )
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$'
  AND n.number REGEXP '^[A-Za-z]+-[0-9]{3}$';

SET FOREIGN_KEY_CHECKS = 1;

-- 4) VERIFICAR: no deben quedar pares duplicados
SELECT o.id, o.number, n.id, n.number
FROM departaments o
JOIN departaments n
  ON n.type = o.type
 AND n.number = CONCAT(
       SUBSTRING_INDEX(o.number, '-', 1), '-',
       LPAD(SUBSTRING_INDEX(o.number, '-', -1), 3, '0')
     )
WHERE o.number REGEXP '^[A-Za-z]+-[0-9]{1,2}$'
  AND n.number REGEXP '^[A-Za-z]+-[0-9]{3}$';
-- Resultado esperado: 0 filas
