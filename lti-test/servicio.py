"""
servicio.py — Configurador LTI para CREA
=========================================
Este script genera las claves y crea una guia visual (setup-crea.html)
para que cualquier docente pueda configurar la herramienta externa en CREA
sin necesidad de tocar codigo.

Uso:
    python servicio.py

El script te va a pedir:
    1. La URL de tu app en Render (ej: https://mi-app.onrender.com)

Luego genera:
    - config.json        (claves para el backend)
    - setup-crea.html    (guia visual con todos los campos de CREA)

Tambien actualiza app.py para que lea desde config.json.
"""

import json
import secrets
import string
import os
import sys
from datetime import datetime

# Forzar UTF-8 en la consola (Windows)
if sys.platform == "win32":
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

# ============================================================
# 1. FUNCIONES AUXILIARES
# ============================================================

def generar_clave(longitud=16):
    """Genera una clave aleatoria segura, facil de leer (sin simbolos confusos)."""
    seguro = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789"
    return "".join(secrets.choice(seguro) for _ in range(longitud))

def generar_secreto(longitud=32):
    """Genera un secreto compartido aleatorio mas largo."""
    seguro = string.ascii_letters + string.digits + "!@#$%&*_-"
    return "".join(secrets.choice(seguro) for _ in range(longitud))

def pedir_url():
    """Pide la URL de Render al usuario."""
    print("\n" + "=" * 60)
    print("  🔧 CONFIGURADOR LTI — Curso Python para CREA")
    print("=" * 60)
    print()
    print("Este asistente genera las claves y la guia de configuracion")
    print("para conectar tus quizzes de Python con CREA via LTI.")
    print()
    url = input("URL de tu app en Render (ej: https://miapp.onrender.com): ").strip()
    while not url.startswith("http"):
        print("❌ La URL debe empezar con http:// o https://")
        url = input("URL de tu app en Render: ").strip()
    url = url.rstrip("/")
    return url

def mostrar_resumen(config, url):
    """Muestra un resumen en consola."""
    print()
    print("=" * 60)
    print("  ✅ CONFIGURACION GENERADA")
    print("=" * 60)
    print()
    print(f"  URL de lanzamiento:  {url}/lti/launch")
    print(f"  Clave consumidor:    {config['LTI_CONSUMER_KEY']}")
    print(f"  Secreto compartido:  {config['LTI_CONSUMER_SECRET']}")
    print()
    print("=" * 60)
    print("  📋 PROXIMOS PASOS")
    print("=" * 60)
    print()
    print("  1. Abri el archivo setup-crea.html en tu navegador")
    print("  2. Copia cada campo en la herramienta externa de CREA")
    print("  3. Hace deploy de app.py en Render")
    print()
    print("  🟢 ¡Listo! Los quizzes van a enviar notas a CREA.")
    print()

def generar_html(config, url, niveles):
    """Genera setup-crea.html con la guia visual."""
    
    filas_niveles = ""
    for n in niveles:
        filas_niveles += f"""
                    <tr>
                        <td><strong>Python Nivel {n}</strong></td>
                        <td><code>nivel={n}</code></td>
                    </tr>"""

    html = f"""<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configuracion LTI — CREA</title>
<style>
*{{margin:0;padding:0;box-sizing:border-box}}
body{{font-family:'Segoe UI',Tahoma,sans-serif;background:#0f172a;color:#f1f5f9;max-width:800px;margin:0 auto;padding:30px 20px 60px}}
h1{{font-size:1.7rem;margin-bottom:6px;background:linear-gradient(135deg,#6366f1,#06b6d4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}}
.sub{{color:#94a3b8;font-size:0.95rem;margin-bottom:30px}}
.card{{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:22px;margin-bottom:20px;box-shadow:0 4px 20px rgba(0,0,0,.2)}}
.card h2{{font-size:1.15rem;color:#818cf8;margin-bottom:14px;display:flex;align-items:center;gap:8px}}
.tabla{{width:100%;border-collapse:collapse;font-size:0.9rem}}
.tabla td{{padding:10px 12px;border-bottom:1px solid #334155}}
.tabla td:first-child{{font-weight:600;color:#e2e8f0;white-space:nowrap}}
.tabla td:last-child{{font-family:Consolas,Monaco,monospace;color:#34d399;word-break:break-all}}
.campo{{display:flex;align-items:center;gap:8px}}
.copiar{{background:#334155;color:#94a3b8;border:1px solid #475569;padding:4px 10px;border-radius:5px;cursor:pointer;font-size:0.75rem;font-weight:600;transition:all .2s}}
.copiar:hover{{background:#6366f1;color:white;border-color:#6366f1}}
.copiado{{background:#10b981!important;color:white!important;border-color:#10b981!important}}
.pasos{{list-style:none;counter-reset:paso}}
.pasos li{{counter-increment:paso;margin-bottom:12px;display:flex;gap:10px;align-items:flex-start;font-size:0.92rem;line-height:1.5}}
.pasos li::before{{content:counter(paso);background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;font-weight:700;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.85rem}}
code{{background:rgba(99,102,241,.2);color:#c084fc;padding:2px 6px;border-radius:4px;font-family:Consolas,Monaco,monospace;font-size:0.85em}}
.alerta{{background:rgba(245,158,11,.1);border-left:4px solid #f59e0b;padding:14px 16px;border-radius:0 8px 8px 0;font-size:0.88rem;line-height:1.5;margin-top:16px}}
.alerta strong{{color:#f59e0b}}
.generado{{color:#64748b;font-size:0.75rem;text-align:right;margin-top:30px}}
img,svg{{max-width:100%}}
.resaltar{{color:#f59e0b;font-weight:600}}
</style>
</head>
<body>

<h1>🔗 Configuracion LTI para CREA</h1>
<p class="sub">Guia visual — Copia estos valores en la herramienta externa de CREA</p>

<!-- TARJETA 1: Datos principales -->
<div class="card">
    <h2>📋 Campos de la herramienta externa en CREA</h2>
    <table class="tabla">
        <tr>
            <td>Titulo</td>
            <td>
                <div class="campo">
                    <span>Python Nivel 1</span>
                    <button class="copiar" onclick="copiar(this,'Python Nivel 1')">Copiar</button>
                </div>
            </td>
        </tr>
        <tr>
            <td>URL</td>
            <td>
                <div class="campo">
                    <span>{url}/lti/launch</span>
                    <button class="copiar" onclick="copiar(this,'{url}/lti/launch')">Copiar</button>
                </div>
            </td>
        </tr>
        <tr>
            <td>Abrir opciones</td>
            <td>Nueva ventana (o iframe)</td>
        </tr>
        <tr>
            <td>Clave Consumidor</td>
            <td>
                <div class="campo">
                    <span>{config['LTI_CONSUMER_KEY']}</span>
                    <button class="copiar" onclick="copiar(this,'{config['LTI_CONSUMER_KEY']}')">Copiar</button>
                </div>
            </td>
        </tr>
        <tr>
            <td>Secreto Compartido</td>
            <td>
                <div class="campo">
                    <span>{config['LTI_CONSUMER_SECRET']}</span>
                    <button class="copiar" onclick="copiar(this,'{config['LTI_CONSUMER_SECRET']}')">Copiar</button>
                </div>
            </td>
        </tr>
        <tr>
            <td>Parametros Personalizados</td>
            <td>
                <div class="campo">
                    <span>nivel=1</span>
                    <button class="copiar" onclick="copiar(this,'nivel=1')">Copiar</button>
                </div>
            </td>
        </tr>
        <tr>
            <td>Habilitar calificacion</td>
            <td><span class="resaltar">✅ SI — Activado</span></td>
        </tr>
    </table>
</div>

<!-- TARJETA 2: Como configurar CREA -->
<div class="card">
    <h2>🖱️ Pasos en CREA</h2>
    <ol class="pasos">
        <li>Entra a tu <strong>curso en CREA</strong></li>
        <li>Anda a <strong>Materiales</strong> o <strong>Agregar materiales</strong></li>
        <li>Selecciona <strong>Herramienta externa</strong></li>
        <li>Completa los <strong>7 campos de arriba</strong> con los botones Copiar</li>
        <li><strong>Guarda</strong> la herramienta</li>
        <li>El quiz aparece como un enlace en los materiales del curso</li>
    </ol>
</div>

<!-- TARJETA 3: Niveles adicionales -->
<div class="card">
    <h2>📚 Configurar mas niveles</h2>
    <p style="color:#94a3b8;font-size:0.9rem;margin-bottom:12px;">Para cada nivel del curso, crea una herramienta externa nueva cambiando solo el <strong>Titulo</strong> y los <strong>Parametros Personalizados</strong>:</p>
    <table class="tabla">
        <tr>
            <td><strong>Python Nivel 1</strong></td>
            <td><code>nivel=1</code></td>
        </tr>
        <tr>
            <td><strong>Python Nivel 2</strong></td>
            <td><code>nivel=2</code></td>
        </tr>
        <tr>
            <td><strong>Python Nivel 3</strong></td>
            <td><code>nivel=3</code></td>
        </tr>
        <tr>
            <td><strong>Python Nivel 4</strong></td>
            <td><code>nivel=4</code></td>
        </tr>
        <tr>
            <td><strong>Python Nivel 5</strong></td>
            <td><code>nivel=5</code></td>
        </tr>
    </table>
    <div class="alerta">
        <strong>⚠️ Importante:</strong> La URL, Clave Consumidor y Secreto Compartido son <strong>los mismos</strong> para todos los niveles. Solo cambia Titulo y Parametros Personalizados.
    </div>
</div>

<!-- TARJETA 4: Verificacion -->
<div class="card">
    <h2>✅ Verificar que funciona</h2>
    <ol class="pasos">
        <li>Hace clic en la herramienta desde CREA</li>
        <li>Debe abrirse el quiz con el nombre del alumno</li>
        <li>Completa el ejercicio y hace clic en <strong>Enviar nota a CREA</strong></li>
        <li>En CREA, anda al <strong>Gradebook</strong> — la nota debe aparecer</li>
    </ol>
</div>

<p class="generado">Generado el {datetime.now().strftime('%d/%m/%Y a las %H:%M')} — Guarda esta pagina como referencia</p>

<script>
function copiar(btn, texto) {{
    navigator.clipboard.writeText(texto).then(function() {{
        btn.textContent = 'Copiado!';
        btn.classList.add('copiado');
        setTimeout(function() {{
            btn.textContent = 'Copiar';
            btn.classList.remove('copiado');
        }}, 2000);
    }});
}}
</script>
</body>
</html>"""
    return html


# ============================================================
# 2. FUNCION PRINCIPAL
# ============================================================

def main():
    # Pedir URL de Render
    url = pedir_url()
    
    # Generar claves
    config = {
        "LTI_CONSUMER_KEY": generar_clave(16),
        "LTI_CONSUMER_SECRET": generar_secreto(32),
        "BASE_URL": url,
    }
    
    # Guardar config.json
    with open("config.json", "w", encoding="utf-8") as f:
        json.dump(config, f, indent=2, ensure_ascii=False)
    print("  ✅ config.json generado")
    
    # Generar HTML de configuracion para CREA
    niveles = [1, 2, 3, 4, 5]
    html = generar_html(config, url, niveles)
    with open("setup-crea.html", "w", encoding="utf-8") as f:
        f.write(html)
    print("  ✅ setup-crea.html generado")
    
    # Actualizar app.py si existe
    if os.path.exists("app.py"):
        actualizar_app_py(config)
        print("  ✅ app.py actualizado con las nuevas claves")
    
    # Mostrar resumen
    mostrar_resumen(config, url)


def actualizar_app_py(config):
    """Reemplaza las claves hardcodeadas en app.py por lectura desde config.json."""
    contenido_actual = ""
    with open("app.py", "r", encoding="utf-8") as f:
        contenido_actual = f.read()
    
    # Verificar si ya usa config.json
    if "config.json" in contenido_actual:
        return  # Ya esta configurado
    
    # Reemplazar el bloque de configuracion
    viejo = """# ============================================================
# CONFIGURACION — Cambia estos valores para tu entorno
# ============================================================

# La URL publica donde corre este backend (Render te da una)
BASE_URL = "https://tu-app.onrender.com"

# Deben coincidir EXACTAMENTE con lo que configuraste en CREA
LTI_CONSUMER_KEY = "python-curso"
LTI_CONSUMER_SECRET = "una-clave-segura-2024"

# ============================================================"""
    
    nuevo = f"""# ============================================================
# CONFIGURACION — Se carga desde config.json
# (generado automaticamente por servicio.py)
# ============================================================

import json as _json
import os as _os

with open(_os.path.join(_os.path.dirname(__file__), "config.json"), "r") as _f:
    _cfg = _json.load(_f)

BASE_URL = _cfg["BASE_URL"]
LTI_CONSUMER_KEY = _cfg["LTI_CONSUMER_KEY"]
LTI_CONSUMER_SECRET = _cfg["LTI_CONSUMER_SECRET"]

# ============================================================"""
    
    if viejo in contenido_actual:
        contenido_actual = contenido_actual.replace(viejo, nuevo)
        with open("app.py", "w", encoding="utf-8") as f:
            f.write(contenido_actual)


# ============================================================
# 3. EJECUCION
# ============================================================

if __name__ == "__main__":
    main()
