import csv
import io
from fastapi import APIRouter, Depends, HTTPException, status, UploadFile, File, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func, and_
from pydantic import BaseModel
from app.database import get_db
from app.models import Usuario, Grupo, Estudiante, Curso, Visibilidad
from app.auth import get_current_user, require_docente, hash_password

router = APIRouter(prefix="/api/docente", tags=["docente"])


class GrupoCreate(BaseModel):
    nombre: str


class EstudianteCreate(BaseModel):
    cedula: str
    nombre: str


class VisibilidadUpdate(BaseModel):
    mostrar_lecciones: bool = True
    mostrar_ejercicios: bool = True
    mostrar_evaluaciones: bool = False


@router.get("/grupos")
async def listar_grupos(db: AsyncSession = Depends(get_db), user: Usuario = Depends(require_docente)):
    result = await db.execute(select(Grupo).where(Grupo.docente_id == user.id))
    grupos = result.scalars().all()
    return [
        {
            "id": g.id,
            "nombre": g.nombre,
            "codigo_acceso": g.codigo_acceso,
            "cantidad_estudiantes": (await db.execute(
                select(func.count()).select_from(Estudiante).where(Estudiante.grupo_id == g.id)
            )).scalar(),
        }
        for g in grupos
    ]


@router.post("/grupos")
async def crear_grupo(data: GrupoCreate, db: AsyncSession = Depends(get_db), user: Usuario = Depends(require_docente)):
    from app.models import generar_codigo
    grupo = Grupo(docente_id=user.id, nombre=data.nombre, codigo_acceso=generar_codigo())
    db.add(grupo)
    await db.commit()
    await db.refresh(grupo)
    return {"ok": True, "id": grupo.id, "nombre": grupo.nombre, "codigo_acceso": grupo.codigo_acceso}


@router.delete("/grupos/{grupo_id}")
async def eliminar_grupo(grupo_id: int, db: AsyncSession = Depends(get_db), user: Usuario = Depends(require_docente)):
    result = await db.execute(select(Grupo).where(Grupo.id == grupo_id, Grupo.docente_id == user.id))
    grupo = result.scalar_one_or_none()
    if not grupo:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Grupo no encontrado")
    await db.delete(grupo)
    await db.commit()
    return {"ok": True, "mensaje": "Grupo eliminado"}


@router.get("/grupos/{grupo_id}/estudiantes")
async def listar_estudiantes(grupo_id: int, db: AsyncSession = Depends(get_db), user: Usuario = Depends(require_docente)):
    result = await db.execute(select(Grupo).where(Grupo.id == grupo_id, Grupo.docente_id == user.id))
    if not result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Grupo no encontrado")
    result = await db.execute(select(Estudiante).where(Estudiante.grupo_id == grupo_id))
    estudiantes = result.scalars().all()
    return [{"cedula": e.cedula, "nombre": e.nombre, "created_at": str(e.created_at)} for e in estudiantes]


@router.post("/grupos/{grupo_id}/estudiantes")
async def agregar_estudiante(grupo_id: int, data: EstudianteCreate, db: AsyncSession = Depends(get_db), user: Usuario = Depends(require_docente)):
    result = await db.execute(select(Grupo).where(Grupo.id == grupo_id, Grupo.docente_id == user.id))
    if not result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Grupo no encontrado")
    result = await db.execute(select(Estudiante).where(Estudiante.cedula == data.cedula))
    if result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="La cédula ya está registrada en otro grupo")
    estudiante = Estudiante(cedula=data.cedula, nombre=data.nombre, grupo_id=grupo_id)
    db.add(estudiante)
    await db.commit()
    return {"ok": True, "cedula": estudiante.cedula, "nombre": estudiante.nombre}


@router.post("/grupos/{grupo_id}/estudiantes/csv")
async def cargar_estudiantes_csv(
    grupo_id: int,
    file: UploadFile = File(...),
    db: AsyncSession = Depends(get_db),
    user: Usuario = Depends(require_docente),
):
    result = await db.execute(select(Grupo).where(Grupo.id == grupo_id, Grupo.docente_id == user.id))
    if not result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Grupo no encontrado")
    content = await file.read()
    reader = csv.reader(io.StringIO(content.decode("utf-8-sig")))
    creados = 0
    for row in reader:
        if len(row) < 2:
            continue
        cedula = row[0].strip()
        nombre = row[1].strip()
        if not cedula or not nombre:
            continue
        result = await db.execute(select(Estudiante).where(Estudiante.cedula == cedula))
        if result.scalar_one_or_none():
            continue
        db.add(Estudiante(cedula=cedula, nombre=nombre, grupo_id=grupo_id))
        creados += 1
    await db.commit()
    return {"ok": True, "mensaje": f"{creados} estudiantes cargados"}


@router.get("/grupos/{grupo_id}/csv")
async def descargar_plantilla_csv(grupo_id: int, db: AsyncSession = Depends(get_db), user: Usuario = Depends(require_docente)):
    result = await db.execute(select(Grupo).where(Grupo.id == grupo_id, Grupo.docente_id == user.id))
    if not result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Grupo no encontrado")
    return "cedula,nombre\n12345678,Juan Pérez\n87654321,María García\n"


@router.get("/visibilidad/{grupo_id}")
async def ver_visibilidad(grupo_id: int, db: AsyncSession = Depends(get_db), user: Usuario = Depends(require_docente)):
    result = await db.execute(select(Grupo).where(Grupo.id == grupo_id, Grupo.docente_id == user.id))
    if not result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Grupo no encontrado")
    result = await db.execute(select(Curso).order_by(Curso.orden))
    cursos = result.scalars().all()
    visibilidades = []
    for curso in cursos:
        result = await db.execute(
            select(Visibilidad).where(Visibilidad.grupo_id == grupo_id, Visibilidad.curso_id == curso.id)
        )
        vis = result.scalar_one_or_none()
        visibilidades.append({
            "curso_id": curso.id,
            "curso_nombre": curso.nombre,
            "curso_slug": curso.slug,
            "curso_icono": curso.icono,
            "mostrar_lecciones": vis.mostrar_lecciones if vis else True,
            "mostrar_ejercicios": vis.mostrar_ejercicios if vis else True,
            "mostrar_evaluaciones": vis.mostrar_evaluaciones if vis else False,
            "fecha_desde": str(vis.fecha_desde) if vis and vis.fecha_desde else None,
            "fecha_hasta": str(vis.fecha_hasta) if vis and vis.fecha_hasta else None,
        })
    return visibilidades


@router.put("/visibilidad/{grupo_id}/{curso_id}")
async def actualizar_visibilidad(
    grupo_id: int,
    curso_id: int,
    data: VisibilidadUpdate,
    db: AsyncSession = Depends(get_db),
    user: Usuario = Depends(require_docente),
):
    result = await db.execute(select(Grupo).where(Grupo.id == grupo_id, Grupo.docente_id == user.id))
    if not result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Grupo no encontrado")
    result = await db.execute(
        select(Visibilidad).where(Visibilidad.grupo_id == grupo_id, Visibilidad.curso_id == curso_id)
    )
    vis = result.scalar_one_or_none()
    if vis:
        vis.mostrar_lecciones = data.mostrar_lecciones
        vis.mostrar_ejercicios = data.mostrar_ejercicios
        vis.mostrar_evaluaciones = data.mostrar_evaluaciones
    else:
        vis = Visibilidad(
            grupo_id=grupo_id,
            curso_id=curso_id,
            mostrar_lecciones=data.mostrar_lecciones,
            mostrar_ejercicios=data.mostrar_ejercicios,
            mostrar_evaluaciones=data.mostrar_evaluaciones,
        )
        db.add(vis)
    await db.commit()
    return {"ok": True, "mensaje": "Visibilidad actualizada"}
