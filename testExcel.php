<?php
// Incluir el autoloader
require 'autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    // Leer un archivo Excel
    $spreadsheet = IOFactory::load('archivo.xlsx');
    $worksheet = $spreadsheet->getActiveSheet();

    // Imprimir las filas y celdas
    foreach ($worksheet->getRowIterator() as $row) {
        $rowData = [];
        foreach ($row->getCellIterator() as $cell) {
            $rowData[] = $cell->getValue();
        }
        print_r($rowData);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>