<?php
$servidor = getenv("MYSQL_HOST") ?: "mariadb";
$usuario = getenv("MYSQL_USER") ?: "app_user";
$password = getenv("MYSQL_PASSWORD") ?: "app_pass_2026";
$base_datos = getenv("MYSQL_DATABASE") ?: "materiales1";
$puerto = getenv("MYSQL_PORT") ?: "3306";

$conexion = mysqli_connect($servidor, $usuario, $password, $base_datos, (int)$puerto);

if (!$conexion) {
    die("Fallo la conexion a la base de datos. Revisa los datos.");
}
?>