<?php
include 'db.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id']);
$sql = "SELECT * FROM bancos WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo "Registro no encontrado.";
    exit;
}
$row = $res->fetch_assoc();

if (isset($_POST['save'])) {
    $factura = $_POST['factura'];
    $descripcion_gastos_ingresos = $_POST['descripcion_gastos_ingresos'];
    $complemento_portales = $_POST['complemento_portales'];

    $update = $conn->prepare("UPDATE bancos SET factura=?, descripcion_gastos_ingresos=?, complemento_portales=? WHERE id=?");
    $update->bind_param("sssi", $factura, $descripcion_gastos_ingresos, $complemento_portales, $id);
    $update->execute();

    header("Location: index.php?msg=Registro actualizado");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar registro</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Editar campos permitidos</h2>
    <form method="post">
        <label>Factura:<br>
            <input type="text" name="factura" value="<?= htmlspecialchars($row['factura']) ?>">
        </label><br>
        <label>Descripción Gastos/Ingresos:<br>
            <input type="text" name="descripcion_gastos_ingresos" value="<?= htmlspecialchars($row['descripcion_gastos_ingresos']) ?>">
        </label><br>
        <label>Complemento Portales:<br>
            <input type="text" name="complemento_portales" value="<?= htmlspecialchars($row['complemento_portales']) ?>">
        </label><br>
        <button type="submit" name="save">Guardar</button>
    </form>
    <a href="index.php">Regresar</a>
</body>
</html>