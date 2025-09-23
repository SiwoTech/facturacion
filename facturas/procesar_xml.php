<?php
// Conexión a la BD
$db = new mysqli('31.170.167.52', 'u826340212_edoresultados', 'Cwo9982061148.', 'u826340212_edoresultados');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xmlfile'])) {
    $files = $_FILES['xmlfile'];
    $totalFiles = count($files['name']);
    $resultados = [];
    for ($i = 0; $i < $totalFiles; $i++) {
        $fileTmpName = $files['tmp_name'][$i];
        if (!$fileTmpName) {
            $resultados[] = "Error al subir el archivo #" . ($i+1);
            continue;
        }
        $xml = simplexml_load_file($fileTmpName);
        if (!$xml) {
            $resultados[] = "Archivo #" . ($i+1) . " XML inválido.";
            continue;
        }

        // Namespaces
        $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xml->registerXPathNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
        $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

        // Principal
        $comprobante = $xml;
        $emisor = $xml->xpath('//cfdi:Emisor')[0];
        $receptor = $xml->xpath('//cfdi:Receptor')[0];

        // Básicos
        $fecha = (string)$comprobante['Fecha'];
        $serie = (string)$comprobante['Serie'];
        $folio = (string)$comprobante['Folio'];
        $tipo_comprobante = (string)$comprobante['TipoDeComprobante'];
        $total_comprobante = (float)$comprobante['Total'];

        // Emisor
        $emisor_rfc = isset($emisor['Rfc']) ? (string)$emisor['Rfc'] : '';
        $emisor_nombre = isset($emisor['Nombre']) ? (string)$emisor['Nombre'] : '';

        // Receptor
        $receptor_rfc = isset($receptor['Rfc']) ? (string)$receptor['Rfc'] : '';
        $receptor_nombre = isset($receptor['Nombre']) ? (string)$receptor['Nombre'] : '';
        $receptor_dom_fiscal = isset($receptor['DomicilioFiscalReceptor']) ? (string)$receptor['DomicilioFiscalReceptor'] : '';
        $receptor_regimen = isset($receptor['RegimenFiscalReceptor']) ? (string)$receptor['RegimenFiscalReceptor'] : '';
        $receptor_uso_cfdi = isset($receptor['UsoCFDI']) ? (string)$receptor['UsoCFDI'] : '';

        // UUID
        $uuid = '';
        $timbre = $xml->xpath('//tfd:TimbreFiscalDigital');
        if ($timbre && isset($timbre[0])) {
            $uuid = (string)$timbre[0]['UUID'];
        }
		
		// VALIDAR ARCHIVO REPETIDO POR UUID
        if ($uuid) {
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM facturas WHERE uuid=?");
            $stmtCheck->bind_param("s", $uuid);
            $stmtCheck->execute();
            $stmtCheck->bind_result($existe);
            $stmtCheck->fetch();
            $stmtCheck->close();

            if ($existe > 0) {
                $resultados[] = "Archivo #" . ($i+1) . ": Ya existe en la base de datos (UUID repetido).";
                continue; // Salta al siguiente archivo
            }
        }
		
        // SUSTITUCIÓN: Busca si el XML sustituye alguna factura anterior (TipoRelacion 04)
        $sustituye_cfdi = null;
        $relacionados = $xml->xpath('//cfdi:CfdiRelacionados[@TipoRelacion="04"]/cfdi:CfdiRelacionado');
        if ($relacionados && isset($relacionados[0])) {
            // Toma el primer UUID relacionado como el que sustituye
            $sustituye_cfdi = (string)$relacionados[0]['UUID'];
            // Marca la factura anterior como cancelada si existe en la base
            if ($sustituye_cfdi) {
                // Actualiza el campo estado='cancelada' (ajusta si tu campo es diferente)
                $stmtCancel = $db->prepare("UPDATE facturas SET estado='cancelada' WHERE uuid=?");
                $stmtCancel->bind_param("s", $sustituye_cfdi);
                $stmtCancel->execute();
                $stmtCancel->close();
            }
        }

        // Complemento de pago
        if ($tipo_comprobante === 'P') {
            $pagos = $xml->xpath('//pago20:Pago');
            foreach ($pagos as $pago) {
                $fecha_pago = (string)$pago['FechaPago'];
                $forma_pago = (string)$pago['FormaDePagoP'];
                $moneda = (string)$pago['MonedaP'];
                $num_operacion = (string)$pago['NumOperacion'];

                $documentos = $pago->xpath('.//pago20:DoctoRelacionado');
                $facturas_complemento = [];
                foreach ($documentos as $doc) {
                    $doc_uuid = (string)$doc['IdDocumento'];
                    $doc_serie = (string)$doc['Serie'];
                    $doc_folio = (string)$doc['Folio'];
                    $imp_pagado = (float)$doc['ImpPagado'];
                    $saldo_anterior = (float)$doc['ImpSaldoAnt'];
                    $saldo_insoluto = (float)$doc['ImpSaldoInsoluto'];
                    $factura = $serie . $folio;
                    $razon_social = $emisor_nombre;
                    $vendedor = $emisor_rfc;
                    $producto_servicio = "Pago de factura " . $doc_serie . $doc_folio;
                    $monto = $imp_pagado;
                    $metodo_pago = $forma_pago;
                    $documento_relacionado = $doc_uuid;
                    $facturas_complemento[] = "$doc_serie-$doc_folio ($doc_uuid)";
                    $facturas_complemento_str = implode(", ", $facturas_complemento);

                    // Actualizado: agregar sustituye_cfdi
                    $stmt = $db->prepare("INSERT INTO facturas (
                        fecha, factura, razon_social, vendedor, producto_servicio, monto, metodo_pago,
                        tipo_comprobante, uuid, receptor_nombre, fecha_pago, num_operacion,
                        documento_relacionado, saldo_anterior, saldo_insoluto,
                        receptor_rfc, receptor_dom_fiscal, receptor_regimen, receptor_uso_cfdi, facturas_complemento,
                        sustituye_cfdi
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    $stmt->bind_param(
                        "ssssssdssssssddssssss",
                        $fecha,
                        $factura,
                        $razon_social,
                        $vendedor,
                        $producto_servicio,
                        $monto,
                        $metodo_pago,
                        $tipo_comprobante,
                        $uuid,
                        $receptor_nombre,
                        $fecha_pago,
                        $num_operacion,
                        $documento_relacionado,
                        $saldo_anterior,
                        $saldo_insoluto,
                        $receptor_rfc,
                        $receptor_dom_fiscal,
                        $receptor_regimen,
                        $receptor_uso_cfdi,
                        $facturas_complemento_str,
                        $sustituye_cfdi
                    );

                    if ($stmt->execute()) {
                        $resultados[] = "Archivo #" . ($i+1) . ": Complemento de pago procesado correctamente.";
                    } else {
                        $resultados[] = "Archivo #" . ($i+1) . ": Error al guardar: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        } else {
            // Factura normal
            $concepto = $xml->xpath('//cfdi:Concepto')[0];
            $producto_servicio = (string)$concepto['Descripcion'];
            $factura = $serie . $folio;
            $razon_social = $emisor_nombre;
            $vendedor = $emisor_rfc;
            $monto = $total_comprobante;
            $metodo_pago = '';
            $fecha_pago = null;
            $num_operacion = '';
            $documento_relacionado = '';
            $saldo_anterior = null;
            $saldo_insoluto = null;
            $facturas_complemento_str = '';

            // Actualizado: agregar sustituye_cfdi
            $stmt = $db->prepare("INSERT INTO facturas (
                fecha, factura, razon_social, vendedor, producto_servicio, monto, metodo_pago,
                tipo_comprobante, uuid, receptor_nombre, fecha_pago, num_operacion,
                documento_relacionado, saldo_anterior, saldo_insoluto,
                receptor_rfc, receptor_dom_fiscal, receptor_regimen, receptor_uso_cfdi, facturas_complemento,
                sustituye_cfdi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "ssssssdssssssddssssss",
                $fecha,
                $factura,
                $razon_social,
                $vendedor,
                $producto_servicio,
                $monto,
                $metodo_pago,
                $tipo_comprobante,
                $uuid,
                $receptor_nombre,
                $fecha_pago,
                $num_operacion,
                $documento_relacionado,
                $saldo_anterior,
                $saldo_insoluto,
                $receptor_rfc,
                $receptor_dom_fiscal,
                $receptor_regimen,
                $receptor_uso_cfdi,
                $facturas_complemento_str,
                $sustituye_cfdi
            );

            if ($stmt->execute()) {
                $resultados[] = "Archivo #" . ($i+1) . ": Factura guardada correctamente.";
            } else {
                $resultados[] = "Archivo #" . ($i+1) . ": Error al guardar: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    $db->close();
    $msg = implode("<br>", $resultados);
} else {
    $msg = "No se recibió archivo XML.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultado subida XML</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="header">
        <div class="header-left">
            <img src="../img/LOGOCWOB.png" alt="Logo">
            <h1>Plataforma de Cobranza</h1>
        </div>
        <div class="header-right">
            <a href="../index.php">Dashboard</a>
            <a href="index.php" class="active">Facturas</a>
            <a href="../bancos/index.php">Bancos</a>
        </div>
    </div>
    <main style="display:flex;justify-content:center;">
        <section class="dashboard-card" style="max-width:500px;padding:32px;">
            <h2>Resultado</h2>
            <p><?php echo $msg; ?></p>
            <br>
            <a href="facturas_subir.php" class="btn-year">Subir otro XML</a>
            <a href="index.php" class="btn-year">Ver Facturas</a>
        </section>
    </main>
</body>
</html>