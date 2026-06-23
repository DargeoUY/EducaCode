from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from pydantic import BaseModel
from app.database import get_db
from app.models import Estudiante, Grupo, Curso, Nivel, Visibilidad

router = APIRouter(prefix="/api/estudiantes", tags=["estudiantes"])


class EstudianteRegistro(BaseModel):
    cedula: str
    nombre: str
    codigo_grupo: str


@router.get("/validar/{cedula}")
async def validar_estudiante(cedula: str, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Estudiante).where(Estudiante.cedula == cedula))
    estudiante = result.scalar_one_or_none()
    if not estudiante:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Estudiante no encontrado")
    result = await db.execute(select(Grupo).where(Grupo.id == estudiante.grupo_id))
    grupo = result.scalar_one_or_none()
    return {
        "ok": True,
        "cedula": estudiante.cedula,
        "nombre": estudiante.nombre,
        "grupo": grupo.nombre if grupo else None,
        "grupo_id": estudiante.grupo_id,
    }


@router.post("/registrar")
async def registrar_estudiante(data: EstudianteRegistro, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Estudiante).where(Estudiante.cedula == data.cedula))
    if result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="La cédula ya está registrada")
    result = await db.execute(select(Grupo).where(Grupo.codigo_acceso == data.codigo_grupo.upper()))
    grupo = result.scalar_one_or_none()
    if not grupo:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Código de grupo inválido")
    estudiante = Estudiante(cedula=data.cedula, nombre=data.nombre, grupo_id=grupo.id)
    db.add(estudiante)
    await db.commit()
    return {"ok": True, "cedula": estudiante.cedula, "nombre": estudiante.nombre, "grupo": grupo.nombre}


@router.get("/{cedula}/cursos")
async def cursos_visibles(cedula: str, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Estudiante).where(Estudiante.cedula == cedula))
    estudiante = result.scalar_one_or_none()
    if not estudiante:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Estudiante no encontrado")

    result = await db.execute(select(Curso).order_by(Curso.orden))
    cursos = result.scalars().all()

    cursos_visibles = []
    for curso in cursos:
        result = await db.execute(
            select(Visibilidad).where(Visibilidad.grupo_id == estudiante.grupo_id, Visibilidad.curso_id == curso.id)
        )
        vis = result.scalar_one_or_none()
        cursos_visibles.append({
            "curso_id": curso.id,
            "nombre": curso.nombre,
            "slug": curso.slug,
            "icono": curso.icono,
            "mostrar_lecciones": vis.mostrar_lecciones if vis else True,
            "mostrar_ejercicios": vis.mostrar_ejercicios if vis else True,
            "mostrar_evaluaciones": vis.mostrar_evaluaciones if vis else False,
        })
    return cursos_visibles


@router.get("/{cedula}/niveles")
async def niveles_visibles(cedula: str, curso_slug: str = None, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Estudiante).where(Estudiante.cedula == cedula))
    estudiante = result.scalar_one_or_none()
    if not estudiante:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Estudiante no encontrado")

    query = select(Nivel).join(Curso).order_by(Nivel.orden)
    if curso_slug:
        result = await db.execute(select(Curso).where(Curso.slug == curso_slug))
        curso = result.scalar_one_or_none()
        if not curso:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Curso no encontrado")
        query = query.where(Curso.id == curso.id)
        result = await db.execute(
            select(Visibilidad).where(Visibilidad.grupo_id == estudiante.grupo_id, Visibilidad.curso_id == curso.id)
        )
        vis = result.scalar_one_or_none()
    else:
        vis = None

    result = await db.execute(query)
    niveles = result.scalars().all()

    return [
        {
            "id": n.id,
            "curso_slug": curso_slug or "",
            "numero": n.numero,
            "titulo": n.titulo,
            "tipo": n.tipo,
            "archivo_html": n.archivo_html,
            "visible": (
                (n.tipo == "leccion" and (vis.mostrar_lecciones if vis else True)) or
                (n.tipo == "ejercicios" and (vis.mostrar_ejercicios if vis else True)) or
                (n.tipo == "evaluacion" and (vis.mostrar_evaluaciones if vis else False))
            ) if vis else True,
        }
        for n in niveles
    ]
