<?php
// Conexión principal a u826340212_edoresultados
include '../bancos/db.php';

// Conexión secundaria a u826340212_orangedb
$conn_codhotel = new mysqli('31.170.167.52', 'u826340212_orangedb', 'Cwo9982061148', 'u826340212_orangedb');
if ($conn_codhotel->connect_error) {
    die('Error conexión codhotel: ' . $conn_codhotel->connect_error);
}

// Consulta de facturas
$res = $conn->query("SELECT * FROM facturas ORDER BY fecha DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Facturas</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .badge-pendiente { color: #fff; background: orange; padding: 2px 8px; border-radius: 5px;}
        .badge-liquidada { color: #fff; background: green; padding: 2px 8px; border-radius: 5px;}
        .badge-cancelada { color: #fff; background: red; padding: 2px 8px; border-radius: 5px;}
        .btn-cancelar { background: #f26522; color: #fff; border: none; padding: 5px 12px; border-radius: 6px; cursor: pointer;}
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
            <a href="index.php" class="active">Facturas</a>
			<a href="../facturas_vendedor/index.php">Facturas por Vendedor</a>
        </nav>
        <div class="topbar-right">
            <button class="btn-exit">Salir</button>
        </div>
    </header>
    <main class="scroll-x">
        <section class="dashboard-card" style="max-width:none; width:98vw;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <h2>Facturas</h2>
                <a href="facturas_subir.php" class="btn-year" style="background:#f26522;">Subir XML</a>
            </div>
            <h2>Listado de Facturas</h2>
            <div class="facturas-listado">
                <div class="facturas-responsive">
                    <table class="facturas-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Factura</th>
                                <th>Razón Social</th>
                                <th>Vendedor</th> <!-- Nuevo vendedor traído de codhotel -->
                                <th>Producto/Servicio</th>
                                <th>Monto</th>
                                <th>Método Pago</th>
                                <th>Tipo Comp.</th>
                                <th>UUID</th>
                                <th>Nombre Receptor</th>
                                <th>Fecha Pago</th>
                                <th>Núm. Operación</th>
                                <th>Documento Relacionado</th>
                                <th>Saldo Anterior</th>
                                <th>Saldo Insoluto</th>
                                <th>RFC Receptor</th>
                                <th>Dom. Fiscal Receptor</th>
                                <th>Régimen Receptor</th>
                                <th>Uso CFDI</th>
                                <th>Facturas Complemento</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = $res->fetch_assoc()): ?>
                            <?php
                                // Buscar vendedor en codhotel, comparando receptor_nombre con razon_social
                                $receptor_nombre = $conn_codhotel->real_escape_string($row['receptor_nombre']);
                                $sql_vendedor = "SELECT vendedor FROM codhotel WHERE razonsocial = '$receptor_nombre' LIMIT 1";
                                $q = $conn_codhotel->query($sql_vendedor);
                                $vendedor = '';
                                if ($v = $q->fetch_assoc()) {
                                    $vendedor = $v['vendedor'];
                                }
                            ?>
                            <tr>
                                <td><?php echo $row['fecha']; ?></td>
                                <td><?php echo htmlspecialchars($row['factura']); ?></td>
                                <td><?php echo htmlspecialchars($row['razon_social']); ?></td>
                                <td><?php echo htmlspecialchars($vendedor); ?></td>
                                <td><?php echo htmlspecialchars($row['producto_servicio']); ?></td>
                                <td><?php echo number_format($row['monto'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['metodo_pago']); ?></td>
                                <td><?php echo htmlspecialchars($row['tipo_comprobante']); ?></td>
                                <td><?php echo htmlspecialchars($row['uuid']); ?></td>
                                <td><?php echo htmlspecialchars($row['receptor_nombre']); ?></td>
                                <td><?php echo $row['fecha_pago']; ?></td>
                                <td><?php echo htmlspecialchars($row['num_operacion']); ?></td>
                                <td><?php echo htmlspecialchars($row['documento_relacionado']); ?></td>
                                <td><?php echo $row['saldo_anterior'] !== null ? number_format($row['saldo_anterior'], 2) : ''; ?></td>
                                <td><?php echo $row['saldo_insoluto'] !== null ? number_format($row['saldo_insoluto'], 2) : ''; ?></td>
                                <td><?php echo htmlspecialchars($row['receptor_rfc']); ?></td>
                                <td><?php echo htmlspecialchars($row['receptor_dom_fiscal']); ?></td>
                                <td><?php echo htmlspecialchars($row['receptor_regimen']); ?></td>
                                <td><?php echo htmlspecialchars($row['receptor_uso_cfdi']); ?></td>
                                <td><?php echo htmlspecialchars($row['facturas_complemento']); ?></td>
                                <td>
                                   <?php
                                    if ($row['estado'] === 'cancelada') {
                                        echo '<span class="badge-cancelada">❌ Cancelada</span>';
                                    } elseif ($row['factura_liquidada']) {
                                        echo '<span class="badge-liquidada">✔ Liquidada</span>';
                                    } else {
                                        echo '<span class="badge-pendiente">⏳ Pendiente</span>';
                                    }
                                    ?>
                                </td>
                                <td id="acciones-<?php echo $row['id']; ?>">
									<?php if ($row['estado'] !== 'cancelada'): ?>
										<button class="btn-cancelar" onclick="abrirCancelarModal('<?php echo $row['id']; ?>', '<?php echo $row['factura']; ?>')">Cancelar</button>
									<?php else: ?>
										<?php
										// Si está cancelada, muestra el folio de la factura sustituta si existe
										if (!empty($row['sustituye_cfdi'])) {
											// Busca el folio de la factura sustituta
											$folioSust = '';
											$stmtF = $conn->prepare("SELECT factura FROM facturas WHERE uuid=? LIMIT 1");
											$stmtF->bind_param("s", $row['sustituye_cfdi']);
											$stmtF->execute();
											$stmtF->bind_result($folioSust);
											$stmtF->fetch();
											$stmtF->close();
											echo "<span class='badge-liquidada'>Sustituida por: $folioSust</span>";
										} else {
											echo '<span class="badge-cancelada">❌ Cancelada</span>';
										}
										?>
									<?php endif; ?>
								</td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Modal Cancelar -->
			<div id="modal-cancelar" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); justify-content:center; align-items:center;">
				<form id="form-cancelar" enctype="multipart/form-data" style="background:#fff;padding:30px;border-radius:12px;min-width:350px;">
					<h3>Cancelar Factura <span id="factura-cancelar"></span></h3>
					<input type="hidden" name="factura_id" id="factura_id">
                    <!-- Nuevo campo oculto para el folio de la factura -->
                    <input type="hidden" name="folio_original" id="folio_original">
					<label>Motivo:</label>
					<select name="motivo_cancelacion" id="motivo-cancelar" onchange="mostrarSustituye()">
						<option value="error_cliente">Error del cliente</option>
						<option value="peticion_cliente">Por petición del cliente</option>
						<option value="vencimiento_fecha">Vencimiento de fecha</option>
						<option value="por_reemplazo">Por reemplazo</option>
					</select>
					<div id="sustituye-opcion" style="display:none; margin-top:12px;">
						<div style="color:#e74c3c; padding:10px; border:1px solid #e74c3c; border-radius:6px; background:#fff7f7;">
							Para cancelar por reemplazo, sube aquí el XML de la factura sustituta.<br>
							<input type="file" name="xml_sustituta" accept=".xml">
						</div>
					</div>
					<br>
					<button type="submit" class="btn-year" style="background:#f26522;">Confirmar Cancelación</button>
					<button type="button" onclick="cerrarCancelarModal()" style="margin-left:10px;">Cerrar</button>
				</form>
			</div>
        </section>
    </main>
    <script>
      // Nueva variable global para guardar el folio
      var facturasFolio = {};

      <?php
      // Generamos un objeto JS con id -> folio para acceso rápido
      $res->data_seek(0);
      while ($row = $res->fetch_assoc()) {
          echo "facturasFolio['{$row['id']}'] = '" . addslashes($row['factura']) . "';\n";
      }
      ?>

      function abrirCancelarModal(id, factura) {
			document.getElementById('modal-cancelar').style.display = 'flex';
			document.getElementById('factura_id').value = id;
			document.getElementById('factura-cancelar').innerText = factura;
            // Asigna el folio automáticamente
            document.getElementById('folio_original').value = facturasFolio[id];
			document.getElementById('motivo-cancelar').value = 'error_cliente';
			mostrarSustituye();
		}
		function cerrarCancelarModal() {
			document.getElementById('modal-cancelar').style.display = 'none';
		}
		function mostrarSustituye() {
			var motivo = document.getElementById('motivo-cancelar').value;
			document.getElementById('sustituye-opcion').style.display = (motivo === 'por_reemplazo') ? 'block' : 'none';
		}

		document.getElementById('form-cancelar').onsubmit = function(e) {
			e.preventDefault();
			var formData = new FormData(document.getElementById('form-cancelar'));
			fetch('cancelar_factura_con_archivos.php', {
				method: 'POST',
				body: formData
			}).then(r=>r.text()).then(res=>{
				// Busca el folio sustituto en la respuesta
				var folioMatch = res.match(/Nueva factura \(folio\): ([A-Za-z0-9\-]+)/);
				var facturaId = document.getElementById('factura_id').value;
				if (folioMatch) {
					var folioSust = folioMatch[1];
					// Actualiza el TD de acciones en la tabla
					var tdAcciones = document.getElementById('acciones-' + facturaId);
					tdAcciones.innerHTML = "<span class='badge-liquidada'>Sustituida por: " + folioSust + "</span>";
				} else {
					alert(res);
				}
				cerrarCancelarModal();
			});
		};
    </script>
</body>
</html>