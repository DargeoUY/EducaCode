# Conectar quizzes de Python con CREA via LTI

Este proyecto envia las notas de los quizzes automaticamente a **CREA (Schoology)** usando **LTI 1.1**.

---

## Para el docente — 3 pasos (sin instalar nada)

```
  1. Abri setup.html          →  Genera claves + descarga config.json
  2. Copia los campos a CREA   →  Desde la misma pagina
  3. Subi todo a Render        →  Gratis, 1 clic
```

---

## Paso 1 — Abrir setup.html

Hace doble clic en `setup.html`. Se abre en tu navegador.

1. Ingresa la URL de tu app en Render (si no la tenes, usa la que viene por defecto)
2. Clic en **Generar claves y configuracion**
3. Clic en **Descargar config.json**

> Las claves se generan aleatoriamente en tu navegador. No se envian a ningun servidor.

---

## Paso 2 — Configurar CREA

En la misma pagina `setup.html` vas a ver la tabla con **todos los campos**. Usa los botones **Copiar** y pegalos en CREA:

1. Entra a tu curso en **CREA**
2. **Materiales** → **Agregar materiales** → **Herramienta externa**
3. Completa los campos copiando desde la pagina
4. **Habilitar calificacion** debe quedar ✅ Activado
5. Guarda

Para cada nivel (2, 3, 4, 5) crea otra herramienta igual, cambiando solo **Titulo** y **Parametros Personalizados** (`nivel=2`, etc.).

---

## Paso 3 — Deploy en Render.com

1. Crea cuenta gratuita en **render.com**
2. Subi la carpeta `lti-test` a un repositorio GitHub (inclui el `config.json` que descargaste)
3. En Render: **New +** → **Web Service** → conecta tu repo
4. Configura:

| Campo | Valor |
|---|---|
| Runtime | Python 3 |
| Build Command | `pip install -r requirements.txt` |
| Start Command | `gunicorn app:app` |
| Plan | Free |

5. Hace clic en **Create Web Service**

Render te da una URL (ej: `https://miapp.onrender.com`). Volve a `setup.html`, actualiza la URL, descarga `config.json` de nuevo y actualiza el repo.

---

## Archivos del proyecto

| Archivo | Para que sirve |
|---|---|
| **setup.html** | Configurador visual — abrirlo en el navegador (NO necesita Python) |
| `app.py` | Backend Flask que se deploya en Render |
| `config.json` | Claves de configuracion (descargado desde setup.html) |
| `test-quiz.html` | Quiz de prueba con 1 ejercicio en Pyodide |
| `requirements.txt` | Dependencias (flask, lti, gunicorn) |
| `estilos.css` | Estilos del curso |
| `servicio.py` | Alternativa: configurador por terminal (requiere Python) |

---

## Probar localmente (opcional, requiere Python)

```bash
pip install -r requirements.txt
python app.py
# Abri http://localhost:5000
```

---

## Solucion de problemas

| Problema | Solucion |
|---|---|
| "LTI Launch invalido" | Verifica que Clave y Secreto coincidan en CREA y `config.json` |
| Nota no aparece en CREA | Asegurate que **Habilitar calificacion** este activado |
| Render lento primer uso | Free tier se duerme tras 15 min — espera 30-60s |
| El quiz no carga | Verifica que la URL en CREA coincida con la de Render |

---

## Adaptar los 4 niveles del curso

Cada HTML de nivel (`Python - LVL 1.html`, etc.) necesita 2 cambios minimos al final del script:

```javascript
// Leer el token de la URL
var TOKEN = new URLSearchParams(window.location.search).get("token");

// Al finalizar, enviar la nota al backend
fetch(window.location.origin + "/lti/submit", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ token: TOKEN, puntaje: puntajeTotal, tiempo: tiempoSegundos })
});
```
