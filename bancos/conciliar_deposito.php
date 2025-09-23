<?php
include 'db.php';

$deposito_id = intval($_POST['deposito_id']);
$facturas = json_decode($_POST['facturas'], true);
$condonacion = floatval($_POST['condonacion']);

// Guarda las facturas asociadas al depósito (como lista en el campo factura)
$facturas_ids = implode(',', array_column($facturas, 'factura'));
$stmt = $conn->prepare("UPDATE bancos SET factura=? WHERE id=?");
$stmt->bind_param("si", $facturas_ids, $deposito_id);
$stmt->execute();
$stmt->close();

// Marca cada factura como liquidada y actualiza condonación en la última factura
foreach ($facturas as $ix => $f) {
    $liquidada = 1;
    $cond = ($ix == count($facturas)-1) ? $condonacion : 0;
    $stmt = $conn->prepare("UPDATE facturas SET factura_liquidada=?, condonacion=? WHERE factura=?");
    $stmt->bind_param("ids", $liquidada, $cond, $f['factura']);
    $stmt->execute();
    $stmt->close();
}

echo "Conciliación guardada correctamente";
?>