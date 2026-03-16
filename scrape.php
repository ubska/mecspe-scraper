#!/usr/bin/env php
<?php
/**
 * MECSPE Exhibitors Scraper
 * Usage:
 *   php scrape.php                   # scrape all pages
 *   php scrape.php --start=1 --end=5 # scrape pages 1-5 only (useful for testing)
 *   php scrape.php --start=1 --end=1 # scrape first page only (quick test)
 */

require __DIR__ . '/vendor/autoload.php';

use Mecspe\MecspeScraper;

// ---------------------------------------------------------------------------
// Parse CLI arguments
// ---------------------------------------------------------------------------
$opts = getopt('', ['start:', 'end:', 'output:', 'help']);

if (isset($opts['help'])) {
    echo <<<HELP
MECSPE Exhibitors Scraper
--------------------------
Usage: php scrape.php [options]

Options:
  --start=N     First page to scrape (default: 1)
  --end=N       Last page to scrape  (default: auto-detect)
  --output=DIR  Output directory     (default: ./output)
  --help        Show this help

Examples:
  php scrape.php                    # Full scrape (all pages)
  php scrape.php --start=1 --end=3  # Only first 3 pages (test)
HELP;
    exit(0);
}

$startPage  = isset($opts['start']) ? (int) $opts['start'] : 1;
$endPage    = isset($opts['end'])   ? (int) $opts['end']   : 0;
$outputDir  = $opts['output'] ?? __DIR__ . '/output';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// ---------------------------------------------------------------------------
// Run scraper
// ---------------------------------------------------------------------------
$timestamp = date('Y-m-d_H-i-s');

echo "=================================================\n";
echo " MECSPE Exhibitors Scraper\n";
echo "=================================================\n";
echo " Start page : " . ($startPage) . "\n";
echo " End page   : " . ($endPage > 0 ? $endPage : 'auto') . "\n";
echo " Output dir : {$outputDir}\n";
echo "=================================================\n\n";

$scraper = new MecspeScraper(verbose: true);

try {
    $exhibitors = $scraper->scrape($startPage, $endPage, $outputDir);

    $jsonPath = "{$outputDir}/mecspe_espositori_{$timestamp}.json";
    $csvPath  = "{$outputDir}/mecspe_espositori_{$timestamp}.csv";

    $scraper->saveJson($jsonPath);
    $scraper->saveCsv($csvPath);

    echo "\n=================================================\n";
    echo " Done! Scraped " . count($exhibitors) . " exhibitors.\n";
    echo " Files saved in: {$outputDir}/\n";
    echo "=================================================\n";
} catch (\Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
