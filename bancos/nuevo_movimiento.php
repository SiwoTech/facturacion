<?php
$db = new mysqli('31.170.167.52', 'u826340212_edoresultados', 'Cwo9982061148.', 'u826340212_edoresultados');

// Consulta facturas disponibles
$facturas = $db->query("SELECT * FROM facturas WHERE factura_liquidada = 0 ORDER BY fecha DESC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'];
    $tipo_movimiento = $_POST['tipo_movimiento'];
    $importe = floatval($_POST['importe']);
    $tipo_pago = $_POST['tipo_pago'];
    $descripcion = $_POST['descripcion'];
    $facturas_seleccionadas = $_POST['facturas']; // array de ID factura

    $total_facturas = 0;
    foreach ($facturas_seleccionadas as $fid) {
        $res = $db->query("SELECT monto FROM facturas WHERE id = ".intval($fid));
        $row = $res->fetch_assoc();
        $total_facturas += floatval($row['monto']);
    }

    if ($total_facturas < $importe) {
        $error = "El importe de las facturas es menor al del movimiento bancario. Agrega más facturas.";
    } elseif ($total_facturas > $importe) {
        $error = "El importe de las facturas es mayor al del movimiento bancario. Corrige la selección.";
    } else {
        // Guardar movimiento
        $db->query("INSERT INTO bancos_movimientos (fecha, tipo_movimiento, importe, tipo_pago, descripcion, usuario_captura, fecha_captura) VALUES ('$fecha', '$tipo_movimiento', $importe, '$tipo_pago', '$descripcion', '".$_SESSION['usuario']."', NOW())");
        $movimiento_id = $db->insert_id;

        // Relacionar facturas y marcarlas como liquidadas
        foreach ($facturas_seleccionadas as $fid) {
            $db->query("INSERT INTO bancos_movimientos_facturas (movimiento_id, factura_id, importe_aplicado) VALUES ($movimiento_id, $fid, (SELECT monto FROM facturas WHERE id = $fid))");
            $db->query("UPDATE facturas SET factura_liquidada = 1 WHERE id = $fid");
        }

        header("Location: movimientos.php?ok=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Nuevo Movimiento Bancario</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h2>Nuevo Movimiento Bancario</h2>
    <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>
    <form method="post">
        <label>Fecha: <input type="date" name="fecha" required></label><br>
        <label>Tipo movimiento: 
            <select name="tipo_movimiento">
                <option value="ingreso">Ingreso (Depósito)</option>
                <option value="egreso">Egreso (Retiro)</option>
            </select>
        </label><br>
        <label>Importe: <input type="number" step="0.01" name="importe" required></label><br>
        <label>Tipo de pago: 
            <select name="tipo_pago">
                <option value="99">99</option>
                <option value="complemento">Complemento</option>
                <option value="pse">PSE</option>
                <option value="otro">Otro</option>
            </select>
        </label><br>
        <label>Descripción: <input type="text" name="descripcion" required></label><br>
        <label>Facturas a asociar:</label>
        <div style="max-height: 200px; overflow-y: auto;">
            <?php while ($f = $facturas->fetch_assoc()): ?>
                <label>
                    <input type="checkbox" name="facturas[]" value="<?php echo $f['id']; ?>">
                    <?php echo $f['factura']." | ".$f['razon_social']." | $".$f['monto']; ?>
                </label><br>
            <?php endwhile; ?>
        </div>
        <button type="submit">Guardar Movimiento</button>
    </form>
</body>
</html>