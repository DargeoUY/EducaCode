import secrets
import string
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func
from pydantic import BaseModel, EmailStr
from app.database import get_db
from app.models import Usuario
from app.auth import hash_password, verify_password, create_token, get_current_user, require_admin
from app.config import ADMIN_KEY

router = APIRouter(prefix="/api/auth", tags=["auth"])


class LoginRequest(BaseModel):
    cedula: str
    password: str


class RegisterRequest(BaseModel):
    cedula: str
    nombre: str
    email: EmailStr | None = None
    password: str


class AdminLoginRequest(BaseModel):
    usuario: str
    password: str


@router.post("/login")
async def login(data: LoginRequest, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Usuario).where(Usuario.cedula == data.cedula))
    user = result.scalar_one_or_none()
    if not user or not verify_password(data.password, user.password_hash):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Credenciales inválidas")
    if not user.activo:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Cuenta pendiente de aprobación")
    token = create_token(user.id, user.cedula, user.rol)
    return {"ok": True, "token": token, "rol": user.rol, "nombre": user.nombre, "cedula": user.cedula, "id": user.id}


@router.post("/admin-login")
async def admin_login(data: AdminLoginRequest, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Usuario).where(Usuario.cedula == data.usuario, Usuario.rol == "admin"))
    user = result.scalar_one_or_none()
    if not user or not verify_password(data.password, user.password_hash):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Credenciales inválidas")
    token = create_token(user.id, user.cedula, user.rol)
    return {"ok": True, "token": token, "rol": user.rol, "nombre": user.nombre}


@router.post("/register")
async def register(data: RegisterRequest, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Usuario).where(Usuario.cedula == data.cedula))
    if result.scalar_one_or_none():
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="La cédula ya está registrada")
    if data.email:
        result = await db.execute(select(Usuario).where(Usuario.email == data.email))
        if result.scalar_one_or_none():
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="El email ya está registrado")

    user = Usuario(
        cedula=data.cedula,
        nombre=data.nombre,
        email=data.email,
        password_hash=hash_password(data.password),
        rol="docente",
        activo=False,
    )
    db.add(user)
    await db.commit()
    return {"ok": True, "mensaje": "Solicitud de registro enviada. Un administrador debe aprobarla."}


@router.get("/me")
async def me(user: Usuario = Depends(get_current_user)):
    return {"ok": True, "id": user.id, "cedula": user.cedula, "nombre": user.nombre, "rol": user.rol, "email": user.email}
