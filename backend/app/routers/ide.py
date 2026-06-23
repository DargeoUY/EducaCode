from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from pydantic import BaseModel
import httpx
from app.database import get_db
from app.config import PISTON_API_URL
from app.models import Usuario
from app.auth import get_current_user

router = APIRouter(prefix="/api/ide", tags=["ide"])


class EjecutarRequest(BaseModel):
    lenguaje: str
    codigo: str
    version: str = "*"


LENGUAJES_MAP = {
    "python": {"language": "python", "version": "3.10.0"},
    "javascript": {"language": "javascript", "version": "18.15.0"},
    "html": {"_internal": True},
    "css": {"_internal": True},
    "php": {"language": "php", "version": "8.2.3"},
    "java": {"language": "java", "version": "15.0.2"},
    "cpp": {"language": "cpp", "version": "10.2.0"},
    "c": {"language": "c", "version": "10.2.0"},
    "csharp": {"language": "csharp", "version": "6.12.0"},
    "go": {"language": "go", "version": "1.16.2"},
    "rust": {"language": "rust", "version": "1.68.2"},
    "ruby": {"language": "ruby", "version": "3.0.1"},
    "sql": {"language": "sql", "version": "3.36.0"},
    "typescript": {"language": "typescript", "version": "5.0.3"},
    "bash": {"language": "bash", "version": "5.2.0"},
    "r": {"language": "r", "version": "4.1.1"},
    "kotlin": {"language": "kotlin", "version": "1.8.20"},
    "swift": {"language": "swift", "version": "5.3.3"},
    "dart": {"language": "dart", "version": "2.19.6"},
    "lua": {"language": "lua", "version": "5.4.4"},
    "perl": {"language": "perl", "version": "5.36.0"},
    "scala": {"language": "scala", "version": "3.2.2"},
    "haskell": {"language": "haskell", "version": "9.0.1"},
}


@router.post("/ejecutar")
async def ejecutar_codigo(data: EjecutarRequest, user: Usuario = Depends(get_current_user)):
    lang_info = LENGUAJES_MAP.get(data.lenguaje.lower())
    if not lang_info:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Lenguaje '{data.lenguaje}' no soportado")

    if lang_info.get("_internal"):
        return {
            "ok": True,
            "language": data.lenguaje,
            "run": {
                "stdout": "",
                "stderr": "",
                "output": f"[{data.lenguaje.upper()}] Ejecución en el navegador —{len(data.codigo)} caracteres listos",
                "code": 0,
            },
        }

    try:
        async with httpx.AsyncClient(timeout=30) as client:
            resp = await client.post(
                f"{PISTON_API_URL}/execute",
                json={
                    "language": lang_info["language"],
                    "version": lang_info["version"],
                    "files": [{"name": "main", "content": data.codigo}],
                },
            )
            if resp.status_code != 200:
                return {"ok": False, "error": f"Error del motor de ejecución: {resp.status_code}"}
            result = resp.json()
            return {"ok": True, "language": data.lenguaje, "run": result.get("run", {})}
    except httpx.RequestError as e:
        raise HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail=f"Motor de ejecución no disponible: {str(e)}")


@router.get("/lenguajes")
async def listar_lenguajes():
    return {
        "lenguajes": [
            {"id": k, "nombre": k.title(), "version": v.get("version", "navegador") if isinstance(v, dict) else v}
            for k, v in LENGUAJES_MAP.items()
        ]
    }
