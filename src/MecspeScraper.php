<?php

namespace Mecspe;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class MecspeScraper
{
    private const BASE_URL    = 'https://mecspe.com';
    private const LIST_URL    = 'https://mecspe.com/portale/it/espositori/';
    private const DELAY_MS    = 500;   // ms between detail requests (be polite)
    private const MAX_RETRIES = 3;

    private Client $client;
    private array  $exhibitors = [];

    public function __construct(private bool $verbose = true)
    {
        $this->client = new Client([
            'timeout'         => 30,
            'connect_timeout' => 10,
            'headers'         => [
                'User-Agent'      => 'Mozilla/5.0 (compatible; MecspeScraper/1.0; research purposes)',
                'Accept'          => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'it-IT,it;q=0.9,en;q=0.8',
            ],
            'verify' => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function scrape(int $startPage = 1, int $endPage = 0, string $outputDir = ''): array
    {
        $lastPage = $endPage > 0 ? $endPage : $this->detectLastPage();

        $this->log("Scraping pages {$startPage}–{$lastPage} …");

        for ($page = $startPage; $page <= $lastPage; $page++) {
            $this->log("  [page {$page}/{$lastPage}] fetching list …");
            $cards = $this->fetchListPage($page);

            foreach ($cards as $i => $card) {
                $n = $i + 1;
                $total = count($cards);
                $this->log("    [{$n}/{$total}] {$card['name']} …");
                $detail = $this->fetchDetailPage($card['url']);
                $this->exhibitors[] = array_merge($card, $detail);
                usleep(self::DELAY_MS * 1000);
            }

            // Save incrementally after each page
            if ($outputDir !== '') {
                $this->saveJson($outputDir . '/mecspe_espositori_partial.json');
                $this->saveCsv($outputDir . '/mecspe_espositori_partial.csv');
            }
        }

        return $this->exhibitors;
    }

    public function saveJson(string $path): void
    {
        file_put_contents($path, json_encode($this->exhibitors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->log("JSON saved → {$path}");
    }

    public function saveCsv(string $path): void
    {
        if (empty($this->exhibitors)) {
            $this->log("No data to save.");
            return;
        }

        $fp = fopen($path, 'w');
        if ($fp === false) {
            $this->log("[WARNING] Impossibile scrivere il CSV (file in uso?), salto il CSV.");
            return;
        }
        fputs($fp, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens correctly

        $headers = [
            'name', 'sector', 'pavilion', 'stand',
            'description', 'services_products',
            'phone', 'email', 'address', 'website', 'url',
        ];
        fputcsv($fp, $headers, ';');

        foreach ($this->exhibitors as $ex) {
            fputcsv($fp, [
                $ex['name']              ?? '',
                $ex['sector']            ?? '',
                $ex['pavilion']          ?? '',
                $ex['stand']             ?? '',
                $ex['description']       ?? '',
                $ex['services_products'] ?? '',
                $ex['phone']             ?? '',
                $ex['email']             ?? '',
                $ex['address']           ?? '',
                $ex['website']           ?? '',
                $ex['url']               ?? '',
            ], ';');
        }

        fclose($fp);
        $this->log("CSV saved → {$path}");
    }

    // -------------------------------------------------------------------------
    // List page
    // -------------------------------------------------------------------------

    private function detectLastPage(): int
    {
        $html    = $this->get(self::LIST_URL);
        $crawler = new Crawler($html);

        $maxPage = 1;
        $crawler->filter('a[href*="?page="], a[href*="&page="]')->each(
            function (Crawler $a) use (&$maxPage) {
                if (preg_match('/[?&]page=(\d+)/', $a->attr('href') ?? '', $m)) {
                    $maxPage = max($maxPage, (int) $m[1]);
                }
            }
        );

        // Pagination numbers that may not be links (current page, last page text, etc.)
        $crawler->filter('.page-item, li.page-item')->each(function (Crawler $li) use (&$maxPage) {
            $text = trim($li->text(''));
            if (is_numeric($text)) {
                $maxPage = max($maxPage, (int) $text);
            }
        });

        $this->log("  Last page detected: {$maxPage}");
        return $maxPage;
    }

    private function fetchListPage(int $page): array
    {
        $url  = self::LIST_URL . ($page > 1 ? "?page={$page}" : '');
        $html = $this->get($url);
        return $this->parseListPage($html);
    }

    /**
     * Page structure:
     *   <div class="category-box …">
     *     <div class="row">
     *       <!-- mobile duplicate: d-md-none -->
     *       <div class="d-md-none …"> <a href="…"><h4>NAME</h4></a> <h5>SECTOR</h5> </div>
     *       <!-- desktop (used as canonical): d-none d-md-block -->
     *       <div class="d-none d-md-block">
     *         <a href="…"><h4 class="category-title">NAME</h4></a>
     *         <h5 class="category-tags">SECTOR</h5>
     *       </div>
     *       <p class="block-text … truncated-text">snippet</p>
     *       <div class="hall-info"><span fw-bold>Padiglione:</span><span>Pad. 28</span></div>
     *       <div class="stand-info"><span fw-bold>Stand:</span><span>C02</span></div>
     *     </div>
     *   </div>
     */
    private function parseListPage(string $html): array
    {
        $crawler = new Crawler($html);
        $cards   = [];

        $crawler->filter('div.category-box')->each(function (Crawler $box) use (&$cards) {
            // Use the desktop block (d-none d-md-block) to avoid duplicates.
            $desktopBlock = $box->filter('div.d-none.d-md-block');
            if ($desktopBlock->count() === 0) {
                return; // skip malformed cards
            }

            // Company name & detail URL
            $nameLink = $desktopBlock->filter('a[href*="/portale/it/"]')->first();
            if ($nameLink->count() === 0) {
                return;
            }
            $name      = trim($nameLink->filter('h4')->first()->text(''));
            $detailUrl = $this->absoluteUrl($nameLink->attr('href'));

            // Sector
            $sector = trim($desktopBlock->filter('h5')->first()->text(''));

            // Description snippet
            $snippet   = '';
            $snippetEl = $box->filter('p.truncated-text, p.block-text');
            if ($snippetEl->count() > 0) {
                $snippet = trim($snippetEl->first()->text(''));
            }

            // Pavilion
            $pavilion = '';
            $hallEl   = $box->filter('div.hall-info');
            if ($hallEl->count() > 0) {
                $spans = $hallEl->filter('span');
                if ($spans->count() >= 2) {
                    $pavilion = trim($spans->last()->text(''));
                }
            }

            // Stand
            $stand   = '';
            $standEl = $box->filter('div.stand-info');
            if ($standEl->count() > 0) {
                $spans = $standEl->filter('span');
                if ($spans->count() >= 2) {
                    $stand = trim($spans->last()->text(''));
                }
            }

            $cards[] = [
                'name'     => $name,
                'sector'   => $sector,
                'pavilion' => $pavilion,
                'stand'    => $stand,
                'snippet'  => $snippet,
                'url'      => $detailUrl,
            ];
        });

        return $cards;
    }

    // -------------------------------------------------------------------------
    // Detail page
    // -------------------------------------------------------------------------

    private function fetchDetailPage(string $url): array
    {
        try {
            $html = $this->get($url);
            return $this->parseDetailPage($html);
        } catch (\Throwable $e) {
            $this->log("    ERROR fetching detail: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Key detail page elements:
     *
     *   data-store="…JSON…"  → email, full_address (phone excluded: loaded via login only)
     *   .tipi-readmore p.section-body  → description
     *   #servizi span.tipi-tag         → services & products (comma list)
     *   #retailWebsite a[href]         → company website
     *
     * NOTE: phone is intentionally left empty — the real number is loaded
     * dynamically by Livewire after authentication and is not in the static HTML.
     */
    private function parseDetailPage(string $html): array
    {
        $crawler = new Crawler($html);

        $data = [
            'description'       => '',
            'services_products' => '',
            'phone'             => 'numero mancante',
            'email'             => 'mail mancante',
            'address'           => '',
            'website'           => '',
        ];

        // --- Contact data from JSON store (email + address only) ---
        if (preg_match('/data-store="([^"]+)"/', $html, $m)) {
            $json    = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $store   = json_decode($json, true) ?? [];
            $email   = trim($store['email'] ?? '');
            $data['email']   = $email !== '' ? $email : 'mail mancante';
            $data['address'] = $store['full_address'] ?? $store['addressLabel'] ?? '';
        }

        // Fallback: mailto link
        if ($data['email'] === 'mail mancante') {
            $crawler->filter('a[href^="mailto:"]')->each(function (Crawler $a) use (&$data) {
                if ($data['email'] === 'mail mancante') {
                    $data['email'] = str_replace('mailto:', '', $a->attr('href'));
                }
            });
        }

        // Fallback: tel link
        if ($data['phone'] === '') {
            $crawler->filter('a[href^="tel:"]')->each(function (Crawler $a) use (&$data) {
                if ($data['phone'] === '') {
                    $data['phone'] = str_replace('tel:', '', $a->attr('href'));
                }
            });
        }

        // --- Description ---
        // Structure: <div class="tipi-readmore"><p class="section-body">...</p></div>
        $descEl = $crawler->filter('.tipi-readmore p.section-body, .tipi-readmore p');
        if ($descEl->count() > 0) {
            $data['description'] = trim($descEl->first()->text(''));
        }

        // --- Services & Products ---
        // Structure: <div id="servizi">…<span class="tipi-tag">Tag</span>…</div>
        $tags = [];
        $crawler->filter('#servizi span.tipi-tag, [data-anchor="Servizi"] span.tipi-tag')->each(
            function (Crawler $span) use (&$tags) {
                $t = trim($span->text(''));
                if ($t !== '') {
                    $tags[] = $t;
                }
            }
        );
        $data['services_products'] = implode(', ', $tags);

        // --- Website ---
        // Structure: <div id="retailWebsite">…<a href="…" rel="nofollow">…</a>
        $websiteEl = $crawler->filter('#retailWebsite a[href]');
        if ($websiteEl->count() > 0) {
            $data['website'] = $websiteEl->first()->attr('href');
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // HTTP helper
    // -------------------------------------------------------------------------

    private function get(string $url): string
    {
        $attempt = 0;
        $delay   = 1;

        while (true) {
            try {
                $response = $this->client->get($url);
                return (string) $response->getBody();
            } catch (RequestException $e) {
                $attempt++;
                if ($attempt >= self::MAX_RETRIES) {
                    throw $e;
                }
                $this->log("    Retry {$attempt} for {$url} (wait {$delay}s) …");
                sleep($delay);
                $delay *= 2;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    private function absoluteUrl(string $href): string
    {
        if (str_starts_with($href, 'http')) {
            return $href;
        }
        return self::BASE_URL . ($href[0] === '/' ? '' : '/') . $href;
    }

    private function log(string $msg): void
    {
        if ($this->verbose) {
            echo $msg . PHP_EOL;
        }
    }
}
