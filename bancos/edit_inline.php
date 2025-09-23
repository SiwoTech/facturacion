<?php
include 'db.php';

$id = intval($_POST['id']);
$field = $_POST['field'];
$value = $_POST['value'];

$permitidos = ['factura', 'descripcion_gastos_ingresos', 'complemento_portales'];
if (!in_array($field, $permitidos)) {
    http_response_code(403);
    echo "Campo no permitido";
    exit;
}

if ($field == 'factura' && trim($value) === '') {
    http_response_code(400);
    echo "Debes seleccionar una factura válida";
    exit;
}

// Actualiza el campo en bancos
$sql = "UPDATE bancos SET $field=? WHERE id=?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo "Error de SQL: " . $conn->error;
    exit;
}
$stmt->bind_param("si", $value, $id);
$stmt->execute();

if ($stmt->error) {
    http_response_code(500);
    echo "Error al guardar: " . $stmt->error;
    exit;
}

// Si se está asignando una factura, verifica si el monto está cubierto
if ($field == 'factura') {
    // Obtén el monto de la factura
    $factura = $conn->prepare("SELECT monto FROM facturas WHERE factura=?");
    $factura->bind_param("s", $value);
    $factura->execute();
    $factura->bind_result($monto_factura);
    $factura->fetch();
    $factura->close();

    // Suma depósitos y retiros asociados a la factura en bancos
    $suma = $conn->prepare("SELECT SUM(depositos - retiros) FROM bancos WHERE factura=?");
    $suma->bind_param("s", $value);
    $suma->execute();
    $suma->bind_result($monto_cubierto);
    $suma->fetch();
    $suma->close();

    // Cálculo y guardado de condonación
    $condonacion = 0;
    if (floatval($monto_cubierto) < floatval($monto_factura)) {
        $condonacion = floatval($monto_factura) - floatval($monto_cubierto);
    }
    $upd_cond = $conn->prepare("UPDATE facturas SET condonacion=? WHERE factura=?");
    $upd_cond->bind_param("ds", $condonacion, $value);
    $upd_cond->execute();
    $upd_cond->close();

    // Si el monto cubierto es igual o mayor al monto de la factura, márcala como liquidada
    if (floatval($monto_cubierto) >= floatval($monto_factura)) {
        $upd = $conn->prepare("UPDATE facturas SET factura_liquidada=1 WHERE factura=?");
        $upd->bind_param("s", $value);
        $upd->execute();
        $upd->close();
    } else {
        // Si no está liquidada, márcala como no liquidada
        $upd = $conn->prepare("UPDATE facturas SET factura_liquidada=0 WHERE factura=?");
        $upd->bind_param("s", $value);
        $upd->execute();
        $upd->close();
    }
}

echo "OK";
?>