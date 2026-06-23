<?php
include 'conexion.php';

if (isset($_POST['cambiar_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'];
    $sql_update = "UPDATE configuracion SET estado_prueba = '$nuevo_estado' WHERE id = 1";
    mysqli_query($conexion, $sql_update);
}

$sql_estado = "SELECT estado_prueba FROM configuracion WHERE id = 1";
$resultado_estado = mysqli_query($conexion, $sql_estado);
$fila_estado = mysqli_fetch_assoc($resultado_estado);
$estado_actual = $fila_estado['estado_prueba'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Docente</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f6f9; }
        .panel { background: white; padding: 20px; border-radius: 8px; max-width: 800px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #2c3e50; color: white; }
    </style>
</head>
<body>
    <div class="panel">
        <h2>👨‍🏫 Panel de Control - Evaluación de Pseudocódigo</h2>
        
        <div style="padding: 15px; background: #e8f4f8; border-radius: 5px; margin-bottom: 20px;">
            <h3>Estado de la prueba: <strong><?php echo strtoupper($estado_actual); ?></strong></h3>
            <form method="POST">
                <?php if ($estado_actual == 'cerrada') { ?>
                    <input type="hidden" name="nuevo_estado" value="abierta">
                    <button type="submit" name="cambiar_estado" style="background: #27ae60; color: white; padding: 10px; border: none; cursor: pointer;">Habilitar Prueba</button>
                <?php } else { ?>
                    <input type="hidden" name="nuevo_estado" value="cerrada">
                    <button type="submit" name="cambiar_estado" style="background: #e74c3c; color: white; padding: 10px; border: none; cursor: pointer;">Cerrar Prueba</button>
                <?php } ?>
            </form>
        </div>

        <h3>📝 Notas de los Estudiantes</h3>
        <table>
            <tr>
                <th>Cédula</th>
                <th>Nombre</th>
                <th>Nota (Sobre 10)</th>
                <th>Fecha</th>
            </tr>
            <?php
            $sql_notas = "SELECT notas.cedula_estudiante, notas.nota, notas.fecha, estudiantes.nombre 
                          FROM notas 
                          JOIN estudiantes ON notas.cedula_estudiante = estudiantes.cedula";
            $resultado_notas = mysqli_query($conexion, $sql_notas);

            if (mysqli_num_rows($resultado_notas) > 0) {
                while($fila = mysqli_fetch_assoc($resultado_notas)) {
                    echo "<tr>";
                    echo "<td>" . $fila['cedula_estudiante'] . "</td>";
                    echo "<td>" . $fila['nombre'] . "</td>";
                    echo "<td>" . $fila['nota'] . "</td>";
                    echo "<td>" . $fila['fecha'] . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='4'>Aún no hay pruebas entregadas.</td></tr>";
            }
            ?>
        </table>
    </div>
</body>
</html>