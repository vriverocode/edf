import json

import openpyxl

SRC = "referencias/lectura_de_agua.xlsx"
DST = "storage/app/water_readings.json"
MONTHS = ["ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO"]

wb = openpyxl.load_workbook(SRC, data_only=True)
ws = wb["Hoja1"]

rows = []
for row in ws.iter_rows(min_row=6, values_only=True):
    dpto = row[0]
    if dpto is None:
        continue
    lecturas = {}
    for i, name in enumerate(MONTHS):
        value = row[2 + i]
        if isinstance(value, float):
            value = round(value, 2)
        lecturas[str(i + 1)] = value
    rows.append({"dpto": dpto, "propietario": row[1], "lecturas": lecturas})

with open(DST, "w", encoding="utf-8") as f:
    json.dump(rows, f, ensure_ascii=False, indent=2)

print(f"Exportadas {len(rows)} filas -> {DST}")
