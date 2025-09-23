<?php
$db = new mysqli('31.170.167.52', 'u826340212_edoresultados', 'Cwo9982061148.', 'u826340212_edoresultados');

$id = intval($_GET['id']);
$audit = $db->query("SELECT * FROM bancos_movimientos_auditoria WHERE movimiento_id = $id ORDER BY fecha DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Auditoría de Movimiento</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h2>Auditoría de Movimiento Bancario #<?php echo $id; ?></h2>
    <table border="1">
        <tr>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Motivo</th>
            <th>Datos antes</th>
            <th>Datos después</th>
        </tr>
        <?php while ($row = $audit->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['fecha']; ?></td>
            <td><?php echo $row['usuario']; ?></td>
            <td><?php echo $row['motivo']; ?></td>
            <td><pre><?php echo htmlspecialchars($row['datos_antes']); ?></pre></td>
            <td><pre><?php echo htmlspecialchars($row['datos_despues']); ?></pre></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>