<?php
$host = '31.170.167.52';
$user = 'u826340212_edoresultados';
$pass = 'Cwo9982061148.';
$dbname = 'u826340212_edoresultados';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>