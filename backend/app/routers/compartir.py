from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from pydantic import BaseModel
from app.database import get_db
from app.models import Usuario, ShareSession
from app.auth import get_current_user, require_docente

router = APIRouter(prefix="/api/compartir", tags=["compartir"])


class CompartirResponse(BaseModel):
    ok: bool
    codigo_sala: str | None = None
    mensaje: str | None = None


@router.post("/crear")
async def crear_sala(db: AsyncSession = Depends(get_db), user: Usuario = Depends(require_docente)):
    sala = ShareSession(docente_id=user.id, contenido="", lenguaje="python")
    db.add(sala)
    await db.commit()
    await db.refresh(sala)
    return {"ok": True, "codigo_sala": sala.codigo_sala, "id": sala.id}


@router.post("/cerrar")
async def cerrar_sala(
    sala_id: str = None,
    codigo_sala: str = None,
    db: AsyncSession = Depends(get_db),
    user: Usuario = Depends(require_docente),
):
    if sala_id:
        result = await db.execute(
            select(ShareSession).where(ShareSession.id == sala_id, ShareSession.docente_id == user.id)
        )
    elif codigo_sala:
        result = await db.execute(
            select(ShareSession).where(ShareSession.codigo_sala == codigo_sala.upper(), ShareSession.docente_id == user.id)
        )
    else:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Proveer sala_id o codigo_sala")

    sala = result.scalar_one_or_none()
    if not sala:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Sala no encontrada")
    sala.is_active = False
    await db.commit()
    return {"ok": True, "mensaje": "Sala cerrada"}


@router.get("/{codigo_sala}")
async def info_sala(codigo_sala: str, db: AsyncSession = Depends(get_db)):
    codigo_sala = codigo_sala.upper()
    result = await db.execute(select(ShareSession).where(ShareSession.codigo_sala == codigo_sala))
    sala = result.scalar_one_or_none()
    if not sala:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Sala no encontrada")
    if not sala.is_active:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="La sala ya fue cerrada")

    result = await db.execute(select(Usuario).where(Usuario.id == sala.docente_id))
    docente = result.scalar_one_or_none()

    return {
        "ok": True,
        "codigo_sala": sala.codigo_sala,
        "docente": docente.nombre if docente else "Desconocido",
        "lenguaje": sala.lenguaje,
        "is_active": sala.is_active,
    }


@router.get("")
async def listar_salas_activas(db: AsyncSession = Depends(get_db), user: Usuario = Depends(require_docente)):
    result = await db.execute(
        select(ShareSession).where(ShareSession.docente_id == user.id, ShareSession.is_active == True)
    )
    salas = result.scalars().all()
    return [
        {"id": s.id, "codigo_sala": s.codigo_sala, "lenguaje": s.lenguaje, "created_at": str(s.created_at)}
        for s in salas
    ]
