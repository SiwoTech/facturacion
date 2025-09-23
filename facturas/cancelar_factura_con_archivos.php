<?php
include '../bancos/db.php';

$msgs = [];
$uuidOrig = '';
$uuidSust = '';
$datosNuevaFactura = []; // Para mostrar el número de la nueva factura

// 1. Recupera el folio de la factura original que se va a cancelar
$folioOriginal = isset($_POST['folio_original']) ? $_POST['folio_original'] : '';
if ($folioOriginal) {
    // Busca el UUID de la original por folio/número
    $stmt = $conn->prepare("SELECT uuid FROM facturas WHERE factura=? LIMIT 1");
    $stmt->bind_param("s", $folioOriginal);
    $stmt->execute();
    $stmt->bind_result($uuidOrig);
    $stmt->fetch();
    $stmt->close();
    if (empty($uuidOrig)) {
        $msgs[] = "No se encontró la factura original con folio: $folioOriginal.";
    }
}

// 2. Procesa la factura sustituta (XML nuevo)
if (isset($_FILES['xml_sustituta']) && $_FILES['xml_sustituta']['tmp_name']) {
    $fileSust = $_FILES['xml_sustituta']['tmp_name'];
    $xmlSust = simplexml_load_file($fileSust);
    $xmlSust->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
    $xmlSust->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
    $timbreSust = $xmlSust->xpath('//tfd:TimbreFiscalDigital');
    if ($timbreSust && isset($timbreSust[0])) {
        $uuidSust = (string)$timbreSust[0]['UUID'];
    }

    // Extrae más datos de la factura sustituta
    $facturaNueva       = (string)($xmlSust['Folio'] ?? '');
    $fechaNueva         = (string)($xmlSust['Fecha'] ?? '');
    $tipoComprobante    = (string)($xmlSust['TipoDeComprobante'] ?? '');
    $montoNueva         = (float)($xmlSust['Total'] ?? 0);

    // Receptor
    $receptor           = $xmlSust->xpath('//cfdi:Receptor');
    $nombreReceptor     = ($receptor && isset($receptor[0])) ? (string)$receptor[0]['Nombre'] : '';
    $rfcReceptor        = ($receptor && isset($receptor[0])) ? (string)$receptor[0]['Rfc'] : '';
    $usoCFDI            = ($receptor && isset($receptor[0])) ? (string)$receptor[0]['UsoCFDI'] : '';

    // Concepto
    $concepto           = $xmlSust->xpath('//cfdi:Concepto');
    $productoServicio   = ($concepto && isset($concepto[0])) ? (string)$concepto[0]['Descripcion'] : '';

    $metodoPago         = (string)($xmlSust['MetodoPago'] ?? '');

    // Guarda para mostrar luego en la respuesta
    $datosNuevaFactura = [
        'factura' => $facturaNueva,
        'uuid' => $uuidSust
    ];

    if ($uuidSust) {
        // Verifica si la factura nueva ya existe en la base
        $stmt = $conn->prepare("SELECT id FROM facturas WHERE uuid=?");
        $stmt->bind_param("s", $uuidSust);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 0) {
            // Inserta la nueva factura sustituta con más campos
            $stmt->close();
            $stmt = $conn->prepare("
                INSERT INTO facturas (
                    uuid, factura, fecha, razon_social, monto, tipo_comprobante, producto_servicio,
                    receptor_nombre, receptor_rfc, receptor_uso_cfdi, metodo_pago, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activa')
            ");
            $stmt->bind_param(
                "ssssdssssss",
                $uuidSust, $facturaNueva, $fechaNueva, $nombreReceptor, $montoNueva,
                $tipoComprobante, $productoServicio, $nombreReceptor, $rfcReceptor, $usoCFDI, $metodoPago
            );
            if ($stmt->execute()) {
                $msgs[] = "Factura sustituta guardada en la base (UUID: $uuidSust, Folio: $facturaNueva).";
            } else {
                $msgs[] = "Error al guardar la factura sustituta: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $msgs[] = "Factura sustituta ya existe en la base (UUID: $uuidSust).";
            $stmt->close();
        }
    } else {
        $msgs[] = "No se pudo extraer UUID de factura sustituta.";
    }
}

// 3. Marca la original como cancelada y la liga a la sustituta (solo si ambos UUID existen)
if (!empty($uuidOrig) && !empty($uuidSust)) {
    $motivo = isset($_POST['motivo_cancelacion']) ? $_POST['motivo_cancelacion'] : '';
    // Usar sustituye_cfdi que ya existe
    $stmt = $conn->prepare("UPDATE facturas SET estado='cancelada', motivo_cancelacion=?, sustituye_cfdi=? WHERE uuid=?");
    $stmt->bind_param("sss", $motivo, $uuidSust, $uuidOrig);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $msgs[] = "Factura original cancelada y ligada a la sustituta.";
    } else {
        $msgs[] = "No se pudo cancelar la factura original (UUID: $uuidOrig).";
    }
    $stmt->close();
}

// Devuelve el resultado y el número de la nueva factura para mostrarlo en la tabla y ocultar el botón cancelar
echo implode("<br>", $msgs);
if (!empty($datosNuevaFactura['factura'])) {
    echo "<br><b>Nueva factura (folio): {$datosNuevaFactura['factura']}</b>";
}
?>