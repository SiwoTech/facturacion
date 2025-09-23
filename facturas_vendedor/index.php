<?php
// Conexión principal a u826340212_edoresultados
include '../bancos/db.php';

// Conexión secundaria a u826340212_orangedb
$conn_codhotel = new mysqli('31.170.167.52', 'u826340212_orangedb', 'Cwo9982061148', 'u826340212_orangedb');
if ($conn_codhotel->connect_error) {
    die('Error conexión codhotel: ' . $conn_codhotel->connect_error);
}

// Obtener lista de vendedores ÚNICOS
$vendedores = [];
$q_vend = $conn_codhotel->query("SELECT vendedor FROM codhotel GROUP BY vendedor ORDER BY vendedor");
while ($v = $q_vend->fetch_assoc()) {
    $vendedores[] = $v['vendedor'];
}

// Filtros
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : 0;
$vendedor = isset($_GET['vendedor']) ? $_GET['vendedor'] : '';

// Si hay vendedor, busca razones sociales asociadas
$razon_sociales = [];
if ($vendedor) {
    $q_rs = $conn_codhotel->query("SELECT razonsocial FROM codhotel WHERE vendedor = '".$conn_codhotel->real_escape_string($vendedor)."'");
    while ($rs = $q_rs->fetch_assoc()) {
        $razon_sociales[] = $rs['razonsocial'];
    }
}

$where = "YEAR(fecha) = $anio";
if ($mes) $where .= " AND MONTH(fecha) = $mes";
if ($vendedor && count($razon_sociales) > 0) {
    $razones = array_map(function($r) use ($conn) {
        return "'".$conn->real_escape_string($r)."'";
    }, $razon_sociales);
    $where .= " AND receptor_nombre IN (" . implode(',', $razones) . ")";
}

$sql = "SELECT * FROM facturas WHERE $where ORDER BY fecha DESC";
$res = $conn->query($sql);

$liquidadas = $pendientes = $canceladas = 0;
$monto_liquidadas = $monto_pendientes = $monto_canceladas = 0;
$facturas_liquidadas = $facturas_pendientes = $facturas_canceladas = [];

while ($row = $res->fetch_assoc()) {
    if ($row['estado'] === 'cancelada') {
        $canceladas++;
        $monto_canceladas += $row['monto'];
        $facturas_canceladas[] = $row;
    } elseif ($row['factura_liquidada']) {
        $liquidadas++;
        $monto_liquidadas += $row['monto'];
        $facturas_liquidadas[] = $row;
    } else {
        $pendientes++;
        $monto_pendientes += $row['monto'];
        $facturas_pendientes[] = $row;
    }
}

// Para mostrar el nombre del vendedor seleccionado
$vendedor_nombre = $vendedor; // Solo muestra el nombre si está seleccionado
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte por Vendedor</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .resumen-facturas { display: flex; gap: 22px; margin: 22px 0 32px 0;}
        .resumen-box { background: #fff7f3; border-radius: 14px; box-shadow: 0 2px 8px #f2652230; padding: 28px 30px; font-size: 1.2em; min-width: 185px; text-align: center;}
        .resumen-box.cancelada { border-left: 7px solid #e74c3c;}
        .resumen-box.liquidada { border-left: 7px solid #27ae60;}
        .resumen-box.pendiente { border-left: 7px solid orange;}
        .facturas-minilist { margin-top: 10px; }
        .facturas-minilist table { width: 100%; border-collapse: collapse; background: #fff; }
        .facturas-minilist th, .facturas-minilist td { padding: 7px 10px; border-bottom: 1px solid #f2e9e7; font-size: 1em; }
        .facturas-minilist th { background: #f26522; color: #fff; }
        .facturas-minilist tr:last-child td { border-bottom: none; }
        .toggle-btn { background: none; border: none; color: #f26522; cursor: pointer; font-size: 1em; }
        .hidden { display: none; }
        @media (max-width: 700px) {
            .resumen-facturas { flex-direction: column; gap: 16px;}
            .resumen-box { min-width: 95vw; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-left">
            <img src="../img/LOGOCWOB.png" alt="Logo" class="topbar-logo">
            <span class="topbar-title">Estado de Facturación</span>
        </div>
        <nav class="topbar-nav">
            <a href="../index.php" >Dashboard Mensual</a>
            <a href="../bancos/index.php">Bancos</a>
            <a href="../facturas/index.php">Facturas</a>
			<a href="index.php" class="active">Facturas por Vendedor</a>
        </nav>
        <div class="topbar-right">
            <button class="btn-exit">Salir</button>
        </div>
    </header>
    <main>
        <form method="GET" class="form-filtros">
            <label>Vendedor:
				<select name="vendedor">
					<option value="">Todos</option>
					<?php
					foreach ($vendedores as $vend) {
						$sel = ($vend === $vendedor) ? 'selected' : '';
						echo '<option value="'.htmlspecialchars($vend).'" '.$sel.'>'.htmlspecialchars($vend).'</option>';
					}
					?>
				</select>
			</label>
            <label>Año:
                <input type="number" name="anio" value="<?php echo $anio; ?>" min="2020" max="2050">
            </label>
            <label>Mes:
                <select name="mes">
                    <option value="">Todos</option>
                    <?php for($m=1;$m<=12;$m++) {
                        $sel = ($mes == $m) ? 'selected' : '';
                        echo '<option value="'.$m.'" '.$sel.'>'.date('F', mktime(0,0,0,$m,1)).'</option>';
                    } ?>
                </select>
            </label>
            <button type="submit">Filtrar</button>
        </form>
        <h2>
            <?php
                if ($vendedor_nombre) echo "Vendedor: <b>$vendedor_nombre</b> ";
                echo " | Periodo: <b>$anio</b>";
                if ($mes) echo " / <b>".date('F', mktime(0,0,0,$mes,1))."</b>";
            ?>
        </h2>
        <div class="resumen-facturas">
            <div class="resumen-box pendiente">
                <div><b>Pendientes</b></div>
                <div><?php echo $pendientes; ?> facturas</div>
                <div>$<?php echo number_format($monto_pendientes,2); ?></div>
                <?php if ($pendientes > 0): ?>
                    <button class="toggle-btn" onclick="toggleList('pendientes')">Ver facturas ▼</button>
                    <div id="list-pendientes" class="facturas-minilist hidden">
                        <table>
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facturas_pendientes as $f): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($f['factura']); ?></td>
                                        <td>$<?php echo number_format($f['monto'],2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="resumen-box liquidada">
                <div><b>Liquidadas</b></div>
                <div><?php echo $liquidadas; ?> facturas</div>
                <div>$<?php echo number_format($monto_liquidadas,2); ?></div>
                <?php if ($liquidadas > 0): ?>
                    <button class="toggle-btn" onclick="toggleList('liquidadas')">Ver facturas ▼</button>
                    <div id="list-liquidadas" class="facturas-minilist hidden">
                        <table>
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facturas_liquidadas as $f): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($f['factura']); ?></td>
                                        <td>$<?php echo number_format($f['monto'],2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="resumen-box cancelada">
                <div><b>Canceladas</b></div>
                <div><?php echo $canceladas; ?> facturas</div>
                <div>$<?php echo number_format($monto_canceladas,2); ?></div>
                <?php if ($canceladas > 0): ?>
                    <button class="toggle-btn" onclick="toggleList('canceladas')">Ver facturas ▼</button>
                    <div id="list-canceladas" class="facturas-minilist hidden">
                        <table>
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facturas_canceladas as $f): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($f['factura']); ?></td>
                                        <td>$<?php echo number_format($f['monto'],2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
            function toggleList(tipo) {
                var el = document.getElementById('list-' + tipo);
                if (el.classList.contains('hidden')) {
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            }
        </script>
    </main>
</body>
</html>