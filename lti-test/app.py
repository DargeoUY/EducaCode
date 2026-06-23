"""
LTI 1.1 Backend para CREA (Schoology)
======================================
Recibe lanzamientos LTI desde CREA, sirve el quiz,
recibe la nota y la envia de vuelta a CREA via Outcomes.

Ejecutar localmente:  python app.py
Deploy en Render:      Web Service > Python > Start: gunicorn app:app
"""

import uuid
import logging
from flask import Flask, request, redirect, render_template_string, jsonify, session
from flask_cors import CORS

from lti import ToolProvider

# ============================================================
# CONFIGURACION — Carga desde config.json o variables de entorno
# (usa setup.html para generar config.json sin instalar Python)
# ============================================================

import json as _json
import os as _os

_CFG_PATH = _os.path.join(_os.path.dirname(__file__), "config.json")

if _os.path.exists(_CFG_PATH):
    with open(_CFG_PATH, "r") as _f:
        _cfg = _json.load(_f)
else:
    # Fallback: variables de entorno (Render dashboard > Environment)
    _cfg = {}

BASE_URL = _cfg.get("BASE_URL") or _os.environ.get("BASE_URL", "https://tu-app.onrender.com")
ADMIN_KEY = _cfg.get("ADMIN_KEY") or _os.environ.get("ADMIN_KEY", "admin-secreto-2024")
_consumers = _cfg.get("consumers", {})
# Soporte retrocompatible: clave unica vieja se migra al dict
if "LTI_CONSUMER_KEY" in _cfg and _cfg["LTI_CONSUMER_KEY"] not in _consumers:
    _consumers[_cfg["LTI_CONSUMER_KEY"]] = _cfg.get("LTI_CONSUMER_SECRET", "")


def _save_consumers():
    _cfg["consumers"] = _consumers
    with open(_CFG_PATH, "w") as _f:
        _json.dump(_cfg, _f, indent=2, ensure_ascii=False)
    log.info("consumers.json actualizado (%d claves)", len(_consumers))

# ============================================================

app = Flask(__name__)
CORS(app)
app.secret_key = str(uuid.uuid4())  # regenerada en cada deploy (solo para flash/session)

# Almacen de sesiones en memoria (para produccion usa SQLite/Redis)
sesiones = {}

# Configuracion de logging
logging.basicConfig(level=logging.INFO)
log = logging.getLogger(__name__)


# ------------------------------------------------------------------
# POST /lti/launch
# CREA envia un POST firmado con OAuth 1.0 con los datos del alumno.
# Validamos la firma, guardamos la sesion y redirigimos al quiz.
# ------------------------------------------------------------------

@app.route("/lti/launch", methods=["POST"])
def lti_launch():
    log.info("LTI Launch recibido desde CREA")

    # Validar firma OAuth: buscar secreto por consumer_key
    consumer_key = request.form.get("oauth_consumer_key", "")
    secret = _consumers.get(consumer_key)
    if not secret:
        log.warning(f"Consumer key no registrado: {consumer_key}")
        return f"Consumer key no registrado: {consumer_key}. Usa setup.html para registrar tus claves.", 403

    try:
        tool_provider = ToolProvider.from_flask_request(
            secret=secret,
            request=request,
        )
    except Exception as err:
        log.error(f"Error al parsear LTI: {err}")
        return "Error interno: LTI request invalido", 500

    if not tool_provider.is_valid_request:
        log.warning("LTI Launch: firma OAuth invalida")
        return "LTI Launch invalido (firma incorrecta)", 403

    # Extraer datos del alumno desde los parametros LTI
    nombre = request.form.get("lis_person_name_full", "Alumno")
    given = request.form.get("lis_person_name_given", "")
    family = request.form.get("lis_person_name_family", "")
    user_id = request.form.get("user_id", "")
    rol = request.form.get("roles", "")

    # Datos criticos para enviar la nota de vuelta
    outcome_url = request.form.get("lis_outcome_service_url", "")
    sourcedid = request.form.get("lis_result_sourcedid", "")

    # Parametro personalizado: nivel del quiz (configurado en CREA)
    nivel = request.form.get("custom_nivel", "1")

    log.info(
        f"Alumno: {nombre} | user_id={user_id} | "
        f"outcome={'SI' if outcome_url else 'NO'} | "
        f"nivel={nivel}"
    )

    # Guardar sesion
    token = str(uuid.uuid4())
    sesiones[token] = {
        "nombre": nombre,
        "user_id": user_id,
        "outcome_url": outcome_url,
        "sourcedid": sourcedid,
        "nivel": nivel,
        "nota": None,
        "consumer_key": consumer_key,
        "consumer_secret": secret,
    }

    # Redirigir al quiz con el token y nombre en la URL
    return redirect(f"{BASE_URL}/quiz/{token}?nombre={nombre}&nivel={nivel}")


# ------------------------------------------------------------------
# GET /quiz/<token>
# Sirve la pagina HTML del quiz con los datos del alumno inyectados.
# ------------------------------------------------------------------

@app.route("/quiz/<token>")
def quiz(token):
    datos = sesiones.get(token)
    if not datos:
        return "Sesion expirada o invalida. Volve a lanzar el quiz desde CREA.", 404

    nombre = datos["nombre"]
    nivel = datos["nivel"]
    outcome_url = datos["outcome_url"]

    calificacion_habilitada = bool(outcome_url)

    # Leer el HTML del quiz y reemplazar placeholders
    with open("test-quiz.html", "r", encoding="utf-8") as f:
        html = f.read()

    html = html.replace("{{TOKEN}}", token)
    html = html.replace("{{NOMBRE}}", nombre)
    html = html.replace("{{NIVEL}}", nivel)
    html = html.replace("{{CALIFICACION_HABILITADA}}", str(calificacion_habilitada).lower())

    return html


# ------------------------------------------------------------------
# POST /lti/submit
# El quiz envia { token, puntaje, tiempo }.
# Reenviamos la nota a CREA via LTI Outcomes (XML firmado con OAuth).
# ------------------------------------------------------------------

@app.route("/lti/submit", methods=["POST"])
def lti_submit():
    data = request.get_json()
    token = data.get("token")
    puntaje = data.get("puntaje", 0)
    tiempo = data.get("tiempo", 0)

    log.info(f"Submit: token={token[:8]}... puntaje={puntaje} tiempo={tiempo}s")

    datos = sesiones.get(token)
    if not datos:
        return jsonify({"ok": False, "error": "Sesion no encontrada"}), 404

    # Convertir puntaje a nota 0-1 (proporcion)
    # Asumiendo puntaje maximo del quiz = 250 pts (5x100 + 1x150)
    nota_normalizada = min(puntaje / 250.0, 1.0)

    datos["nota"] = nota_normalizada
    datos["tiempo"] = tiempo
    sesiones[token] = datos

    outcome_url = datos["outcome_url"]
    sourcedid = datos["sourcedid"]

    if not outcome_url or not sourcedid:
        log.warning(f"No hay outcome URL — calificacion NO habilitada para este lanzamiento")
        return jsonify({
            "ok": True,
            "nota": nota_normalizada,
            "advertencia": "Calificacion no habilitada en CREA para esta herramienta"
        })

    # Enviar nota a CREA
    try:
        tool_provider = ToolProvider(
            consumer_key=datos["consumer_key"],
            consumer_secret=datos["consumer_secret"],
            params={
                "lis_outcome_service_url": outcome_url,
                "lis_result_sourcedid": sourcedid,
            },
        )
        resultado = tool_provider.post_replace_result(nota_normalizada)
    except Exception as err:
        log.error(f"Error al enviar nota a CREA: {err}")
        return jsonify({"ok": False, "error": str(err)}), 500

    if resultado:
        log.info(f"Nota {nota_normalizada} enviada exitosamente a CREA")
        return jsonify({"ok": True, "nota": nota_normalizada, "enviado_a_crea": True})
    else:
        log.error(f"CREA rechazo la nota: {tool_provider.outcome_error or 'error desconocido'}")
        return jsonify({"ok": False, "error": tool_provider.outcome_error or "CREA rechazo la nota"}), 400


# ------------------------------------------------------------------
# POST /register
# Registra un nuevo par consumer_key/consumer_secret en el backend.
# Protegido con ADMIN_KEY para que solo docentes autorizados registren.
# ------------------------------------------------------------------

@app.route("/register", methods=["POST"])
def register_consumer():
    data = request.get_json()
    if not data:
        return jsonify({"ok": False, "error": "Cuerpo JSON requerido"}), 400

    admin = data.get("admin_key", "")
    if admin != ADMIN_KEY:
        log.warning("Intento de registro con admin_key incorrecta")
        return jsonify({"ok": False, "error": "Clave de administrador incorrecta"}), 403

    nueva_key = data.get("consumer_key", "").strip()
    nuevo_secret = data.get("consumer_secret", "").strip()

    if not nueva_key or not nuevo_secret:
        return jsonify({"ok": False, "error": "consumer_key y consumer_secret son requeridos"}), 400

    if nueva_key in _consumers:
        log.info(f"Consumer key ya registrado: {nueva_key} — actualizando secret")
    else:
        log.info(f"Nuevo consumer key registrado: {nueva_key}")

    _consumers[nueva_key] = nuevo_secret
    _save_consumers()

    return jsonify({
        "ok": True,
        "mensaje": "Claves registradas correctamente",
        "consumer_key": nueva_key,
        "total_consumers": len(_consumers),
    })


# ------------------------------------------------------------------
# GET / — Info basica
# ------------------------------------------------------------------

@app.route("/")
def index():
    return f"""
    <h3>Backend LTI — Multi-docente</h3>
    <p>URL de lanzamiento: <code>{BASE_URL}/lti/launch</code></p>
    <p>Endpoint de registro: <code>{BASE_URL}/register</code></p>
    <p>Consumidores registrados: <strong>{len(_consumers)}</strong></p>
    <p>Sesiones activas: {len(sesiones)}</p>
    """


# ------------------------------------------------------------------
# Inicio
# ------------------------------------------------------------------

if __name__ == "__main__":
    import os
    port = int(os.environ.get("PORT", 5000))
    log.info(f"Iniciando servidor en puerto {port}")
    app.run(host="0.0.0.0", port=port, debug=True)
