<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';

$id_banco = isset($_GET['id_banco']) ? intval($_GET['id_banco']) : 0;

// Obtiene el movimiento bancario
$stmt = $conn->prepare("SELECT * FROM bancos WHERE id = ?");
$stmt->bind_param("i", $id_banco);
$stmt->execute();
$banco = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$banco) {
    echo "<div style='padding:30px;'><h2>Error: Movimiento no encontrado.</h2></div>";
    exit;
}

// Obtiene todas las facturas no liquidadas
$facturas = [];
$q = $conn->query("SELECT factura, razon_social, receptor_nombre, monto FROM facturas WHERE factura_liquidada = 0 ORDER BY fecha DESC");
while ($row = $q->fetch_assoc()) {
    $facturas[] = $row;
}

$deposito = floatval($banco['depositos'] ?? 0);
$retiro = floatval($banco['retiros'] ?? 0);
$monto_movimiento = $deposito > 0 ? $deposito : $retiro;

// Procesa el formulario de conciliación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['facturas'])) {
    $facturas_seleccionadas = $_POST['facturas'];
    if (!is_array($facturas_seleccionadas)) $facturas_seleccionadas = [];
    $facturas_str = implode(',', array_map('trim', $facturas_seleccionadas));

    // Suma de los montos seleccionados
    $monto_total = 0;
    if ($facturas_seleccionadas) {
        $in_clause = "'" . implode("','", array_map('addslashes', $facturas_seleccionadas)) . "'";
        $sql = "SELECT SUM(monto) AS total FROM facturas WHERE factura IN ($in_clause)";
        $rs = $conn->query($sql);
        $r = $rs ? $rs->fetch_assoc() : ['total'=>0];
        $monto_total = $r['total'] ?? 0;
    }

    // Calcula restante
    $restante = $monto_movimiento - $monto_total;
    $condonacion = 0;
    // El usuario confirma condonación solo si el monto no cuadra
    if (abs($restante) > 0.01 && isset($_POST['confirm_condonacion']) && $_POST['confirm_condonacion'] == "1") {
        $condonacion = $restante;
    }

    // Actualiza bancos
    $stmt = $conn->prepare("UPDATE bancos SET factura = ?, condonacion = ? WHERE id = ?");
    $stmt->bind_param("sdi", $facturas_str, $condonacion, $id_banco);
    if ($stmt->execute()) {
        // Marca facturas como liquidadas
        if ($facturas_seleccionadas) {
            $in_clause = "'" . implode("','", array_map('addslashes', $facturas_seleccionadas)) . "'";
            if (!$conn->query("UPDATE facturas SET factura_liquidada = 1 WHERE factura IN ($in_clause)")) {
                echo "<div style='padding:30px;color:red;'><h3>Error al actualizar facturas: " . $conn->error . "</h3></div>";
                exit;
            }
        }
        echo "<div style='padding:30px;'>";
        echo "<h3>¡Conciliación realizada con éxito!</h3>";
        echo "<p>Facturas asociadas: $facturas_str</p>";
        if ($condonacion != 0) {
            echo "<p>Condonación registrada: $" . number_format($condonacion,2) . "</p>";
        }
        echo "<button onclick=\"window.parent.closeConciliacionModal()\">Cerrar</button></div>";
        exit;
    } else {
        echo "<div style='padding:30px;color:red;'><h3>Error al actualizar bancos: " . $stmt->error . "</h3></div>";
        exit;
    }
}
?>
<div style="font-family: Arial, sans-serif; max-width: 520px; margin:0 auto;">
    <h2 style="margin-top:0;">Conciliación múltiple de facturas</h2>
    <div style="background:#f8f8f8;padding:10px 15px;margin-bottom:18px;border-radius:6px;">
        <b>Movimiento:</b> <?= htmlspecialchars($banco['descripcion'] ?? '') ?><br>
        <b>Fecha:</b> <?= htmlspecialchars($banco['fecha_operacion'] ?? '') ?><br>
        <b>Cuenta:</b> <?= htmlspecialchars($banco['cuenta'] ?? '') ?><br>
        <b>Depósitos:</b> $<?= number_format($banco['depositos'] ?? 0,2) ?><br>
        <b>Retiros:</b> $<?= number_format($banco['retiros'] ?? 0,2) ?><br>
        <b>Saldo:</b> $<?= number_format($banco['saldo'] ?? 0,2) ?><br>
        <b>Facturas asociadas actualmente:</b> <?= htmlspecialchars($banco['factura'] ?? '') ?: '-' ?><br>
        <b>Condonación actual:</b> $<?= number_format($banco['condonacion'] ?? 0,2) ?><br>
    </div>
    <div class="modal-content-conciliacion">
    <form id="form-conciliacion-multiple" method="post">
        <div style="margin-bottom:10px;">
            <label for="facturas"><b>Selecciona las facturas que deseas conciliar con este movimiento:</b></label>
            <select name="facturas[]" id="facturas-multiple" multiple size="10" style="width:100%;max-width:500px;">
                <?php foreach ($facturas as $f): ?>
                    <option value="<?= htmlspecialchars($f['factura']) ?>" data-monto="<?= htmlspecialchars($f['monto']) ?>">
                        <?= htmlspecialchars($f['factura']) ?> | <?= htmlspecialchars($f['razon_social']) ?> | <?= htmlspecialchars($f['receptor_nombre']) ?> | $<?= number_format($f['monto'],2) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Puedes seleccionar varias facturas (Ctrl/Cmd + clic).</small>
        </div>
        <div id="total-monto" style="font-size:1.1em;color:#1976d2;"></div>
        <div id="restante-info" style="font-size:1em;color:#c00;"></div>
        <div id="condonacion-form" style="display:none;margin-top:8px;">
            <label>
                <input type="checkbox" id="confirm_condonacion" name="confirm_condonacion" value="1"> Registrar el restante como condonación
            </label>
            <input type="number" step="0.01" id="condonacion" name="condonacion" readonly style="width:90px;">
        </div>
        <div style="margin-top:12px;">
            <button type="submit" style="padding:8px 18px;background:#1976d2;color:#fff;border:none;border-radius:3px;cursor:pointer;">Conciliar facturas seleccionadas</button>
            <button type="button" onclick="window.parent.closeConciliacionModal()" style="padding:8px 18px;background:#bbb;border:none;border-radius:3px;cursor:pointer;margin-left:10px;">Cerrar</button>
        </div>
    </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var select = document.getElementById('facturas-multiple');
        function updateTotal() {
            var options = select && select.options ? select.options : [];
            var total = 0;
            for (var i = 0; i < options.length; i++) {
                if (options[i].selected) {
                    var monto = options[i].getAttribute('data-monto');
                    if (monto) total += parseFloat(monto);
                }
            }
            var monto_mov = <?= json_encode($monto_movimiento) ?>;
            var restante = (monto_mov - total).toFixed(2);
            document.getElementById('total-monto').textContent = 'Monto total de facturas seleccionadas: $' + total.toFixed(2);

            // debug en consola
            console.log('Total:', total, 'Monto movimiento:', monto_mov, 'Restante:', restante);

            if (Math.abs(restante) > 0.01) {
                document.getElementById('restante-info').textContent = 'Restante: $' + restante;
                document.getElementById('condonacion-form').style.display = '';
                document.getElementById('condonacion').value = restante;
            } else {
                document.getElementById('restante-info').textContent = '';
                document.getElementById('condonacion-form').style.display = 'none';
                document.getElementById('confirm_condonacion').checked = false;
                document.getElementById('condonacion').value = '';
            }
        }
        if (select) {
            select.addEventListener('change', updateTotal);
            updateTotal();
        } else {
            console.error("No se encontró el select de facturas.");
        }

        // AJAX submit para que no recargue el modal
        var form = document.getElementById('form-conciliacion-multiple');
        form.onsubmit = function(e){
            var restante = document.getElementById('condonacion').value;
            var confirm = document.getElementById('confirm_condonacion').checked;
            if (restante && Math.abs(restante) > 0.01 && !confirm) {
                alert('Debes confirmar que el restante quedará como condonación.');
                return false;
            }
            e.preventDefault();
            var data = new FormData(form);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '');
            xhr.onload = function(){
                // Muestra el resultado en el modal
                document.querySelector('.modal-content-conciliacion').innerHTML = xhr.responseText;
            };
            xhr.onerror = function() {
                alert('Hubo un error al guardar. Intenta de nuevo.');
            };
            xhr.send(data);
        };
    });
    </script>
</div>