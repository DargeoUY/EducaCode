"""
LTI 1.1 Backend para CREA (Schoology) — EducaCode
Recibe lanzamientos LTI, valida OAuth 1.0, guarda sesion,
y reenvia notas a CREA via LTI Outcomes (XML + OAuth).
"""

import uuid
import logging
import json
import os
import hashlib
import urllib.parse
from datetime import datetime, timezone
from flask import Flask, request, redirect, jsonify, render_template_string
from flask_cors import CORS
from oauthlib.oauth1 import SignatureOnlyEndpoint, Client
from oauthlib.common import generate_nonce, generate_timestamp
import pymysql

# --- Config ---
BASE_URL = os.environ.get("BASE_URL", "http://localhost")
ADMIN_KEY = os.environ.get("ADMIN_KEY", "admin_key_lti_2026")
DB_HOST = os.environ.get("DB_HOST", "mariadb")
DB_USER = os.environ.get("DB_USER", "app_user")
DB_PASS = os.environ.get("DB_PASS", "app_pass_2026")
DB_NAME = os.environ.get("DB_NAME", "materiales1")
LTI_CONSUMER_KEY = os.environ.get("LTI_CONSUMER_KEY", "crea-key")
LTI_CONSUMER_SECRET = os.environ.get("LTI_CONSUMER_SECRET", "crea-secret")

app = Flask(__name__)
CORS(app)
app.secret_key = str(uuid.uuid4())

logging.basicConfig(level=logging.INFO)
log = logging.getLogger(__name__)

sesiones = {}

# --- DB ---
def get_db():
    return pymysql.connect(
        host=DB_HOST, user=DB_USER, password=DB_PASS,
        database=DB_NAME, charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor
    )

# --- OAuth 1.0 helpers ---
def validate_oauth_signature():
    """Valida la firma OAuth 1.0 del request entrante de CREA."""
    try:
        uri = request.base_url
        http_method = request.method
        headers = dict(request.headers)
        body = request.form.to_dict() if request.form else {}

        params = {}
        for k, v in request.args.items():
            params[k] = v

        client = Client(
            LTI_CONSUMER_KEY,
            client_secret=LTI_CONSUMER_SECRET,
            signature_method="HMAC-SHA1",
        )

        full_params = dict(body)
        full_params.update(params)
        full_params.update({
            "oauth_consumer_key": LTI_CONSUMER_KEY,
            "oauth_nonce": request.args.get("oauth_nonce", request.form.get("oauth_nonce", "")),
            "oauth_signature_method": "HMAC-SHA1",
            "oauth_timestamp": request.args.get("oauth_timestamp", request.form.get("oauth_timestamp", "")),
            "oauth_version": "1.0",
        })

        signature = request.args.get("oauth_signature", request.form.get("oauth_signature", ""))
        if not signature:
            log.warning("No OAuth signature in request")
            return False

        valid = client.verify_request(uri, http_method, body, headers, {})
        return valid
    except Exception as e:
        log.error(f"OAuth validation error: {e}")
        return False

def sign_outcome_request(outcome_url, sourcedid, score, consumer_key, consumer_secret):
    """Envia nota a CREA via LTI Outcomes (XML firmado con OAuth 1.0)."""
    message_id = str(uuid.uuid4())
    body_xml = f"""<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeRequest xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
  <imsx_POXHeader>
    <imsx_POXRequestHeaderInfo>
      <imsx_version>V1.0</imsx_version>
      <imsx_messageIdentifier>{message_id}</imsx_messageIdentifier>
    </imsx_POXRequestHeaderInfo>
  </imsx_POXHeader>
  <imsx_POXBody>
    <replaceResultRequest>
      <resultRecord>
        <sourcedGUID>
          <sourcedId>{sourcedid}</sourcedId>
        </sourcedGUID>
        <result>
          <resultScore>
            <language>en</language>
            <textString>{score}</textString>
          </resultScore>
        </result>
      </resultRecord>
    </replaceResultRequest>
  </imsx_POXBody>
</imsx_POXEnvelopeRequest>"""

    client = Client(consumer_key, client_secret=consumer_secret)
    import requests as req
    headers_oauth = client.get_oauth_header(
        client.client_key, client.client_secret, {
            "oauth_nonce": generate_nonce(),
            "oauth_timestamp": generate_timestamp(),
            "oauth_version": "1.0",
            "oauth_signature_method": "HMAC-SHA1",
        }
    )
    try:
        resp = req.post(
            outcome_url,
            data=body_xml,
            headers={
                "Content-Type": "application/xml",
                "Authorization": headers_oauth,
            },
            timeout=15,
        )
        return resp.status_code == 200, resp.text
    except Exception as e:
        log.error(f"Outcome request failed: {e}")
        return False, str(e)


# --- Routes ---

@app.route("/lti/launch", methods=["POST"])
def lti_launch():
    log.info("LTI Launch desde CREA")
    if not validate_oauth_signature():
        log.warning("Firma OAuth invalida")
        return "LTI Launch invalido (firma OAuth)", 403

    nombre = request.form.get("lis_person_name_full", "Alumno")
    user_id = request.form.get("user_id", "")
    outcome_url = request.form.get("lis_outcome_service_url", "")
    sourcedid = request.form.get("lis_result_sourcedid", "")
    nivel = request.form.get("custom_nivel", "1")
    eval_id = request.form.get("custom_evaluacion_id", "0")

    log.info(f"Alumno: {nombre} | user_id={user_id} | eval_id={eval_id} | outcome={'SI' if outcome_url else 'NO'}")

    token = str(uuid.uuid4())
    sesiones[token] = {
        "nombre": nombre,
        "user_id": user_id,
        "outcome_url": outcome_url,
        "sourcedid": sourcedid,
        "evaluacion_id": eval_id,
        "consumer_key": LTI_CONSUMER_KEY,
        "consumer_secret": LTI_CONSUMER_SECRET,
        "nivel": nivel,
    }

    redirect_url = f"{BASE_URL}/lti/quiz/{token}?nombre={nombre}&eval_id={eval_id}"
    return redirect(redirect_url)


@app.route("/lti/quiz/<token>")
def quiz(token):
    datos = sesiones.get(token)
    if not datos:
        return "Sesion expirada. Volve a lanzar desde CREA.", 404

    eval_id = datos.get("evaluacion_id", "0")
    nombre = datos.get("nombre", "Alumno")

    return render_template_string("""<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Evaluacion LTI — EducaCode</title>
<style>
:root{--bg:#0a0e17;--text:#f1f5f9;--text2:#94a3b8;--prim:#6366f1;--ok:#10b981}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;padding:20px}
.card{background:rgba(15,23,42,.9);border:1px solid rgba(99,102,241,.15);border-radius:16px;padding:32px;max-width:480px;text-align:center}
h1{font-size:1.3rem;color:var(--prim);margin-bottom:8px}
p{color:var(--text2);margin-bottom:16px}
.btn{display:inline-block;padding:12px 28px;background:var(--prim);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none;margin-top:10px}
.btn:hover{opacity:.9}
iframe{width:100%;height:100vh;border:none}
</style></head>
<body>
<div class="card">
    <h1>📝 Evaluacion LTI</h1>
    <p>Hola <strong>{{ nombre }}</strong></p>
    <p style="font-size:.85rem">Evaluacion ID: {{ eval_id }}</p>
    <a href="/estudiante/evaluacion.php?id={{ eval_id }}&lti_token={{ token }}" class="btn">Comenzar evaluacion</a>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var url = new URLSearchParams(window.location.search);
    var evalId = url.get('eval_id') || '{{ eval_id }}';
    var token = '{{ token }}';
    if (evalId && evalId !== '0') {
        window.location.href = '/estudiante/evaluacion.php?id=' + evalId + '&lti_token=' + token;
    }
});
</script>
</body>
</html>""", token=token, nombre=nombre, eval_id=eval_id)


@app.route("/lti/submit", methods=["POST"])
def lti_submit():
    data = request.get_json()
    token = data.get("token")
    puntaje = data.get("puntaje", 0)
    notaf = data.get("nota", 0)
    max_puntaje = data.get("max_puntaje", 100)

    log.info(f"LTI Submit: token={token[:8] if token else 'N/A'}... nota={notaf}")

    if not token or token not in sesiones:
        log.warning("Token no encontrado")
        return jsonify({"ok": False, "error": "Sesion LTI no encontrada"}), 404

    datos = sesiones[token]
    outcome_url = datos.get("outcome_url")
    sourcedid = datos.get("sourcedid")

    if not outcome_url or not sourcedid:
        log.warning("No outcome URL — calificacion NO habilitada")
        return jsonify({"ok": True, "nota": notaf, "advertencia": "Sin outcome URL (lanzamiento sin calificacion)"})

    nota_normalizada = min(notaf / 10.0, 1.0) if max_puntaje and max_puntaje > 0 else min(notaf / 10.0, 1.0)

    ok, resp = sign_outcome_request(
        outcome_url, sourcedid, str(round(nota_normalizada, 4)),
        datos.get("consumer_key", LTI_CONSUMER_KEY),
        datos.get("consumer_secret", LTI_CONSUMER_SECRET),
    )

    if ok:
        log.info(f"Nota {nota_normalizada} enviada exitosamente a CREA")
        return jsonify({"ok": True, "nota": notaf, "enviado_a_crea": True})
    else:
        log.error(f"CREA rechazo la nota: {resp}")
        return jsonify({"ok": False, "error": resp[:300]}), 400


@app.route("/lti/health")
def health():
    return jsonify({"ok": True, "sesiones_activas": len(sesiones)})


@app.route("/lti/config", methods=["POST"])
def config():
    data = request.get_json()
    if not data or data.get("admin_key") != ADMIN_KEY:
        return jsonify({"ok": False, "error": "No autorizado"}), 403
    return jsonify({
        "ok": True,
        "consumer_key": LTI_CONSUMER_KEY,
        "consumer_secret": LTI_CONSUMER_SECRET,
        "launch_url": f"{BASE_URL}/lti/launch",
    })


@app.route("/lti/test-launch", methods=["GET"])
def test_launch():
    token = str(uuid.uuid4())
    sesiones[token] = {
        "nombre": "Test Student",
        "user_id": "test123",
        "outcome_url": "",
        "sourcedid": "",
        "evaluacion_id": "1",
        "consumer_key": LTI_CONSUMER_KEY,
        "consumer_secret": LTI_CONSUMER_SECRET,
    }
    return redirect(f"/lti/quiz/{token}")


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=True)
