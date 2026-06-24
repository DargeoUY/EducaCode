from contextlib import asynccontextmanager
from fastapi import FastAPI, WebSocket, WebSocketDisconnect, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse, HTMLResponse
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

STATIC_DIR = "/data/static"

app.mount("/static", StaticFiles(directory=STATIC_DIR, html=True), name="static")


@app.get("/login", response_class=HTMLResponse)
async def login_page():
    return FileResponse(f"{STATIC_DIR}/login.html")


@app.get("/admin/{rest:path}", response_class=HTMLResponse)
async def admin_pages(rest: str = ""):
    import os
    if rest.endswith(".html") or rest.endswith(".js") or rest.endswith(".css"):
        path = f"{STATIC_DIR}/admin/{rest}"
    else:
        path = f"{STATIC_DIR}/admin/{rest}.html"
        if not os.path.isfile(path):
            path = f"{STATIC_DIR}/admin/{rest}"
            if not os.path.isfile(path) and not rest:
                path = f"{STATIC_DIR}/admin/index.html"
    if os.path.isfile(path):
        return FileResponse(path)
    return FileResponse(f"{STATIC_DIR}/admin/index.html")


@app.get("/docente/{rest:path}", response_class=HTMLResponse)
async def docente_pages(rest: str = ""):
    import os
    if rest.endswith(".html") or rest.endswith(".js") or rest.endswith(".css"):
        path = f"{STATIC_DIR}/docente/{rest}"
    else:
        path = f"{STATIC_DIR}/docente/{rest}.html"
        if not os.path.isfile(path):
            path = f"{STATIC_DIR}/docente/{rest}"
            if not os.path.isfile(path) and not rest:
                path = f"{STATIC_DIR}/docente/index.html"
    if os.path.isfile(path):
        return FileResponse(path)
    return FileResponse(f"{STATIC_DIR}/docente/index.html")


@app.get("/ide", response_class=HTMLResponse)
async def ide_page():
    return FileResponse(f"{STATIC_DIR}/ide/ide.html")


@app.get("/", response_class=HTMLResponse)
async def root():
    return FileResponse(f"{STATIC_DIR}/index.html")


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
