from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func, desc
from pydantic import BaseModel
from app.database import get_db
from app.models import Nota, Estudiante, Grupo, Nivel, Curso, Usuario
from app.auth import get_current_user, require_docente

router = APIRouter(prefix="/api/notas", tags=["notas"])


class NotaSubmit(BaseModel):
    cedula_estudiante: str
    nivel_id: int
    nota: float
    tiempo_seg: int = 0
    intento: int = 1


@router.get("")
async def listar_notas(
    grupo_id: int = Query(None),
    curso_id: int = Query(None),
    nivel_id: int = Query(None),
    cedula: str = Query(None),
    db: AsyncSession = Depends(get_db),
    user: Usuario = Depends(require_docente),
):
    query = (
        select(Nota, Estudiante, Nivel, Curso)
        .join(Estudiante, Nota.cedula_estudiante == Estudiante.cedula)
        .join(Nivel, Nota.nivel_id == Nivel.id)
        .join(Curso, Nivel.curso_id == Curso.id)
    )

    if grupo_id:
        result = await db.execute(select(Grupo).where(Grupo.id == grupo_id, Grupo.docente_id == user.id))
        if not result.scalar_one_or_none():
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="No tenés acceso a ese grupo")
        query = query.where(Estudiante.grupo_id == grupo_id)
    else:
        subquery = select(Grupo.id).where(Grupo.docente_id == user.id).subquery()
        query = query.where(Estudiante.grupo_id.in_(subquery))

    if curso_id:
        query = query.where(Curso.id == curso_id)
    if nivel_id:
        query = query.where(Nivel.id == nivel_id)
    if cedula:
        query = query.where(Nota.cedula_estudiante == cedula)

    query = query.order_by(desc(Nota.fecha))
    result = await db.execute(query)
    rows = result.all()

    return [
        {
            "id": row.Nota.id,
            "cedula": row.Nota.cedula_estudiante,
            "nombre_estudiante": row.Estudiante.nombre,
            "curso": row.Curso.nombre,
            "nivel_numero": row.Nivel.numero,
            "nivel_titulo": row.Nivel.titulo,
            "nota": float(row.Nota.nota),
            "tiempo_seg": row.Nota.tiempo_seg,
            "intento": row.Nota.intento,
            "fecha": str(row.Nota.fecha),
        }
        for row in rows
    ]


@router.post("")
async def guardar_nota(data: NotaSubmit, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Estudiante).where(Estudiante.cedula == data.cedula_estudiante))
    if not result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Estudiante no encontrado")
    result = await db.execute(select(Nivel).where(Nivel.id == data.nivel_id))
    if not result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Nivel no encontrado")
    nota = Nota(
        cedula_estudiante=data.cedula_estudiante,
        nivel_id=data.nivel_id,
        nota=data.nota,
        tiempo_seg=data.tiempo_seg,
        intento=data.intento,
    )
    db.add(nota)
    await db.commit()
    return {"ok": True, "id": nota.id, "mensaje": "Nota guardada"}


@router.get("/resumen/{cedula}")
async def resumen_estudiante(cedula: str, db: AsyncSession = Depends(get_db)):
    query = (
        select(Curso.nombre, func.avg(Nota.nota), func.count(Nota.id))
        .join(Nivel, Nota.nivel_id == Nivel.id)
        .join(Curso, Nivel.curso_id == Curso.id)
        .where(Nota.cedula_estudiante == cedula)
        .group_by(Curso.nombre)
    )
    result = await db.execute(query)
    rows = result.all()
    return [
        {"curso": nombre, "promedio": float(avg) if avg else 0, "evaluaciones": count}
        for nombre, avg, count in rows
    ]


@router.get("/exportar")
async def exportar_notas_csv(
    grupo_id: int = None,
    curso_id: int = None,
    cedula: str = None,
    db: AsyncSession = Depends(get_db),
    user: Usuario = Depends(require_docente),
):
    from fastapi.responses import StreamingResponse
    import csv, io

    query = (
        select(Nota, Estudiante, Nivel, Curso)
        .join(Estudiante, Nota.cedula_estudiante == Estudiante.cedula)
        .join(Nivel, Nota.nivel_id == Nivel.id)
        .join(Curso, Nivel.curso_id == Curso.id)
    )

    if grupo_id:
        query = query.where(Estudiante.grupo_id == grupo_id)
    else:
        subquery = select(Grupo.id).where(Grupo.docente_id == user.id).subquery()
        query = query.where(Estudiante.grupo_id.in_(subquery))
    if curso_id:
        query = query.where(Curso.id == curso_id)
    if cedula:
        query = query.where(Nota.cedula_estudiante == cedula)

    result = await db.execute(query)
    rows = result.all()

    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(["Cédula", "Nombre", "Curso", "Nivel", "Nota", "Tiempo (s)", "Intento", "Fecha"])
    for row in rows:
        writer.writerow([
            row.Nota.cedula_estudiante, row.Estudiante.nombre, row.Curso.nombre,
            f"Nivel {row.Nivel.numero}: {row.Nivel.titulo}", float(row.Nota.nota),
            row.Nota.tiempo_seg, row.Nota.intento, str(row.Nota.fecha),
        ])

    output.seek(0)
    return StreamingResponse(
        iter([output.getvalue()]),
        media_type="text/csv",
        headers={"Content-Disposition": "attachment; filename=notas.csv"},
    )
