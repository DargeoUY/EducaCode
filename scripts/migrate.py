import asyncio
import csv
import sys
import os
from pathlib import Path

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'backend'))

from app.database import async_session, init_db
from app.models import Estudiante, Nota, Grupo, Nivel, Curso
from sqlalchemy import select


async def importar_estudiantes_csv(csv_path: str, grupo_id: int):
    async with async_session() as db:
        contador = 0
        with open(csv_path, newline='', encoding='utf-8-sig') as f:
            reader = csv.reader(f)
            next(reader, None)
            for row in reader:
                if len(row) < 2:
                    continue
                cedula = row[0].strip()
                nombre = row[1].strip()
                if not cedula or not nombre:
                    continue
                result = await db.execute(select(Estudiante).where(Estudiante.cedula == cedula))
                if result.scalar_one_or_none():
                    print(f"  [SKIP] {cedula} ya existe")
                    continue
                db.add(Estudiante(cedula=cedula, nombre=nombre, grupo_id=grupo_id))
                contador += 1
        await db.commit()
        print(f"  {contador} estudiantes importados al grupo {grupo_id}")


async def importar_notas_csv(csv_path: str, nivel_id: int):
    async with async_session() as db:
        contador = 0
        with open(csv_path, newline='', encoding='utf-8-sig') as f:
            reader = csv.reader(f)
            next(reader, None)
            for row in reader:
                if len(row) < 2:
                    continue
                cedula = row[0].strip()
                try:
                    nota_val = float(row[1].strip())
                except ValueError:
                    continue
                tiempo = int(row[2].strip()) if len(row) > 2 and row[2].strip().isdigit() else 0
                intento = int(row[3].strip()) if len(row) > 3 and row[3].strip().isdigit() else 1
                db.add(Nota(cedula_estudiante=cedula, nivel_id=nivel_id, nota=nota_val, tiempo_seg=tiempo, intento=intento))
                contador += 1
        await db.commit()
        print(f"  {contador} notas importadas al nivel {nivel_id}")


async def main():
    await init_db()
    print("=== Script de migración de datos ===")
    print()
    print("Uso:")
    print("  python migrate.py estudiantes <csv_file> <grupo_id>")
    print("  python migrate.py notas <csv_file> <nivel_id>")
    print()
    print("Exportá tus Google Sheets como CSV (Archivo > Descargar > CSV)")
    print("y luego ejecutá el comando correspondiente.")
    print()

    if len(sys.argv) < 2:
        print("IDs de niveles de referencia:")
        async with async_session() as db:
            result = await db.execute(select(Nivel).order_by(Nivel.id))
            for n in result.scalars().all():
                print(f"  Nivel {n.id}: {n.titulo} (Curso ID {n.curso_id})")
        return

    accion = sys.argv[1]
    if accion == "estudiantes" and len(sys.argv) >= 4:
        csv_path = sys.argv[2]
        grupo_id = int(sys.argv[3])
        if not os.path.exists(csv_path):
            print(f"Error: archivo no encontrado: {csv_path}")
            return
        await importar_estudiantes_csv(csv_path, grupo_id)
    elif accion == "notas" and len(sys.argv) >= 4:
        csv_path = sys.argv[2]
        nivel_id = int(sys.argv[3])
        if not os.path.exists(csv_path):
            print(f"Error: archivo no encontrado: {csv_path}")
            return
        await importar_notas_csv(csv_path, nivel_id)
    else:
        print("Comando no reconocido. Usá: estudiantes <csv> <grupo_id> o notas <csv> <nivel_id>")


if __name__ == "__main__":
    asyncio.run(main())
