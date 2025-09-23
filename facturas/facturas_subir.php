<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Factura XML</title>
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
            <h2>Subir Factura XML</h2>
            <form action="procesar_xml.php" method="post" enctype="multipart/form-data">
                <label for="xmlfile" style="font-weight:bold;">Selecciona uno o varios archivos XML:</label>
                <input type="file" name="xmlfile[]" id="xmlfile" accept=".xml" required style="margin-bottom:22px;" multiple>
                <button class="btn-year" type="submit">Subir y Procesar</button>
            </form>
        </section>
    </main>
</body>
</html>