<?php
include 'conexion.php';
session_start();

$sql_estado = "SELECT estado_prueba FROM configuracion WHERE id = 1";
$resultado_estado = mysqli_query($conexion, $sql_estado);
$fila_estado = mysqli_fetch_assoc($resultado_estado);

if ($fila_estado['estado_prueba'] == 'cerrada') {
    die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px;'>⛔ La evaluación está cerrada en este momento.</h2>");
}

if (isset($_POST['login'])) {
    $cedula = $_POST['cedula'];
    $sql_estudiante = "SELECT * FROM estudiantes WHERE cedula = '$cedula'";
    $resultado_estudiante = mysqli_query($conexion, $sql_estudiante);
    
    if (mysqli_num_rows($resultado_estudiante) > 0) {
        $_SESSION['cedula'] = $cedula;
    } else {
        $error = "Cédula no encontrada.";
    }
}

if (isset($_POST['entregar_examen'])) {
    $cedula = $_SESSION['cedula'];
    $puntaje = 0;
    
    $respuestas_correctas = array("p1" => "b", "p2" => "a", "p3" => "c", "p4" => "b", "p5" => "c", "p6" => "a", "p7" => "b");
    $puntos_preguntas = array("p1" => 1, "p2" => 1, "p3" => 1, "p4" => 1, "p5" => 1, "p6" => 1, "p7" => 4);

    foreach ($respuestas_correctas as $pregunta => $respuesta_correcta) {
        if (isset($_POST[$pregunta]) && $_POST[$pregunta] == $respuesta_correcta) {
            $puntaje = $puntaje + $puntos_preguntas[$pregunta];
        }
    }

    $sql_guardar = "INSERT INTO notas (cedula_estudiante, nivel_id, nota, tiempo_seg, intento) VALUES ('$cedula', 30, '$puntaje', 0, 1)";
    mysqli_query($conexion, $sql_guardar);
    
    session_destroy();
    die("<h2 style='text-align:center; font-family:sans-serif; color:#27ae60; margin-top:50px;'>✅ Evaluación entregada correctamente.<br><br>Tu calificación es: $puntaje / 10.<br><br>Ya podés cerrar esta ventana.</h2>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluación Final - Pseudocódigo</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; color: #333; }
        .contenedor { max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .pregunta { background: #f9f9f9; padding: 15px; border-left: 4px solid #3498db; margin-bottom: 20px; border-radius: 0 4px 4px 0; }
        .pregunta-desafio { border-left: 5px solid #e74c3c; background: #fdf2e9; }
        .codigo { background: #2c3e50; color: #ecf0f1; padding: 12px; font-family: 'Courier New', Courier, monospace; font-weight: bold; border-radius: 4px; margin: 10px 0; line-height: 1.5; }
        .puntos { display: inline-block; background: #3498db; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; margin-left: 10px; vertical-align: middle; }
        .puntos-desafio { background: #e74c3c; }
        button { background: #27ae60; color: white; padding: 12px 25px; border: none; font-size: 16px; cursor: pointer; border-radius: 5px; font-weight: bold; width: 100%; transition: 0.2s; }
        button:hover { background: #219653; }
        #pantalla-bloqueo { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(231, 76, 60, 0.98); color: white; z-index: 9999; text-align: center; padding-top: 150px; }
        label { display: block; margin: 8px 0; font-size: 1.05em; cursor: pointer; }
    </style>
</head>
<body>

<div class="contenedor">
    <?php if (!isset($_SESSION['cedula'])) { ?>
        <h2 style="text-align:center; color:#2c3e50;">Ingreso a la Evaluación</h2>
        <p style="text-align:center;">Por favor, ingresá tu cédula para comenzar el cuestionario del espacio técnico-tecnológico.</p>
        <?php if(isset($error)) { echo "<p style='color:red; text-align:center; font-weight:bold;'>$error</p>"; } ?>
        
        <form method="POST" style="text-align:center; margin-top: 30px;">
            <input type="text" name="cedula" placeholder="Ej: 12345678" required style="padding:10px; font-size:16px; border: 1px solid #ccc; border-radius: 4px; width: 250px;">
            <br><br>
            <button type="submit" name="login" style="width: 270px; background: #2c3e50;">Comenzar Prueba</button>
        </form>

    <?php } else { ?>
        <h1 style="color:#2c3e50; text-align: center; margin-top: 0;">📝 Evaluación Final de Programación</h1>
        <div style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #ffeeba;">
            <strong>⚠️ ATENCIÓN:</strong> Esta prueba cuenta con sistema anti-trampas. Si cambiás de pestaña, minimizás el navegador o abrís otro programa, la prueba se bloqueará y se enviará automáticamente con la nota que tengas hasta el momento.
        </div>

        <form method="POST" id="formulario-examen">
            
            <div class="pregunta">
                <p><strong>1. ¿Qué hace exactamente la instrucción <code>Leer</code>?</strong> <span class="puntos">1 pt</span></p>
                <label><input type="radio" name="p1" value="a" required> Muestra un mensaje en la pantalla para que el usuario lo lea.</label>
                <label><input type="radio" name="p1" value="b"> Pausa el programa y guarda en una variable lo que el usuario escribe.</label>
                <label><input type="radio" name="p1" value="c"> Borra todos los datos de la memoria del robot.</label>
            </div>

            <div class="pregunta">
                <p><strong>2. ¿Para qué usamos una Variable en nuestro código?</strong> <span class="puntos">1 pt</span></p>
                <label><input type="radio" name="p2" value="a" required> Para guardar datos (como si fuera una caja) y usarlos más adelante.</label>
                <label><input type="radio" name="p2" value="b"> Para repetir una instrucción muchas veces sin cansarnos.</label>
                <label><input type="radio" name="p2" value="c"> Para indicarle al programa dónde está el Inicio y el Fin.</label>
            </div>

            <div class="pregunta">
                <p><strong>3. Si queremos que el programa tome una decisión (elija entre dos caminos distintos según una condición), debemos usar la estructura:</strong> <span class="puntos">1 pt</span></p>
                <label><input type="radio" name="p3" value="a" required> Inicio / Fin</label>
                <label><input type="radio" name="p3" value="b"> Para / FinPara</label>
                <label><input type="radio" name="p3" value="c"> Si / Sino</label>
            </div>

            <div class="pregunta">
                <p><strong>4. En un bucle <code>Para</code>, ¿qué función cumple el parámetro <code>Con Paso</code>?</strong> <span class="puntos">1 pt</span></p>
                <label><input type="radio" name="p4" value="a" required> Frena el bucle si hay un error en la computadora.</label>
                <label><input type="radio" name="p4" value="b"> Le indica al contador de a cuántos números tiene que sumar o restar por vuelta.</label>
                <label><input type="radio" name="p4" value="c"> Es la condición de cierre que destruye la variable.</label>
            </div>

            <div class="pregunta">
                <p><strong>5. Seguimiento mental. Si corremos este código, ¿qué número queda guardado finalmente en la variable <code>vida</code>?</strong> <span class="puntos">1 pt</span></p>
                <div class="codigo">
                    Inicio<br>
                    &nbsp;&nbsp;vida = 10<br>
                    &nbsp;&nbsp;vida = vida - 3<br>
                    Fin
                </div>
                <label><input type="radio" name="p5" value="a" required> 3</label>
                <label><input type="radio" name="p5" value="b"> 13</label>
                <label><input type="radio" name="p5" value="c"> 7</label>
            </div>

            <div class="pregunta">
                <p><strong>6. Evaluando el condicional. Si un alumno sacó exactamente 60 puntos, ¿qué mensaje mostrará este programa?</strong> <span class="puntos">1 pt</span></p>
                <div class="codigo">
                    Inicio<br>
                    &nbsp;&nbsp;nota = 60<br>
                    &nbsp;&nbsp;Si nota >= 60 Entonces<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Escribir "Aprobado"<br>
                    &nbsp;&nbsp;Sino<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Escribir "Reprobado"<br>
                    &nbsp;&nbsp;FinSi<br>
                    Fin
                </div>
                <label><input type="radio" name="p6" value="a" required> Aprobado</label>
                <label><input type="radio" name="p6" value="b"> Reprobado</label>
                <label><input type="radio" name="p6" value="c"> No muestra nada porque le falta el signo de igual (==).</label>
            </div>

            <div class="pregunta pregunta-desafio">
                <p style="color: #c0392b;"><strong>7. 🔥 DESAFÍO FINAL: Seguimiento de Bucle Mientras</strong> <span class="puntos puntos-desafio">4 pts</span></p>
                <p>Hacé el camino paso a paso de este bloque de código. ¿Qué número exacto mostrará la pantalla cuando el programa llegue a la instrucción <code>Escribir</code>?</p>
                <div class="codigo">
                    Inicio<br>
                    &nbsp;&nbsp;energia = 0<br>
                    &nbsp;&nbsp;Mientras energia < 10 Hacer<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;energia = energia + 4<br>
                    &nbsp;&nbsp;FinMientras<br>
                    &nbsp;&nbsp;Escribir energia<br>
                    Fin
                </div>
                <label><input type="radio" name="p7" value="a" required> 10</label>
                <label><input type="radio" name="p7" value="b"> 12</label>
                <label><input type="radio" name="p7" value="c"> 8</label>
            </div>

            <button type="submit" name="entregar_examen" id="btn-entregar">Entregar Evaluación</button>
        </form>

        <div id="pantalla-bloqueo">
            <h1 style="font-size: 55px; margin-bottom: 10px;">⛔ PRUEBA BLOQUEADA ⛔</h1>
            <h2>Se ha detectado un cambio de pestaña, ventana o aplicación.</h2>
            <p style="font-size: 1.2em;">La evaluación ha sido enviada automáticamente con las respuestas que llegaste a marcar.</p>
        </div>

        <script>
            document.addEventListener("visibilitychange", function() {
                if (document.hidden) {
                    document.getElementById("pantalla-bloqueo").style.display = "block";
                    document.getElementById("formulario-examen").style.display = "none";
                    document.getElementById("btn-entregar").click();
                }
            });
        </script>
    <?php } ?>
</div>

</body>
</html>