from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from pydantic import BaseModel
from app.database import get_db
from app.models import Usuario, Grupo
from app.auth import get_current_user, require_admin

router = APIRouter(prefix="/api/admin", tags=["admin"])


class AprobarRequest(BaseModel):
    activo: bool


class CursoCreate(BaseModel):
    nombre: str
    slug: str
    icono: str = "📚"
    orden: int = 0


@router.get("/docentes")
async def listar_docentes(
    pendientes: bool = Query(False),
    db: AsyncSession = Depends(get_db),
    user: Usuario = Depends(require_admin),
):
    query = select(Usuario).where(Usuario.rol == "docente")
    if pendientes:
        query = query.where(Usuario.activo == False)
    result = await db.execute(query)
    docentes = result.scalars().all()
    return [
        {"id": d.id, "cedula": d.cedula, "nombre": d.nombre, "email": d.email, "activo": d.activo, "created_at": str(d.created_at)}
        for d in docentes
    ]


@router.post("/docentes/{docente_id}/aprobar")
async def aprobar_docente(
    docente_id: int,
    data: AprobarRequest,
    db: AsyncSession = Depends(get_db),
    user: Usuario = Depends(require_admin),
):
    result = await db.execute(select(Usuario).where(Usuario.id == docente_id, Usuario.rol == "docente"))
    docente = result.scalar_one_or_none()
    if not docente:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Docente no encontrado")
    docente.activo = data.activo
    await db.commit()
    estado = "aprobado" if data.activo else "desaprobado"
    return {"ok": True, "mensaje": f"Docente {docente.nombre} {estado}"}


@router.get("/estadisticas")
async def estadisticas(
    db: AsyncSession = Depends(get_db),
    user: Usuario = Depends(require_admin),
):
    from sqlalchemy import func
    total_docentes = (await db.execute(select(func.count()).select_from(Usuario).where(Usuario.rol == "docente"))).scalar()
    total_activos = (await db.execute(select(func.count()).select_from(Usuario).where(Usuario.rol == "docente", Usuario.activo == True))).scalar()
    total_pendientes = (await db.execute(select(func.count()).select_from(Usuario).where(Usuario.rol == "docente", Usuario.activo == False))).scalar()
    total_grupos = (await db.execute(select(func.count()).select_from(Grupo))).scalar()
    return {
        "total_docentes": total_docentes,
        "docentes_activos": total_activos,
        "docentes_pendientes": total_pendientes,
        "total_grupos": total_grupos,
    }
