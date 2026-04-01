<?php
/**
 * Plugin Name:  MECSPE Prodotti
 * Plugin URI:   https://github.com/ubska/mecspe-scraper
 * Description:  Visualizza i veicoli usati con filtri dropdown, sidebar e card orizzontali.
 * Version:      2.0.0
 * Author:       MECSPE Scraper
 * Text Domain:  mecspe-plugin
 * License:      GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MECSPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MECSPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MECSPE_POST_TYPE',  'prodotti' );

/* =========================================================
   1. TASSONOMIE AGGIUNTIVE (se non già registrate)
   ========================================================= */
add_action( 'init', 'mecspe_register_taxonomies', 20 );
function mecspe_register_taxonomies() {
    foreach ( [
        'mecspe_categoria'   => 'Categorie MECSPE',
        'mecspe_padiglione'  => 'Padiglioni',
    ] as $slug => $label ) {
        if ( ! taxonomy_exists( $slug ) ) {
            register_taxonomy( $slug, MECSPE_POST_TYPE, [
                'label'        => $label,
                'hierarchical' => true,
                'show_in_rest' => true,
                'rewrite'      => [ 'slug' => $slug ],
            ] );
        }
    }
}

/* =========================================================
   2. ASSETS
   ========================================================= */
add_action( 'wp_enqueue_scripts', 'mecspe_enqueue_assets' );
function mecspe_enqueue_assets() {
    wp_enqueue_style(  'mecspe-style',   MECSPE_PLUGIN_URL . 'assets/css/style.css',   [], '2.0.0' );
    wp_enqueue_script( 'mecspe-filters', MECSPE_PLUGIN_URL . 'assets/js/filters.js', ['jquery'], '2.0.0', true );
    wp_localize_script( 'mecspe-filters', 'MecspeAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'mecspe_filter_nonce' ),
    ] );
}

/* =========================================================
   3. SHORTCODE  [mecspe_prodotti]
   ========================================================= */
add_shortcode( 'mecspe_prodotti',   'mecspe_shortcode' );
add_shortcode( 'mecspe_espositori', 'mecspe_shortcode' );
function mecspe_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'per_page' => 12 ], $atts );
    ob_start();
    mecspe_render_archive( (int) $atts['per_page'] );
    return ob_get_clean();
}

/* =========================================================
   4. RENDER ARCHIVIO
   ========================================================= */
function mecspe_render_archive( int $per_page = 12 ) {
    $meta_filters = mecspe_get_meta_filters();
    $args  = mecspe_build_query_args( $per_page );
    $query = new WP_Query( $args );

    /* Opzioni per i dropdown in cima */
    $dd_marca  = mecspe_get_meta_options( 'marche_0_testo' );
    $dd_anno   = mecspe_get_year_options( 'prima_immatricolazione' );
    $dd_cambio = mecspe_get_meta_options( 'cambi_0_testo' );
    $dd_allest = mecspe_get_meta_options( 'allestimenti_0_testo' );

    $sel_marca  = sanitize_text_field( $_GET['dd_marca']  ?? '' );
    $sel_anno   = sanitize_text_field( $_GET['dd_anno']   ?? '' );
    $sel_cambio = sanitize_text_field( $_GET['dd_cambio'] ?? '' );
    $sel_allest = sanitize_text_field( $_GET['dd_allest'] ?? '' );
    ?>
    <div class="mecspe-wrap" id="mecspe-wrap">

        <!-- ══ BARRA DROPDOWN IN CIMA ══ -->
        <div class="mecspe-dd-bar">
            <select class="mecspe-dd" id="dd_marca">
                <option value="">Produttore</option>
                <?php foreach ( $dd_marca as $v ) : ?>
                <option value="<?php echo esc_attr($v); ?>" <?php selected($sel_marca,$v); ?>><?php echo esc_html($v); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="mecspe-dd" id="dd_anno">
                <option value="">Immatricolazione</option>
                <?php foreach ( $dd_anno as $v ) : ?>
                <option value="<?php echo esc_attr($v); ?>" <?php selected($sel_anno,$v); ?>><?php echo esc_html($v); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="mecspe-dd" id="dd_cambio">
                <option value="">Cambio</option>
                <?php foreach ( $dd_cambio as $v ) : ?>
                <option value="<?php echo esc_attr($v); ?>" <?php selected($sel_cambio,$v); ?>><?php echo esc_html($v); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="mecspe-dd" id="dd_allest">
                <option value="">Allestimento</option>
                <?php foreach ( $dd_allest as $v ) : ?>
                <option value="<?php echo esc_attr($v); ?>" <?php selected($sel_allest,$v); ?>><?php echo esc_html($v); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="mecspe-dd-btn" id="mecspe-dd-search">
                &#128269; RICERCA
            </button>
        </div>

        <h2 class="mecspe-page-title">Risultato della ricerca</h2>

        <!-- ══ LAYOUT ══ -->
        <div class="mecspe-layout">

            <!-- SIDEBAR -->
            <aside class="mecspe-sidebar" id="mecspe-sidebar">
                <div class="mecspe-sidebar-title">Filtra per</div>

                <!-- Range KM -->
                <div class="mecspe-acc-item">
                    <button class="mecspe-acc-toggle" aria-expanded="false">
                        <span>&#8250;</span> KM percorsi
                    </button>
                    <div class="mecspe-acc-body" style="display:none">
                        <div class="mecspe-km-range">
                            <input type="number" id="mecspe-km-min" class="mecspe-km-input" placeholder="Min" min="0" step="10000" value="<?php echo esc_attr($_GET['mecspe_km_min'] ?? ''); ?>">
                            <span>—</span>
                            <input type="number" id="mecspe-km-max" class="mecspe-km-input" placeholder="Max" min="0" step="10000" value="<?php echo esc_attr($_GET['mecspe_km_max'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <?php foreach ( $meta_filters as $meta_key => $filter ) :
                    if ( empty( $filter['options'] ) ) continue;
                    $active = (array)( $_GET[ 'mf_' . $meta_key ] ?? [] );
                ?>
                <div class="mecspe-acc-item">
                    <button class="mecspe-acc-toggle" aria-expanded="false">
                        <span>&#8250;</span> <?php echo esc_html( $filter['label'] ); ?>
                    </button>
                    <div class="mecspe-acc-body" style="display:none">
                        <?php foreach ( $filter['options'] as $val ) : ?>
                        <label class="mecspe-cb-label">
                            <input type="checkbox"
                                   class="mecspe-filter-check"
                                   data-taxonomy="mf_<?php echo esc_attr($meta_key); ?>"
                                   data-label="<?php echo esc_attr($val); ?>"
                                   value="<?php echo esc_attr($val); ?>"
                                   <?php checked( in_array($val, $active) ); ?>>
                            <?php echo esc_html($val); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            </aside>

            <!-- MAIN -->
            <div class="mecspe-main" id="mecspe-main">

                <!-- Barra risultati -->
                <div class="mecspe-results-bar">
                    <button class="mecspe-cancella-btn" id="mecspe-reset">&#10005; CANCELLA FILTRI</button>
                    <span class="mecspe-count" id="mecspe-count">Risultati: <?php echo $query->found_posts; ?></span>
                    <select id="mecspe-orderby" class="mecspe-select-sort">
                        <option value="title-ASC"  <?php selected(($_GET['mecspe_order'] ?? 'title-ASC'),'title-ASC'); ?>>Ordina per: A – Z</option>
                        <option value="title-DESC" <?php selected(($_GET['mecspe_order'] ?? ''),'title-DESC'); ?>>Ordina per: Z – A</option>
                        <option value="date-DESC"  <?php selected(($_GET['mecspe_order'] ?? ''),'date-DESC'); ?>>Più recenti</option>
                    </select>
                </div>

                <!-- Lista veicoli -->
                <div class="mecspe-list" id="mecspe-grid">
                    <?php mecspe_render_cards( $query ); ?>
                </div>

                <div class="mecspe-pagination" id="mecspe-pagination"
                     style="<?php echo $query->max_num_pages <= 1 ? 'display:none' : ''; ?>">
                    <button class="mecspe-load-more" id="mecspe-load-more"
                            data-page="1" data-max="<?php echo (int)$query->max_num_pages; ?>">
                        Carica altri
                    </button>
                </div>
            </div>
        </div>

        <div class="mecspe-overlay" id="mecspe-overlay"></div>
    </div>
    <?php
    wp_reset_postdata();
}

/* =========================================================
   5. CARD ORIZZONTALE
   ========================================================= */
function mecspe_render_cards( WP_Query $query ) {
    if ( ! $query->have_posts() ) {
        echo '<div class="mecspe-no-results"><p>Nessun veicolo trovato con i filtri selezionati.</p></div>';
        return;
    }
    while ( $query->have_posts() ) {
        $query->the_post();
        $id  = get_the_ID();
        $acf = function_exists('get_field');

        $modello    = $acf ? get_field('modello', $id)                 : get_post_meta($id,'modello',true);
        $km         = $acf ? get_field('km_percorsi', $id)             : get_post_meta($id,'km_percorsi',true);
        $anno_raw   = $acf ? get_field('prima_immatricolazione', $id)  : get_post_meta($id,'prima_immatricolazione',true);
        $cavalli    = $acf ? get_field('cavalli', $id)                 : get_post_meta($id,'cavalli',true);
        $prezzo     = $acf ? get_field('prezzo', $id)                  : get_post_meta($id,'prezzo',true);
        $trattativa = $acf ? get_field('trattativa_in_sede', $id)      : get_post_meta($id,'trattativa_in_sede',true);
        $pronto     = $acf ? get_field('veicolo_pronto', $id)          : get_post_meta($id,'veicolo_pronto',true);
        $targa      = $acf ? get_field('targa', $id)                   : get_post_meta($id,'targa',true);
        $cod        = $acf ? get_field('codice_interno', $id)          : get_post_meta($id,'codice_interno',true);

        /* Repeater: prendi primo valore testo */
        $marca    = mecspe_first_repeater( $id, 'marche',         'testo', $acf );
        $cambio   = mecspe_first_repeater( $id, 'cambi',          'testo', $acf );
        $motore   = mecspe_first_repeater( $id, 'motori',         'testo', $acf );
        $cabina   = mecspe_first_repeater( $id, 'cabine',         'testo', $acf );
        $allest   = mecspe_first_repeater( $id, 'allestimenti',   'testo', $acf );
        $offerta  = mecspe_first_repeater( $id, 'tipi_offerta',   'testo', $acf );

        /* Anno: estrai solo anno numerico */
        $anno = '';
        if ( $anno_raw && preg_match('/\d{4}/', $anno_raw, $m) ) $anno = $m[0];

        /* KM formattato */
        $km_fmt = $km ? number_format((float)preg_replace('/[^\d]/','',$km), 0, ',', '.') : '';

        /* Nome sopra immagine */
        $img_label = trim( ($marca ? $marca . ' ' : '') . ($modello ?: get_the_title()) );
        ?>
        <div class="mecspe-card">

            <!-- Colonna immagine -->
            <div class="mecspe-card-left">
                <div class="mecspe-card-img-label"><?php echo esc_html( strtoupper($img_label) ); ?></div>
                <a href="<?php the_permalink(); ?>" class="mecspe-card-img-wrap">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail('medium', ['class'=>'mecspe-card-img','loading'=>'lazy']); ?>
                    <?php else : ?>
                        <div class="mecspe-card-no-img">Nessuna foto</div>
                    <?php endif; ?>
                </a>
                <!-- Badge sotto immagine -->
                <div class="mecspe-card-badges">
                    <?php if ($km_fmt) : ?><span class="mecspe-badge">Km <?php echo esc_html($km_fmt); ?></span><?php endif; ?>
                    <?php if ($anno)   : ?><span class="mecspe-badge">Anno <?php echo esc_html($anno); ?></span><?php endif; ?>
                    <?php if ($motore) : ?><span class="mecspe-badge"><?php echo esc_html($motore); ?></span><?php endif; ?>
                </div>
                <!-- Prezzo / Trattativa -->
                <?php if ($trattativa) : ?>
                <div class="mecspe-card-trattativa">Trattativa Riservata</div>
                <?php elseif ($prezzo) : ?>
                <div class="mecspe-card-prezzo">
                    Tuo a <strong>&euro; <?php echo esc_html(number_format((float)preg_replace('/[^\d]/','',$prezzo),0,',','.')); ?></strong>
                </div>
                <?php endif; ?>
            </div>

            <!-- Colonna contenuto -->
            <div class="mecspe-card-right">
                <div class="mecspe-card-head">
                    <div>
                        <h2 class="mecspe-card-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            <?php if ($offerta) : ?><span class="mecspe-usato-badge"><?php echo esc_html($offerta); ?></span><?php endif; ?>
                        </h2>
                        <p class="mecspe-card-subtitle">
                            <?php echo esc_html( implode(' &nbsp;', array_filter([$cabina, $targa ? 'Rif: '.$targa : '', $cod ? 'Cod: '.$cod : ''])) ); ?>
                        </p>
                    </div>
                    <?php if ($prezzo && !$trattativa) : ?>
                    <div class="mecspe-card-price-top">&euro; <?php echo esc_html(number_format((float)preg_replace('/[^\d]/','',$prezzo),0,',','.')); ?></div>
                    <?php endif; ?>
                </div>

                <div class="mecspe-card-specs">
                    <?php if ($anno)   : ?><div class="mecspe-spec"><span class="mecspe-spec-icon">&#128197;</span> <?php echo esc_html($anno); ?></div><?php endif; ?>
                    <?php if ($cavalli): ?><div class="mecspe-spec"><span class="mecspe-spec-icon">&#9881;</span> <?php echo esc_html($cavalli); ?> CV</div><?php endif; ?>
                    <?php if ($km_fmt) : ?><div class="mecspe-spec"><span class="mecspe-spec-icon">&#128338;</span> <?php echo esc_html($km_fmt); ?> Km</div><?php endif; ?>
                    <?php if ($motore) : ?><div class="mecspe-spec"><span class="mecspe-spec-icon">&#8250;</span> <?php echo esc_html($motore); ?></div><?php endif; ?>
                    <?php if ($allest) : ?><div class="mecspe-spec"><span class="mecspe-spec-icon">&#8250;</span> <?php echo esc_html($allest); ?></div><?php endif; ?>
                    <?php if ($cambio) : ?><div class="mecspe-spec"><span class="mecspe-spec-icon">&#8250;</span> <?php echo esc_html($cambio); ?></div><?php endif; ?>
                    <?php if ($pronto) : ?><div class="mecspe-spec"><span class="mecspe-spec-icon">&#10003;</span> Veicolo pronto</div><?php endif; ?>
                </div>

                <div class="mecspe-card-footer">
                    <a href="<?php the_permalink(); ?>" class="mecspe-btn-dettagli">DETTAGLI</a>
                </div>
            </div>

        </div>
        <?php
    }
    wp_reset_postdata();
}

/* =========================================================
   6. HELPERS META
   ========================================================= */
function mecspe_first_repeater( int $id, string $field, string $subfield, bool $acf ): string {
    if ( $acf ) {
        $rows = get_field( $field, $id );
        return ( is_array($rows) && ! empty($rows) ) ? ( $rows[0][$subfield] ?? '' ) : '';
    }
    return get_post_meta( $id, $field . '_0_' . $subfield, true ) ?: '';
}

function mecspe_get_meta_options( string $meta_key ): array {
    global $wpdb;
    $rows = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish'
           AND pm.meta_value != '' ORDER BY pm.meta_value ASC LIMIT 200",
        $meta_key, MECSPE_POST_TYPE
    ) );
    return array_values( array_filter( $rows ) );
}

function mecspe_get_year_options( string $meta_key ): array {
    $values = mecspe_get_meta_options( $meta_key );
    $years  = [];
    foreach ( $values as $v ) {
        if ( preg_match('/\d{4}/', $v, $m) ) $years[] = $m[0];
    }
    $years = array_values( array_unique( $years ) );
    rsort( $years );
    return $years;
}

function mecspe_get_meta_filters(): array {
    $groups = [
        'marche_0_testo'          => 'Marca',
        'prima_immatricolazione'  => 'Anno immatricolazione',
        'cabine_0_testo'          => 'Cabina',
        'cambi_0_testo'           => 'Cambio',
        'allestimenti_0_testo'    => 'Allestimento',
        'tipi_offerta_0_testo'    => 'Tipo offerta',
        'motori_0_testo'          => 'Motore',
        'equipaggiamenti_0_testo' => 'Equipaggiamento',
        'pneumatici_0_testo'      => 'Pneumatici',
        'fender_laterali_0_testo' => 'Fender laterale',
        'elenco_spoiler_0_testo'  => 'Spoiler',
        'minigonne_0_testo'       => 'Minigonne',
    ];
    $result = [];
    foreach ( $groups as $key => $label ) {
        $options = ( $key === 'prima_immatricolazione' )
            ? mecspe_get_year_options( $key )
            : mecspe_get_meta_options( $key );
        if ( ! empty($options) ) $result[$key] = [ 'label' => $label, 'options' => $options ];
    }
    return $result;
}

/* =========================================================
   7. QUERY ARGS
   ========================================================= */
function mecspe_build_query_args( int $per_page, int $paged = 1 ): array {
    $args = [
        'post_type'      => MECSPE_POST_TYPE,
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'post_status'    => 'publish',
    ];

    $order_raw = sanitize_text_field( $_REQUEST['mecspe_order'] ?? 'title-ASC' );
    [ $orderby, $order ] = array_pad( explode('-', $order_raw, 2), 2, 'ASC' );
    $args['orderby'] = in_array($orderby, ['title','date'], true) ? $orderby : 'title';
    $args['order']   = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

    $meta_query = [ 'relation' => 'AND' ];

    /* Dropdown top bar */
    $dd_map = [
        'dd_marca'  => 'marche_0_testo',
        'dd_anno'   => 'prima_immatricolazione',
        'dd_cambio' => 'cambi_0_testo',
        'dd_allest' => 'allestimenti_0_testo',
    ];
    foreach ( $dd_map as $param => $meta_key ) {
        $val = sanitize_text_field( $_REQUEST[$param] ?? '' );
        if ( ! $val ) continue;
        if ( $param === 'dd_anno' ) {
            $meta_query[] = [ 'key' => $meta_key, 'value' => $val, 'compare' => 'LIKE' ];
        } else {
            $meta_query[] = [ 'key' => $meta_key, 'value' => $val, 'compare' => '=' ];
        }
    }

    /* Checkbox sidebar */
    foreach ( array_keys( mecspe_get_meta_filters() ) as $meta_key ) {
        $values = array_filter( array_map('sanitize_text_field', (array)( $_REQUEST['mf_'.$meta_key] ?? [] ) ) );
        if ( empty($values) ) continue;
        if ( $meta_key === 'prima_immatricolazione' ) {
            $g = [ 'relation' => 'OR' ];
            foreach ( $values as $y ) $g[] = [ 'key' => $meta_key, 'value' => $y, 'compare' => 'LIKE' ];
            $meta_query[] = $g;
        } else {
            $meta_query[] = [ 'key' => $meta_key, 'value' => $values, 'compare' => 'IN' ];
        }
    }

    /* Range KM */
    $km_min = (int)( $_REQUEST['mecspe_km_min'] ?? 0 );
    $km_max = (int)( $_REQUEST['mecspe_km_max'] ?? 0 );
    if ( $km_min > 0 && $km_max > 0 ) {
        $meta_query[] = [ 'key' => 'km_percorsi', 'value' => [$km_min,$km_max], 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ];
    } elseif ( $km_min > 0 ) {
        $meta_query[] = [ 'key' => 'km_percorsi', 'value' => $km_min, 'compare' => '>=', 'type' => 'NUMERIC' ];
    } elseif ( $km_max > 0 ) {
        $meta_query[] = [ 'key' => 'km_percorsi', 'value' => $km_max, 'compare' => '<=', 'type' => 'NUMERIC' ];
    }

    if ( count($meta_query) > 1 ) $args['meta_query'] = $meta_query;

    return $args;
}

/* =========================================================
   8. AJAX
   ========================================================= */
add_action( 'wp_ajax_mecspe_filter',        'mecspe_ajax_filter' );
add_action( 'wp_ajax_nopriv_mecspe_filter', 'mecspe_ajax_filter' );
function mecspe_ajax_filter() {
    check_ajax_referer( 'mecspe_filter_nonce', 'nonce' );
    $query = new WP_Query( mecspe_build_query_args( 12, max(1,(int)($_REQUEST['paged']??1)) ) );
    ob_start();
    mecspe_render_cards( $query );
    wp_send_json_success([
        'html'      => ob_get_clean(),
        'found'     => $query->found_posts,
        'max_pages' => $query->max_num_pages,
        'paged'     => (int)($_REQUEST['paged']??1),
    ]);
}

/* =========================================================
   9. FLUSH REWRITE
   ========================================================= */
register_activation_hook( __FILE__, function() { mecspe_register_taxonomies(); flush_rewrite_rules(); } );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
