<?php
$db = new mysqli('31.170.167.52', 'u826340212_edoresultados', 'Cwo9982061148.', 'u826340212_edoresultados');

$id = intval($_GET['id']);
$mov = $db->query("SELECT * FROM bancos_movimientos WHERE id = $id")->fetch_assoc();
$facturas_asociadas = $db->query("SELECT factura_id FROM bancos_movimientos_facturas WHERE movimiento_id = $id");
$facturas_ids = [];
while ($row = $facturas_asociadas->fetch_assoc()) $facturas_ids[] = $row['factura_id'];

// Solo facturas no liquidadas o las asociadas a este movimiento
$facturas = $db->query("
    SELECT * FROM facturas 
    WHERE factura_liquidada = 0 OR id IN (".implode(',', $facturas_ids).")
    ORDER BY fecha DESC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo = $_POST['motivo'];
    $nuevas_facturas = $_POST['facturas'];
    $datos_antes = json_encode($mov);

    // Liberar facturas anteriores
    foreach ($facturas_ids as $fid) {
        // Solo liberar si ya no está en la nueva selección
        if (!in_array($fid, $nuevas_facturas)) {
            $db->query("UPDATE facturas SET factura_liquidada = 0 WHERE id = $fid");
        }
    }

    // Actualizar movimiento y asociar nuevas facturas
    $db->query("DELETE FROM bancos_movimientos_facturas WHERE movimiento_id = $id");
    foreach ($nuevas_facturas as $fid) {
        $db->query("INSERT INTO bancos_movimientos_facturas (movimiento_id, factura_id, importe_aplicado) VALUES ($id, $fid, (SELECT monto FROM facturas WHERE id = $fid))");
        $db->query("UPDATE facturas SET factura_liquidada = 1 WHERE id = $fid");
    }

    // Auditar
    $datos_despues = json_encode($_POST);
    $db->query("INSERT INTO bancos_movimientos_auditoria (movimiento_id, fecha, usuario, motivo, datos_antes, datos_despues) 
        VALUES ($id, NOW(), '".$_SESSION['usuario']."', '$motivo', '$datos_antes', '$datos_despues')");

    header("Location: movimientos.php?edit=ok");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Editar Movimiento Bancario</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h2>Editar Movimiento Bancario</h2>
    <form method="post">
        <label>Motivo de modificación: 
            <select name="motivo" required>
                <option value="reemplazo">Reemplazo</option>
                <option value="error_captura">Error de captura</option>
                <option value="peticion_cliente">Petición del cliente</option>
                <option value="vencimiento_fecha">Vencimiento de fecha</option>
            </select>
        </label><br>
        <label>Facturas a asociar (elige nuevas):</label>
        <div style="max-height: 200px; overflow-y: auto;">
            <?php while ($f = $facturas->fetch_assoc()): ?>
                <label>
                    <input type="checkbox" name="facturas[]" value="<?php echo $f['id']; ?>"
                        <?php if (in_array($f['id'], $facturas_ids)) echo 'checked'; ?>>
                    <?php echo $f['factura']." | ".$f['razon_social']." | $".$f['monto']; ?>
                </label><br>
            <?php endwhile; ?>
        </div>
        <button type="submit">Guardar Cambios</button>
    </form>
</body>
</html>