import asyncio
from app.database import init_db
from app.config import ADMIN_DEFAULT_USER, ADMIN_DEFAULT_PASSWORD
from app.auth import hash_password
from app.models import Usuario
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from app.database import async_session


async def seed():
    await init_db()
    async with async_session() as db:
        result = await db.execute(select(Usuario).where(Usuario.rol == "admin"))
        if not result.scalar_one_or_none():
            admin = Usuario(
                cedula=ADMIN_DEFAULT_USER,
                nombre="Administrador",
                password_hash=hash_password(ADMIN_DEFAULT_PASSWORD),
                rol="admin",
                activo=True,
            )
            db.add(admin)
            await db.commit()
            print(f"Admin creado: {ADMIN_DEFAULT_USER} / {ADMIN_DEFAULT_PASSWORD}")
        else:
            print("Admin ya existe")


if __name__ == "__main__":
    asyncio.run(seed())
