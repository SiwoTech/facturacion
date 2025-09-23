<?php
include 'db.php';

if (isset($_POST['submit'])) {
    if (isset($_FILES['csv']) && $_FILES['csv']['error'] == 0) {
        $file = fopen($_FILES['csv']['tmp_name'], 'r');
        fgetcsv($file); // Omitir encabezado

        $inserted = 0;
        $skipped = 0;

        while (($data = fgetcsv($file)) !== FALSE) {
            $cuenta = trim($data[0], "'");
            $fecha_operacion = date('Y-m-d', strtotime(str_replace('/', '-', $data[1])));
            $descripcion = $data[2];
            $depositos = ($data[3] == '-') ? 0.00 : floatval(str_replace(['$', ','], '', $data[3]));
            $retiros = ($data[4] == '-') ? 0.00 : floatval(str_replace(['$', ','], '', $data[4]));
            $saldo = floatval(str_replace(['$', ','], '', $data[5]));
            $movimiento = $data[6];
            $descripcion_detallada = $data[7];

            // Verificar duplicado
            $check = $conn->prepare("SELECT id FROM bancos WHERE cuenta=? AND fecha_operacion=? AND movimiento=?");
            $check->bind_param("sss", $cuenta, $fecha_operacion, $movimiento);
            $check->execute();
            $check->store_result();

            if ($check->num_rows == 0) {
                $sql = "INSERT INTO bancos (cuenta, fecha_operacion, movimiento, descripcion, descripcion_detallada, depositos, retiros, saldo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssss", $cuenta, $fecha_operacion, $movimiento, $descripcion, $descripcion_detallada, $depositos, $retiros, $saldo);
                $stmt->execute();
                $inserted++;
            } else {
                $skipped++;
            }
        }
        fclose($file);
        header("Location: index.php?msg=Datos subidos exitosamente ($inserted nuevos, $skipped duplicados omitidos)");
        exit;
    }
    else {
        echo "Error al subir archivo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Movimientos Bancarios</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <img src="logo.png" alt="Logo">
        <h1>Bancos</h1>
    </div>
    <div class="main-content">
        <h2>Subir archivo CSV bancario</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="csv" accept=".csv" required>
            <button type="submit" name="submit">Subir</button>
        </form>
        <a href="index.php">Ver registros</a>
    </div>
</body>
</html>