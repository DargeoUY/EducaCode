from contextlib import asynccontextmanager
from fastapi import FastAPI, WebSocket, WebSocketDisconnect, Request
from fastapi.middleware.cors import CORSMiddleware
from app.routers import auth, admin, docente, estudiantes, notas, ide, compartir
from app.services.compartir import manager
from app.database import init_db, engine, async_session
from app.models import Base, LogActividad
from app.config import ADMIN_DEFAULT_USER, ADMIN_DEFAULT_PASSWORD, ADMIN_KEY
from app.auth import hash_password
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from app.models import Usuario


async def seed_admin():
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


@asynccontextmanager
async def lifespan(app: FastAPI):
    await init_db()
    await seed_admin()
    yield


app = FastAPI(title="Materiales1 API", version="1.0.0", lifespan=lifespan)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(auth.router)
app.include_router(admin.router)
app.include_router(docente.router)
app.include_router(estudiantes.router)
app.include_router(notas.router)
app.include_router(ide.router)
app.include_router(compartir.router)


@app.get("/api/health")
async def health():
    return {"ok": True, "status": "running"}


@app.post("/api/seguridad/log")
async def log_seguridad(request: Request):
    try:
        data = await request.json()
        async with async_session() as db:
            log = LogActividad(
                cedula_estudiante=data.get("cedula", ""),
                evento=data.get("evento", "desconocido"),
                nivel_id=data.get("nivel_id"),
            )
            db.add(log)
            await db.commit()
    except Exception:
        pass
    return {"ok": True}


@app.websocket("/ws/compartir/{codigo_sala}")
async def websocket_compartir(websocket: WebSocket, codigo_sala: str, token: str = None):
    codigo_sala = codigo_sala.upper()
    rol = "docente" if token else "estudiante"

    await manager.connect(websocket, codigo_sala, rol)
    try:
        while True:
            data = await websocket.receive_json()
            msg_type = data.get("type")

            if msg_type == "content":
                content = data.get("content", "")
                language = data.get("language", "python")
                cursor = data.get("cursor", None)
                manager.save_content(codigo_sala, content, language)
                await manager.broadcast(codigo_sala, {
                    "type": "content",
                    "content": content,
                    "language": language,
                    "cursor": cursor,
                }, websocket)

            elif msg_type == "cursor":
                await manager.broadcast(codigo_sala, {
                    "type": "cursor",
                    "cursor": data.get("cursor"),
                }, websocket)

            elif msg_type == "language":
                await manager.broadcast(codigo_sala, {
                    "type": "language",
                    "language": data.get("language", "python"),
                }, websocket)

    except WebSocketDisconnect:
        await manager.disconnect(websocket, codigo_sala)
    except Exception:
        await manager.disconnect(websocket, codigo_sala)
