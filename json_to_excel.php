#!/usr/bin/env php
<?php
/**
 * Converte il JSON degli espositori MECSPE in un file Excel ben formattato.
 *
 * Uso:
 *   php json_to_excel.php                          # usa il JSON più recente in ./output/
 *   php json_to_excel.php --input=output/file.json # file specifico
 *   php json_to_excel.php --output=espositori.xlsx # nome file output
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

// ---------------------------------------------------------------------------
// Argomenti CLI
// ---------------------------------------------------------------------------
$opts      = getopt('', ['input:', 'output:']);
$outputDir = __DIR__ . '/output';

// Trova il JSON più recente se non specificato
if (isset($opts['input'])) {
    $jsonFile = $opts['input'];
} else {
    $files = glob($outputDir . '/*.json');
    if (empty($files)) {
        die("[ERRORE] Nessun file JSON trovato in {$outputDir}/\n");
    }
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    $jsonFile = $files[0];
}

if (!file_exists($jsonFile)) {
    die("[ERRORE] File non trovato: {$jsonFile}\n");
}

$xlsxFile = $opts['output'] ?? $outputDir . '/mecspe_espositori.xlsx';

echo "Input  : {$jsonFile}\n";
echo "Output : {$xlsxFile}\n\n";

// ---------------------------------------------------------------------------
// Carica dati
// ---------------------------------------------------------------------------
$data = json_decode(file_get_contents($jsonFile), true);
if (!$data) {
    die("[ERRORE] JSON non valido o vuoto.\n");
}
echo "Espositori trovati: " . count($data) . "\n";

// ---------------------------------------------------------------------------
// Crea Excel
// ---------------------------------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Espositori MECSPE');

// Intestazioni colonne
$columns = [
    'A' => ['label' => 'Azienda',           'key' => 'name',              'width' => 35],
    'B' => ['label' => 'Settore',            'key' => 'sector',            'width' => 28],
    'C' => ['label' => 'Padiglione',         'key' => 'pavilion',          'width' => 14],
    'D' => ['label' => 'Stand',              'key' => 'stand',             'width' => 10],
    'E' => ['label' => 'Telefono',           'key' => 'phone',             'width' => 20],
    'F' => ['label' => 'Email',              'key' => 'email',             'width' => 35],
    'G' => ['label' => 'Indirizzo',          'key' => 'address',           'width' => 40],
    'H' => ['label' => 'Sito Web',           'key' => 'website',           'width' => 30],
    'I' => ['label' => 'Servizi e Prodotti', 'key' => 'services_products', 'width' => 45],
    'J' => ['label' => 'Descrizione',        'key' => 'description',       'width' => 60],
    'K' => ['label' => 'Scheda MECSPE',      'key' => 'url',               'width' => 50],
];

// ---------------------------------------------------------------------------
// Riga intestazione (riga 1)
// ---------------------------------------------------------------------------
foreach ($columns as $col => $cfg) {
    $sheet->setCellValue("{$col}1", $cfg['label']);
    $sheet->getColumnDimension($col)->setWidth($cfg['width']);
}

// Stile intestazione: sfondo blu scuro, testo bianco, grassetto
$headerRange = 'A1:K1';
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => [
        'bold'  => true,
        'color' => ['argb' => Color::COLOR_WHITE],
        'size'  => 11,
    ],
    'fill' => [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF1F3864'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color'       => ['argb' => 'FFBFBFBF'],
        ],
    ],
]);
$sheet->getRowDimension(1)->setRowHeight(22);

// Blocca la riga di intestazione (freeze pane)
$sheet->freezePane('A2');

// ---------------------------------------------------------------------------
// Righe dati
// ---------------------------------------------------------------------------
foreach ($data as $i => $ex) {
    $row = $i + 2;

    foreach ($columns as $col => $cfg) {
        $value = $ex[$cfg['key']] ?? '';
        $sheet->setCellValue("{$col}{$row}", $value);
    }

    // Altezza riga adattiva
    $sheet->getRowDimension($row)->setRowHeight(18);

    // Colore alternato righe: bianco / grigio chiarissimo
    $bgColor = ($i % 2 === 0) ? 'FFFFFFFF' : 'FFF2F2F2';
    $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['argb' => $bgColor],
        ],
        'alignment' => [
            'vertical'   => Alignment::VERTICAL_CENTER,
            'wrapText'   => false,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['argb' => 'FFD9D9D9'],
            ],
        ],
    ]);

    // Colora in arancione le celle con dati mancanti
    foreach (['E', 'F'] as $col) {
        $val = $sheet->getCell("{$col}{$row}")->getValue();
        if (in_array($val, ['numero mancante', 'mail mancante'])) {
            $sheet->getStyle("{$col}{$row}")->applyFromArray([
                'font' => ['color' => ['argb' => 'FFBF5900'], 'italic' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF0D0']],
            ]);
        }
    }

    // Sito web come hyperlink cliccabile
    $website = $ex['website'] ?? '';
    if ($website !== '') {
        $sheet->getCell("H{$row}")->setHyperlink(new \PhpOffice\PhpSpreadsheet\Cell\Hyperlink($website));
        $sheet->getStyle("H{$row}")->applyFromArray([
            'font' => ['color' => ['argb' => 'FF0563C1'], 'underline' => Font::UNDERLINE_SINGLE],
        ]);
    }

    // Scheda MECSPE come hyperlink
    $url = $ex['url'] ?? '';
    if ($url !== '') {
        $sheet->getCell("K{$row}")->setHyperlink(new \PhpOffice\PhpSpreadsheet\Cell\Hyperlink($url));
        $sheet->getStyle("K{$row}")->applyFromArray([
            'font' => ['color' => ['argb' => 'FF0563C1'], 'underline' => Font::UNDERLINE_SINGLE],
        ]);
    }

    if ($row % 100 === 0) {
        echo "  Righe elaborate: {$row}...\n";
    }
}

// ---------------------------------------------------------------------------
// Filtro automatico su tutte le colonne
// ---------------------------------------------------------------------------
$lastRow = count($data) + 1;
$sheet->setAutoFilter("A1:K{$lastRow}");

// ---------------------------------------------------------------------------
// Salva file
// ---------------------------------------------------------------------------
$writer = new Xlsx($spreadsheet);
$writer->save($xlsxFile);

echo "\nFatto! File salvato: {$xlsxFile}\n";
echo "Righe totali: " . count($data) . "\n";
