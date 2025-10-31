<?php
spl_autoload_register(function ($class) {
    // Ruta base donde está PhpSpreadsheet
    $baseDir = __DIR__ . '/lib/PhpSpreadsheet/';

    // Verifica si la clase pertenece al namespace PhpOffice\PhpSpreadsheet
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Si no pertenece al namespace PhpSpreadsheet, no hacer nada
        return;
    }

    // Define la ruta relativa de la clase
    $relativeClass = substr($class, $len);

    // Construye la ruta completa del archivo
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // Verifica y carga el archivo
    if (file_exists($file)) {
        require $file;
    } else {
        echo "Archivo no encontrado: $file\n";
    }
});