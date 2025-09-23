<?php
include '../bancos/db.php';

// Validación básica de entrada
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$motivo = isset($_POST['motivo']) ? $_POST['motivo'] : '';
$sustituye = isset($_POST['sustituye']) ? $_POST['sustituye'] : '';

// Buscar la factura a cancelar
$stmt = $conn->prepare("SELECT uuid FROM facturas WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo "Factura no encontrada.";
    exit;
}
$uuid_cancelada = $row['uuid'];

// Marcar como cancelada y guardar motivo
$stmt = $conn->prepare("UPDATE facturas SET estado='cancelada', motivo_cancelacion=?, factura_sustituye=? WHERE id=?");
$stmt->bind_param("ssi", $motivo, $sustituye, $id);
$stmt->execute();
$stmt->close();

// Si hay factura que la sustituye, actualiza el campo en la nueva factura
if ($motivo == 'por_reemplazo' && $sustituye) {
    $stmt = $conn->prepare("UPDATE facturas SET sustituye_cfdi=? WHERE uuid=?");
    $stmt->bind_param("ss", $uuid_cancelada, $sustituye);
    $stmt->execute();
    $stmt->close();
}

echo "Factura cancelada correctamente.";
?>