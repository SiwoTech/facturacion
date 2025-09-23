<?php
include 'bancos/db.php';

// Año seleccionado (por defecto, el actual)
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Obtén los datos mensuales de facturas emitidas y cobradas
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$emitidas = [];
$cobradas = [];
foreach ($meses as $m => $mes) {
    // Facturas emitidas en el mes
    $r = $conn->query("SELECT SUM(monto) as total FROM facturas WHERE YEAR(fecha)=$year AND MONTH(fecha)=$m");
    $emitidas[$m] = $r->fetch_assoc()['total'] ?: 0;

    // Facturas cobradas (liquidadas) en el mes
    $r = $conn->query("SELECT SUM(monto) as total FROM facturas WHERE YEAR(fecha)=$year AND MONTH(fecha)=$m AND factura_liquidada=1");
    $cobradas[$m] = $r->fetch_assoc()['total'] ?: 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Plataforma de Facturación</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header class="topbar">
        <div class="topbar-left">
            <img src="img/LOGOCWOB.png" alt="Logo" class="topbar-logo">
            <span class="topbar-title">Estado de Facturación</span>
        </div>
        <nav class="topbar-nav">
            <a href="#" class="active">Dashboard Mensual</a>
            <a href="bancos/index.php">Bancos</a>
            <a href="facturas/index.php">Facturas</a>
			<a href="facturas_vendedor/index.php">Facturas por Vendedor</a>
        </nav>
        <div class="topbar-right">
            <button class="btn-exit">Salir</button>
        </div>
    </header>

    <main>
        <section class="dashboard-card">
            <div class="dashboard-header">
                <h2>Dashboard Anual</h2>
                <form class="dashboard-year" method="get" style="display:flex;align-items:center;gap:1em;">
                    <span>Año:</span>
                    <input type="number" value="<?= $year ?>" min="2000" max="2100" class="year-input" name="year">
                    <button class="btn-year" type="submit">Ver año</button>
                </form>
            </div>
            <div class="dashboard-months">
                <?php foreach ($meses as $m => $mes): ?>
                <div class="month-card">
                    <h3><?= $mes ?></h3>
                    <div class="chart-legend">
                        <span class="legend emitidas"></span> Facturas Emitidas
                        <span class="legend cobradas"></span> Facturas Cobradas
                    </div>
                    <canvas id="chart-<?= strtolower($mes) ?>"></canvas>
                    <div class="month-totals">
                        <span class="emitidas"><strong>Emitidas:</strong> $<?= number_format($emitidas[$m],2) ?></span>
                        <span class="cobradas"><strong>Cobradas:</strong> $<?= number_format($cobradas[$m],2) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <script>
    // Datos desde PHP
    const meses = <?= json_encode(array_values($meses)) ?>;
    const emitidas = <?= json_encode(array_values($emitidas)) ?>;
    const cobradas = <?= json_encode(array_values($cobradas)) ?>;

    // Dibuja las gráficas mes a mes
    meses.forEach(function(mes, idx){
        const idCanvas = 'chart-' + mes.toLowerCase();
        const ctx = document.getElementById(idCanvas).getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total'],
                datasets: [
                    {
                        label: 'Facturas Emitidas',
                        backgroundColor: '#72c59a',
                        data: [emitidas[idx]],
                    },
                    {
                        label: 'Facturas Cobradas',
                        backgroundColor: '#f2994a',
                        data: [cobradas[idx]],
                    }
                ]
            },
            options: {
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true } }
            }
        });
    });
    </script>
</body>
</html>