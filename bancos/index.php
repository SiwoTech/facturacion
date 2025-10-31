<?php

include 'db.php';

// Se agrega f.condonacion al SELECT
$result = $conn->query("
    SELECT b.*, f.monto AS monto_factura, f.factura_liquidada, f.tipo_comprobante, f.metodo_pago, f.condonacion
    FROM bancos b
    LEFT JOIN facturas f ON b.factura = f.factura
    ORDER BY b.fecha_operacion DESC
");

$facturas_all = [];
$facturas = $conn->query("SELECT factura, razon_social, receptor_nombre, monto, factura_liquidada FROM facturas WHERE factura_liquidada = 0 ORDER BY fecha DESC");
while ($f = $facturas->fetch_assoc()) {
    $facturas_all[] = $f;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Movimientos Bancarios</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.jqueryui.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
</head>
<body>
    <header class="topbar">
        <div class="topbar-left">
            <img src="../img/LOGOCWOB.png" alt="Logo" class="topbar-logo">
            <span class="topbar-title">Estado de Facturación</span>
        </div>
        <nav class="topbar-nav">
            <a href="../index.php">Dashboard Mensual</a>
            <a href="index.php" class="active">Bancos</a>
            <a href="../facturas/index.php">Facturas</a>
            <a href="../facturas_vendedor/index.php" class="active">Facturas por Vendedor</a>
        </nav>
        <div class="topbar-right">
            <button class="btn-exit">Salir</button>
        </div>
    </header>
    <div class="main-content">
        <h2>Movimientos Bancarios</h2>
        <?php if (isset($_GET['msg'])) echo "<p class='success'>{$_GET['msg']}</p>"; ?>
        <a href="upload.php">Subir Archivo de Bancos</a>
		<a href="upload_layout.php">SUbir Layout Excel</a>
        <table id="tabla-cobranza" class="display">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cuenta</th>
                    <th>Fecha</th>
                    <th>Movimiento</th>
                    <th>Descripción</th>
                    <th>Descripción Detallada</th>
                    <th>Depósitos</th>
                    <th>Retiros</th>
                    <th>Saldo</th>
                    <th>Factura</th>
                    <th>Estado Factura</th>
                    <th>Tipo de Pago</th>
                    <th>Tipo Comprobante</th>
                    <th>Monto Factura</th>
                    <th>Descripción Gastos/Ingresos</th>
                    <th>Complemento Portales</th>
                    <th>Múltiples Facturas</th>
                </tr>
                <tr>
                    <th><input type="text" placeholder="🔎 ID" /></th>
                    <th><input type="text" placeholder="🔎 Cuenta" /></th>
                    <th><input type="text" placeholder="🔎 Fecha" /></th>
                    <th><input type="text" placeholder="🔎 Movimiento" /></th>
                    <th><input type="text" placeholder="🔎 Descripción" /></th>
                    <th><input type="text" placeholder="🔎 Detallada" /></th>
                    <th><input type="text" placeholder="🔎 Depósitos" /></th>
                    <th><input type="text" placeholder="🔎 Retiros" /></th>
                    <th><input type="text" placeholder="🔎 Saldo" /></th>
                    <th><input type="text" placeholder="🔎 Factura" /></th>
                    <th><input type="text" placeholder="🔎 Estado Factura" /></th>
                    <th><input type="text" placeholder="🔎 Tipo Pago" /></th>
                    <th><input type="text" placeholder="🔎 Tipo Comprobante" /></th>
                    <th><input type="text" placeholder="🔎 Monto Factura" /></th>
                    <th><input type="text" placeholder="🔎 Desc. Gastos/Ing." /></th>
                    <th><input type="text" placeholder="🔎 Complemento" /></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                // Lógica para bloquear edición si ya está conciliado
                $facturas_asignadas = [];
                if ($row['factura']) {
                    $facturas_asignadas = explode(',', $row['factura']);
                }
                $monto_facturas = 0;
                if ($facturas_asignadas) {
                    $sql = "SELECT SUM(monto) AS total FROM facturas WHERE factura IN ('" . implode("','", array_map('addslashes', $facturas_asignadas)) . "')";
                    $rs = $conn->query($sql);
                    $r = $rs ? $rs->fetch_assoc() : ['total'=>0];
                    $monto_facturas = $r['total'] ?? 0;
                }
                $condonacion = floatval($row['condonacion'] ?? 0);
                $deposito = floatval($row['depositos']);
                $retiro = floatval($row['retiros']);
                $is_conciliado = (
                    ($deposito > 0 && abs($deposito - $monto_facturas - $condonacion) < 0.01) ||
                    ($retiro > 0 && abs($retiro - $monto_facturas - $condonacion) < 0.01)
                );
                ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['cuenta']) ?></td>
                    <td><?= $row['fecha_operacion'] ?></td>
                    <td><?= htmlspecialchars($row['movimiento']) ?></td>
                    <td><?= htmlspecialchars($row['descripcion']) ?></td>
                    <td><?= htmlspecialchars($row['descripcion_detallada']) ?></td>
                    <td><?= number_format($row['depositos'],2) ?></td>
                    <td><?= number_format($row['retiros'],2) ?></td>
                    <td><?= number_format($row['saldo'],2) ?></td>
                    <td<?php if (!$is_conciliado): ?> class="editable"<?php endif; ?>
                        data-id="<?= $row['id'] ?>"
                        data-field="factura"
                        data-depositos="<?= $row['depositos'] ?>"
                        data-retiros="<?= $row['retiros'] ?>"
                    ><?= htmlspecialchars($row['factura']) ?></td>
                    <td>
                        <?php 
                        if ($row['factura_liquidada'] === null) echo 'Sin factura';
                        elseif ($row['factura_liquidada']) echo '<span style="color:green;">Liquidada</span>';
                        else echo '<span style="color:red;">Pendiente</span>';
                        ?>
                    </td>
                    <td><?= htmlspecialchars($row['metodo_pago']) ?></td>
                    <td><?= htmlspecialchars($row['tipo_comprobante']) ?></td>
                    <td><?= $row['monto_factura'] !== null ? '$'.number_format($row['monto_factura'],2) : '' ?></td>
                    <td<?php if (!$is_conciliado): ?> class="editable"<?php endif; ?> data-id="<?= $row['id'] ?>" data-field="descripcion_gastos_ingresos">
                        <?php
                        if (isset($row['condonacion']) && $row['condonacion'] > 0) {
                            echo "<span style='color:orange;'>Condonación: $".number_format($row['condonacion'],2)."</span>";
                        } else {
                            echo htmlspecialchars($row['descripcion_gastos_ingresos']);
                        }
                        ?>
                    </td>
                    <td<?php if (!$is_conciliado): ?> class="editable"<?php endif; ?> data-id="<?= $row['id'] ?>" data-field="complemento_portales"><?= htmlspecialchars($row['complemento_portales']) ?></td>
                    <td>
                        <button type="button" class="btn-multiples-facturas" onclick="abrirConciliacionModal(<?= $row['id'] ?>)" <?= $is_conciliado ? 'disabled style="background:#bbb;cursor:not-allowed;"' : '' ?>>Múltiples Facturas</button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <!-- Modal para conciliación múltiple -->
    <div id="modal-conciliacion-multiple" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:99999;">
        <div class="modal-content-conciliacion" style="background:#fff;max-width:540px;margin:5% auto;padding:30px;border-radius:8px;box-shadow:0 3px 20px #333;">
            <!-- Aquí se carga el contenido AJAX -->
        </div>
    </div>
    <script>
    $(document).ready(function() {
        var tabla = $('#tabla-cobranza').DataTable({
            language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-MX.json" },
            pageLength: 25,
            orderCellsTop: true,
            fixedHeader: true
        });

        $('#tabla-cobranza thead tr:eq(1) th').each(function (i) {
            $('input', this).on('keyup change', function () {
                if (tabla.column(i).search() !== this.value) {
                    tabla.column(i).search(this.value).draw();
                }
            });
        });

        var facturas_all = <?php echo json_encode($facturas_all); ?>;

        // Edición en línea
        document.querySelectorAll('.editable').forEach(function(cell) {
            cell.addEventListener('click', function() {
                if (cell.querySelector('input') || cell.querySelector('select')) return;
                var valor = cell.textContent;
                var field = cell.getAttribute('data-field');
                var depositos = parseFloat(cell.getAttribute('data-depositos'));
                var retiros = parseFloat(cell.getAttribute('data-retiros'));

                if (field === 'factura') {
                    var select = document.createElement('select');
                    var option_empty = document.createElement('option');
                    option_empty.value = '';
                    option_empty.textContent = '-- Seleccionar factura --';
                    select.appendChild(option_empty);

                    var opts = facturas_all.filter(function(f) {
                        var razon_social = (f.razon_social || '').toLowerCase();
                        if (depositos !== 0) {
                            return razon_social === 'gute idee';
                        }
                        if (retiros !== 0) {
                            return razon_social !== 'gute idee';
                        }
                        return false;
                    });

                    opts.forEach(function(f) {
                        var option = document.createElement('option');
                        option.value = f.factura;
                        var monto = Number(f.monto).toFixed(2);
                        if (depositos !== 0) {
                            option.textContent = f.factura + " | " + f.receptor_nombre + " | $" + monto;
                        }
                        else if (retiros !== 0) {
                            option.textContent = f.factura + " | " + f.razon_social + " | $" + monto;
                        }
                        if (f.factura === valor) option.selected = true;
                        select.appendChild(option);
                    });

                    cell.textContent = '';
                    cell.appendChild(select);
                    select.focus();

                    select.addEventListener('change', guardar);
                    select.addEventListener('blur', guardar);

                    function guardar() {
                        var nuevoValor = select.value;
                        cell.textContent = nuevoValor;
                        if (nuevoValor !== valor) {
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', 'edit_inline.php', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onload = function() {
                                if (xhr.status !== 200) {
                                    alert('Error al guardar');
                                    cell.textContent = valor;
                                } else {
                                    location.reload();
                                }
                            };
                            xhr.send('id=' + cell.getAttribute('data-id') +
                                    '&field=' + field +
                                    '&value=' + encodeURIComponent(nuevoValor));
                        }
                    }
                } else {
                    var input = document.createElement('input');
                    input.type = 'text';
                    input.value = valor;
                    cell.textContent = '';
                    cell.appendChild(input);
                    input.focus();

                    input.addEventListener('blur', guardar);
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') guardar();
                        if (e.key === 'Escape') cell.textContent = valor;
                    });

                    function guardar() {
                        var nuevoValor = input.value;
                        cell.textContent = nuevoValor;
                        if (nuevoValor !== valor) {
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', 'edit_inline.php', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onload = function() {
                                if (xhr.status !== 200) {
                                    alert('Error al guardar');
                                    cell.textContent = valor;
                                }
                            };
                            xhr.send('id=' + cell.getAttribute('data-id') +
                                    '&field=' + field +
                                    '&value=' + encodeURIComponent(nuevoValor));
                        }
                    }
                }
            });
        });
    });

    // Modal conciliación múltiple
    function abrirConciliacionModal(id_banco){
        var modal = document.getElementById('modal-conciliacion-multiple');
        var content = modal.querySelector('.modal-content-conciliacion');
        modal.style.display = 'block';
        content.innerHTML = '<div style="text-align:center;padding:50px;">Cargando...</div>';
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'conciliacion_multiple.php?id_banco=' + id_banco);
        xhr.onload = function(){
            content.innerHTML = xhr.responseText;
        };
        xhr.send();
    }
    function closeConciliacionModal(){
        document.getElementById('modal-conciliacion-multiple').style.display = 'none';
    }
    </script>
</body>
</html> 