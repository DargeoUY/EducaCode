// ============================================
// GOOGLE APPS SCRIPT - Validador Web Nivel 5
// ============================================
// INSTRUCCIONES DE CONFIGURACION:
//
// 1. Abrí Google Sheets y creá una nueva hoja de cálculo
// 2. Creá DOS hojas (pestañas abajo):
//    - "Alumnos"  → columnas: Cédula | Nombre | Apellido | Grupo
//      (cargá acá los alumnos habilitados, uno por fila)
//    - "Resultados" → columnas: Cédula | Nombre | Apellido | Grupo | Puntuación | Fecha y Hora | Intento N° | Nota (1-10) | Estado | Tiempo (seg)
//      (esta hoja se llena sola, solo creá los encabezados)
// 3. Andá a Extensiones > Apps Script
// 4. Pegá este código completo
// 5. Hacé clic en "Implementar" > "Nueva implementación" > "Aplicación web"
// 6. Ejecutar como: "Yo", Acceso: "Cualquiera"
// 7. Copiá la URL y pegala en la variable GOOGLE_SCRIPT_URL del HTML Nivel 5
// ============================================

var TIEMPO_MAXIMO = 1800; // 30 minutos en segundos
var ENCABEZADOS = ["Cédula", "Nombre", "Apellido", "Grupo", "Puntuación", "Fecha y Hora", "Intento N°", "Nota (1-10)", "Estado", "Tiempo (seg)"];

function doGet(e) {
  if (e && e.parameter && e.parameter.accion === "validar") {
    return validarAlumno(e.parameter.cedula);
  }
  var grupo = (e && e.parameter && e.parameter.grupo) ? String(e.parameter.grupo).trim() : "";
  return obtenerRanking(grupo);
}

function doPost(e) {
  var data = JSON.parse(e.postData.contents);
  var hoja = SpreadsheetApp.getActiveSpreadsheet().getSheetByName("Resultados");

  if (!hoja) {
    hoja = SpreadsheetApp.getActiveSpreadsheet().insertSheet("Resultados");
    hoja.appendRow(ENCABEZADOS);
  }

  // Asegurar encabezados correctos
  var headers = hoja.getDataRange().getValues()[0];
  if (!headers || headers.length < ENCABEZADOS.length) {
    hoja.getRange(1, 1, 1, ENCABEZADOS.length).setValues([ENCABEZADOS]);
  }

  if (data.accion === "iniciar") {
    return registrarInicio(data, hoja);
  }
  return registrarFinal(data, hoja);
}

function registrarInicio(data, hoja) {
  var resultados = hoja.getDataRange().getValues();
  var intentoNumero = 1;
  for (var i = 1; i < resultados.length; i++) {
    if (String(resultados[i][0]) === String(data.cedula) &&
        String(resultados[i][8]) === "Finalizado") {
      intentoNumero++;
    }
  }

  hoja.appendRow([
    String(data.cedula),
    data.nombre, data.apellido, data.grupo,
    "—", // puntuacion
    data.fechaHora,
    intentoNumero,
    "",  // nota
    "En curso",
    ""   // tiempo placeholder
  ]);

  return jsonResponse({ resultado: "ok", intento: intentoNumero });
}

function registrarFinal(data, hoja) {
  var rows = hoja.getDataRange().getValues();
  var cedula = String(data.cedula);

  // Buscar fila "En curso" más reciente para esta cédula
  for (var i = rows.length - 1; i >= 1; i--) {
    if (String(rows[i][0]) === cedula && String(rows[i][8]) === "En curso") {
      var fila = i + 1;
      hoja.getRange(fila, 5).setValue(data.puntuacion);
      hoja.getRange(fila, 6).setValue(data.fechaHora);
      hoja.getRange(fila, 8).setValue(data.nota || "");
      hoja.getRange(fila, 9).setValue("Finalizado");
      hoja.getRange(fila, 10).setValue(data.tiempoUsado || "");
      return jsonResponse({ resultado: "ok", actualizado: true });
    }
  }

  // Fallback: si no hay fila "En curso", crear una nueva
  var intentoNumero = 1;
  for (var j = 1; j < rows.length; j++) {
    if (String(rows[j][0]) === cedula && String(rows[j][8]) === "Finalizado") {
      intentoNumero++;
    }
  }

  hoja.appendRow([
    cedula,
    data.nombre, data.apellido, data.grupo,
    data.puntuacion, data.fechaHora,
    intentoNumero,
    data.nota || "",
    "Finalizado",
    data.tiempoUsado || ""
  ]);

  return jsonResponse({ resultado: "ok", intento: intentoNumero });
}

function validarAlumno(cedula) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var hojaAlumnos = ss.getSheetByName("Alumnos");

  if (!hojaAlumnos) {
    return jsonResponse({ autorizado: false, motivo: "Falta la hoja 'Alumnos' en el Sheets." });
  }

  var alumnos = hojaAlumnos.getDataRange().getValues();
  if (alumnos.length < 2) {
    return jsonResponse({ autorizado: false, motivo: "La hoja 'Alumnos' está vacía." });
  }

  var alumnoEncontrado = null;
  for (var i = 1; i < alumnos.length; i++) {
    if (String(alumnos[i][0]).trim() === String(cedula).trim()) {
      alumnoEncontrado = {
        cedula: String(alumnos[i][0]).trim(),
        nombre: alumnos[i][1] || "",
        apellido: alumnos[i][2] || "",
        grupo: alumnos[i][3] || ""
      };
      break;
    }
  }

  if (!alumnoEncontrado) {
    return jsonResponse({ autorizado: false, motivo: "Cédula no encontrada en la lista de alumnos." });
  }

  var hojaResultados = ss.getSheetByName("Resultados");
  var intentos = 0;
  if (hojaResultados) {
    var resultados = hojaResultados.getDataRange().getValues();
    for (var j = 1; j < resultados.length; j++) {
      if (String(resultados[j][0]).trim() !== String(cedula).trim()) continue;
      var estado = String(resultados[j][8] || "").trim();
      var punt = resultados[j][4];
      // Cuenta: Finalizado, En curso, o fila vieja sin estado pero con puntuación válida
      if (estado === "Finalizado" || estado === "En curso" || (estado === "" && punt !== "—" && punt !== "" && !isNaN(Number(punt)))) {
        intentos++;
      }
    }
  }

  if (intentos >= 3) {
    return jsonResponse({ autorizado: false, motivo: "Ya realizaste el máximo de 3 intentos.", intentos: intentos });
  }

  return jsonResponse({
    autorizado: true,
    intentos: intentos,
    restantes: 3 - intentos,
    nombre: alumnoEncontrado.nombre,
    apellido: alumnoEncontrado.apellido,
    grupo: alumnoEncontrado.grupo
  });
}

function obtenerRanking(grupoFiltro) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var hoja = ss.getSheetByName("Resultados");

  if (!hoja) return jsonResponse([]);

  var data = hoja.getDataRange().getValues();
  if (data.length <= 1) return jsonResponse([]);

  var mejores = {};
  for (var i = 1; i < data.length; i++) {
    if (String(data[i][8] || "").trim() !== "Finalizado") continue;

    // Si hay filtro de grupo, saltar los que no coincidan
    if (grupoFiltro && String(data[i][3] || "").trim().toLowerCase() !== grupoFiltro.toLowerCase()) continue;

    var cedula = String(data[i][0]).trim();
    var puntuacion = Number(data[i][4]) || 0;
    var tiempoUsado = Number(data[i][9]) || TIEMPO_MAXIMO;
    var minutosAhorrados = Math.floor((TIEMPO_MAXIMO - tiempoUsado) / 60);
    var bonus = Math.max(0, minutosAhorrados * 1.5);
    var rankingScore = Math.round(puntuacion + bonus);

    if (!mejores[cedula] || rankingScore > mejores[cedula].rankingScore) {
      mejores[cedula] = {
        nombre: data[i][1],
        apellido: data[i][2],
        grupo: data[i][3],
        puntuacion: puntuacion,
        intento: data[i][6],
        nota: data[i][7] || "",
        tiempoUsado: tiempoUsado,
        bonus: bonus,
        rankingScore: rankingScore
      };
    }
  }

  var ranking = [];
  for (var key in mejores) {
    ranking.push(mejores[key]);
  }

  ranking.sort(function(a, b) { return b.rankingScore - a.rankingScore; });
  ranking = ranking.slice(0, 10);

  return jsonResponse(ranking);
}

function jsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
