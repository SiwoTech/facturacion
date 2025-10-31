<?php
include 'db.php';

require '../autoload.php'; // Asegúrate de tener PhpSpreadsheet instalado o configurado

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['layout']) && $_FILES['layout']['error'] === 0) {
        $file = $_FILES['layout']['tmp_name'];

        try {
            // Leer el archivo Excel
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $aplicadas = 0;
            $canceladas = 0;
            $pagos_registrados = 0;
            $no_encontradas = [];

            foreach ($rows as $index => $row) {
                if ($index === 0) continue; // Saltar encabezados

                // Suponiendo que las facturas están en la primera columna y separadas por comas
                $facturas = explode(',', trim($row[0])); // Ajusta el índice según la columna de facturas en tu archivo Excel
                $facturas = array_map('trim', $facturas); // Elimina espacios en blanco alrededor de cada factura

                // Procesar facturas aplicadas y registrar pagos parciales
                foreach ($facturas as $factura) {
                    if (!empty($factura)) {
                        // Buscar la factura en la base de datos
                        $stmt = $conn->prepare("SELECT id FROM facturas WHERE factura = ?");
                        $stmt->bind_param("s", $factura);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            $factura_id = $result->fetch_assoc()['id'];

                            // Verificar si hay un pago parcial en la descripción
                            if (!empty($row[1])) { // Ajusta el índice según la columna "Descripción"
                                $descripcion = trim($row[1]);
                                $insert_pago = $conn->prepare("INSERT INTO pagos (factura_id, descripcion) VALUES (?, ?)");
                                $insert_pago->bind_param("is", $factura_id, $descripcion);
                                $insert_pago->execute();
                                $pagos_registrados++;
                            }

                            // Marcar la factura como aplicada (si no lo ha sido aún)
                            $update = $conn->prepare("UPDATE facturas SET factura_liquidada = 1 WHERE id = ?");
                            $update->bind_param("i", $factura_id);
                            $update->execute();
                            $aplicadas++;
                        } else {
                            // Registrar facturas no encontradas
                            $no_encontradas[] = $factura;
                        }
                    }
                }

                // Procesar facturas canceladas (suponiendo que está en la columna "Descripción" en el índice 2)
                if (!empty($row[2])) { // Ajusta el índice según la columna de facturas canceladas
                    $factura_cancelada = trim($row[2]);
                    if (!empty($factura_cancelada)) {
                        $stmt = $conn->prepare("SELECT id FROM facturas WHERE factura = ?");
                        $stmt->bind_param("s", $factura_cancelada);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            // Marcar la factura como cancelada
                            $update = $conn->prepare("UPDATE facturas SET estado = 'cancelada' WHERE factura = ?");
                            $update->bind_param("s", $factura_cancelada);
                            $update->execute();
                            $canceladas++;
                        } else {
                            // Registrar facturas no encontradas
                            $no_encontradas[] = $factura_cancelada;
                        }
                    }
                }
            }

            // Mensaje de resultado
            $msg = "Proceso completado: $aplicadas facturas marcadas como aplicadas, $canceladas facturas marcadas como canceladas, $pagos_registrados pagos registrados.";
            if (!empty($no_encontradas)) {
                $msg .= " No se encontraron las siguientes facturas: " . implode(", ", $no_encontradas);
            }

            header("Location: upload_layout.php?msg=" . urlencode($msg));
            exit;

        } catch (Exception $e) {
            die('Error al procesar el archivo: ' . $e->getMessage());
        }
    } else {
        die('Error al subir el archivo.');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Layout de Facturas</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="header">
        <img src="../img/LOGOCWOB.png" alt="Logo">
        <h1>Subir Layout de Facturas</h1>
    </div>
    <div class="main-content">
        <h2>Subir archivo de layout</h2>
        <?php if (isset($_GET['msg'])): ?>
            <p class="success"><?= htmlspecialchars($_GET['msg']) ?></p>
        <?php endif; ?>
        <form action="upload_layout.php" method="post" enctype="multipart/form-data">
            <label for="layout">Selecciona el archivo Excel:</label>
            <input type="file" name="layout" id="layout" accept=".xls,.xlsx" required>
            <button type="submit">Subir y Procesar</button>
        </form>
        <a href="index.php">Regresar a Bancos</a>
    </div>
</body>
</html>