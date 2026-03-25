<?php
/**
 * Plugin Name:  MECSPE Prodotti
 * Plugin URI:   https://github.com/ubska/mecspe-scraper
 * Description:  Visualizza i prodotti MECSPE con filtri laterali e superiori.
 * Version:      1.1.0
 * Author:       MECSPE Scraper
 * Text Domain:  mecspe-plugin
 * License:      GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MECSPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MECSPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MECSPE_POST_TYPE',  'prodotti' );

/* =========================================================
   1. TASSONOMIE  (aggiuntive, se non già registrate dallo scraper)
   ========================================================= */
add_action( 'init', 'mecspe_register_taxonomies', 20 );
function mecspe_register_taxonomies() {
    if ( ! taxonomy_exists( 'mecspe_categoria' ) ) {
        register_taxonomy( 'mecspe_categoria', MECSPE_POST_TYPE, [
            'labels'       => [
                'name'          => 'Categorie MECSPE',
                'singular_name' => 'Categoria',
                'all_items'     => 'Tutte le categorie',
            ],
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite'      => [ 'slug' => 'mecspe-categoria' ],
        ] );
    }
    if ( ! taxonomy_exists( 'mecspe_padiglione' ) ) {
        register_taxonomy( 'mecspe_padiglione', MECSPE_POST_TYPE, [
            'labels'       => [
                'name'          => 'Padiglioni',
                'singular_name' => 'Padiglione',
                'all_items'     => 'Tutti i padiglioni',
            ],
            'hierarchical' => false,
            'show_in_rest' => true,
            'rewrite'      => [ 'slug' => 'mecspe-padiglione' ],
        ] );
    }
}

/* =========================================================
   2. ASSETS
   ========================================================= */
add_action( 'wp_enqueue_scripts', 'mecspe_enqueue_assets' );
function mecspe_enqueue_assets() {
    wp_enqueue_style(
        'mecspe-style',
        MECSPE_PLUGIN_URL . 'assets/css/style.css',
        [], '1.1.0'
    );
    wp_enqueue_script(
        'mecspe-filters',
        MECSPE_PLUGIN_URL . 'assets/js/filters.js',
        [ 'jquery' ], '1.1.0', true
    );
    wp_localize_script( 'mecspe-filters', 'MecspeAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'mecspe_filter_nonce' ),
        'strings' => [
            'found_singular' => 'prodotto trovato',
            'found_plural'   => 'prodotti trovati',
            'load_more'      => 'Carica altri',
            'loading'        => 'Caricamento…',
            'error'          => 'Errore nel caricamento. Riprova.',
        ],
    ] );
}

/* =========================================================
   3. SHORTCODE  [mecspe_prodotti]
   ========================================================= */
add_shortcode( 'mecspe_prodotti', 'mecspe_shortcode' );
/* alias legacy */
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

    /* Raccogli filtri meta dai campi ACF del truck */
    $meta_filters = mecspe_get_meta_filters();

    $args  = mecspe_build_query_args( $per_page );
    $query = new WP_Query( $args );

    ?>
    <div class="mecspe-wrap" id="mecspe-wrap">

        <!-- ════ TOP BAR ════ -->
        <div class="mecspe-topbar">

            <div class="mecspe-search-wrap">
                <span class="mecspe-search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </span>
                <input type="text" id="mecspe-search"
                       placeholder="Cerca prodotto o espositore…"
                       value="<?php echo esc_attr( $_GET['mecspe_s'] ?? '' ); ?>"
                       autocomplete="off">
            </div>

            <div class="mecspe-topbar-controls">
                <span class="mecspe-results-count" id="mecspe-count">
                    <?php echo $query->found_posts; ?> prodotti trovati
                </span>
                <div class="mecspe-topbar-right">
                    <label class="mecspe-label-inline" for="mecspe-orderby">Ordina:</label>
                    <select id="mecspe-orderby" class="mecspe-select">
                        <option value="title-ASC"  <?php selected( ($_GET['mecspe_order'] ?? 'title-ASC'), 'title-ASC' ); ?>>A &ndash; Z</option>
                        <option value="title-DESC" <?php selected( ($_GET['mecspe_order'] ?? ''), 'title-DESC' ); ?>>Z &ndash; A</option>
                        <option value="date-DESC"  <?php selected( ($_GET['mecspe_order'] ?? ''), 'date-DESC' ); ?>>Più recenti</option>
                    </select>
                    <button class="mecspe-btn-toggle-sidebar" id="mecspe-toggle-sidebar">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="4" y1="6" x2="20" y2="6"/>
                            <line x1="8" y1="12" x2="20" y2="12"/>
                            <line x1="12" y1="18" x2="20" y2="18"/>
                        </svg>
                        Filtri
                        <span class="mecspe-filter-badge" id="mecspe-filter-badge" style="display:none">0</span>
                    </button>
                </div>
            </div>

        </div><!-- /.mecspe-topbar -->

        <!-- ════ LAYOUT ════ -->
        <div class="mecspe-layout" id="mecspe-layout">

            <!-- ── SIDEBAR ── -->
            <aside class="mecspe-sidebar" id="mecspe-sidebar">
                <div class="mecspe-sidebar-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                        </svg>
                        Filtra per
                    </h3>
                    <button class="mecspe-reset-btn" id="mecspe-reset">Azzera tutto</button>
                </div>

                <div id="mecspe-active-filters"></div>

                <!-- Filtro KM -->
                <div class="mecspe-filter-group">
                    <button class="mecspe-filter-group-toggle" aria-expanded="true">
                        KM percorsi
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="mecspe-filter-options">
                        <div class="mecspe-km-range">
                            <input type="number" id="mecspe-km-min" class="mecspe-km-input" placeholder="Min KM" min="0" step="10000" value="<?php echo esc_attr( $_GET['mecspe_km_min'] ?? '' ); ?>">
                            <span>—</span>
                            <input type="number" id="mecspe-km-max" class="mecspe-km-input" placeholder="Max KM" min="0" step="10000" value="<?php echo esc_attr( $_GET['mecspe_km_max'] ?? '' ); ?>">
                        </div>
                    </div>
                </div>

                <?php foreach ( $meta_filters as $meta_key => $filter ) :
                    if ( empty( $filter['options'] ) ) continue;
                    $active = (array)( $_GET[ 'mf_' . $meta_key ] ?? [] );
                ?>
                <div class="mecspe-filter-group">
                    <button class="mecspe-filter-group-toggle" aria-expanded="true">
                        <?php echo esc_html( $filter['label'] ); ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="mecspe-filter-options">
                        <?php foreach ( $filter['options'] as $val ) : ?>
                        <label class="mecspe-checkbox-label">
                            <input type="checkbox"
                                   class="mecspe-filter-check"
                                   data-taxonomy="mf_<?php echo esc_attr( $meta_key ); ?>"
                                   data-label="<?php echo esc_attr( $val ); ?>"
                                   value="<?php echo esc_attr( $val ); ?>"
                                   <?php checked( in_array( $val, $active ) ); ?>>
                            <span class="mecspe-checkbox-custom"></span>
                            <span class="mecspe-checkbox-text"><?php echo esc_html( $val ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            </aside><!-- /.mecspe-sidebar -->

            <!-- ── MAIN ── -->
            <div class="mecspe-main" id="mecspe-main">
                <div class="mecspe-grid" id="mecspe-grid">
                    <?php mecspe_render_cards( $query ); ?>
                </div>

                <div class="mecspe-pagination" id="mecspe-pagination"
                     style="<?php echo $query->max_num_pages <= 1 ? 'display:none' : ''; ?>">
                    <button class="mecspe-load-more" id="mecspe-load-more"
                            data-page="1"
                            data-max="<?php echo (int) $query->max_num_pages; ?>">
                        Carica altri
                    </button>
                </div>
            </div><!-- /.mecspe-main -->

        </div><!-- /.mecspe-layout -->

        <!-- overlay mobile -->
        <div class="mecspe-overlay" id="mecspe-overlay"></div>

    </div><!-- /.mecspe-wrap -->
    <?php
    wp_reset_postdata();
}

/* =========================================================
   5. CARD SINGOLA
   ========================================================= */
function mecspe_render_cards( WP_Query $query ) {
    if ( ! $query->have_posts() ) {
        echo '<div class="mecspe-no-results">'
           . '<p>Nessun prodotto trovato con i filtri selezionati.</p>'
           . '<p>Prova a rimuovere qualche filtro.</p>'
           . '</div>';
        return;
    }

    while ( $query->have_posts() ) {
        $query->the_post();
        $id = get_the_ID();

        /* Leggi campi ACF del camion */
        $acf = function_exists( 'get_field' );
        $km         = $acf ? get_field( 'km_percorsi', $id )         : get_post_meta( $id, 'km_percorsi', true );
        $anno       = $acf ? get_field( 'prima_immatricolazione', $id): get_post_meta( $id, 'prima_immatricolazione', true );
        $cavalli    = $acf ? get_field( 'cavalli', $id )              : get_post_meta( $id, 'cavalli', true );
        $prezzo     = $acf ? get_field( 'prezzo', $id )               : get_post_meta( $id, 'prezzo', true );
        $trattativa = $acf ? get_field( 'trattativa_in_sede', $id )   : get_post_meta( $id, 'trattativa_in_sede', true );
        $pronto     = $acf ? get_field( 'veicolo_pronto', $id )       : get_post_meta( $id, 'veicolo_pronto', true );

        /* Marca dal repeater ACF */
        $marca = '';
        if ( $acf ) {
            $marche = get_field( 'marche', $id );
            if ( ! empty( $marche ) && is_array( $marche ) )
                $marca = $marche[0]['testo'] ?? '';
        }

        /* Tag come badge (es. "Usato CGT Trucks") */
        $tags = get_the_terms( $id, 'post_tag' );
        $tax_badges = ( $tags && ! is_wp_error( $tags ) ) ? wp_list_pluck( $tags, 'name' ) : [];
        ?>
        <article class="mecspe-card" id="post-<?php echo $id; ?>">

            <a href="<?php the_permalink(); ?>" class="mecspe-card-thumb-link">
                <div class="mecspe-card-thumb">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'medium', [ 'class' => 'mecspe-card-img', 'loading' => 'lazy' ] ); ?>
                    <?php else : ?>
                        <div class="mecspe-card-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
            </a>

            <div class="mecspe-card-body">
                <?php if ( $marca ) : ?>
                <div class="mecspe-card-brand"><?php echo esc_html( $marca ); ?></div>
                <?php endif; ?>

                <h2 class="mecspe-card-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h2>

                <?php if ( ! empty( $tax_badges ) ) : ?>
                <div class="mecspe-card-tags">
                    <?php foreach ( array_slice( $tax_badges, 0, 2 ) as $b ) : ?>
                    <span class="mecspe-tag"><?php echo esc_html( $b ); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <ul class="mecspe-card-specs">
                    <?php if ( $anno ) : ?>
                    <li><strong>Immatricolazione:</strong> <?php echo esc_html( $anno ); ?></li>
                    <?php endif; ?>
                    <?php if ( $km ) : ?>
                    <li><strong>KM:</strong> <?php echo esc_html( number_format( (float)str_replace('.','',str_replace(',','',$km)), 0, ',', '.' ) ); ?> km</li>
                    <?php endif; ?>
                    <?php if ( $cavalli ) : ?>
                    <li><strong>Cavalli:</strong> <?php echo esc_html( $cavalli ); ?> CV</li>
                    <?php endif; ?>
                    <?php if ( $pronto ) : ?>
                    <li>&#10003; Veicolo pronto</li>
                    <?php endif; ?>
                </ul>

                <div class="mecspe-card-price">
                    <?php if ( $trattativa ) : ?>
                        Trattativa in sede
                    <?php elseif ( $prezzo ) : ?>
                        &euro; <?php echo esc_html( number_format( (float)str_replace('.','',str_replace(',','',$prezzo)), 0, ',', '.' ) ); ?>
                        <small>+ IVA</small>
                    <?php else : ?>
                        <span style="color:#999;font-size:13px">Contattaci per il prezzo</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mecspe-card-footer">
                <a href="<?php the_permalink(); ?>" class="mecspe-btn-dettaglio">Scopri di più</a>
            </div>

        </article>
        <?php
    }
    wp_reset_postdata();
}

/* =========================================================
   6. HELPER: filtri basati su meta ACF del truck
   ========================================================= */
function mecspe_get_meta_filters(): array {
    /* Gruppi: meta_key => label
       Per i repeater ACF si usa il sub-campo _0_testo */
    $groups = [
        'marche_0_testo'        => 'Marca',
        'cambi_0_testo'         => 'Cambio',
        'cabine_0_testo'        => 'Cabina',
        'allestimenti_0_testo'  => 'Allestimento',
        'tipi_offerta_0_testo'  => 'Tipo offerta',
        'prima_immatricolazione'=> 'Anno immatricolazione',
    ];

    global $wpdb;
    $result = [];
    foreach ( $groups as $meta_key => $label ) {
        $options = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s
               AND p.post_type = %s
               AND p.post_status = 'publish'
               AND pm.meta_value != ''
             ORDER BY pm.meta_value ASC
             LIMIT 100",
            $meta_key, MECSPE_POST_TYPE
        ) );

        /* Per anno: estrai solo l'anno (ultime 4 cifre) */
        if ( $meta_key === 'prima_immatricolazione' ) {
            $years = [];
            foreach ( $options as $v ) {
                if ( preg_match( '/\d{4}/', $v, $m ) ) $years[] = $m[0];
            }
            $options = array_values( array_unique( $years ) );
            rsort( $options ); // anni decrescenti
        }

        if ( ! empty( $options ) ) {
            $result[ $meta_key ] = [ 'label' => $label, 'options' => $options ];
        }
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

    $s = sanitize_text_field( $_REQUEST['mecspe_s'] ?? '' );
    if ( $s ) $args['s'] = $s;

    $order_raw = sanitize_text_field( $_REQUEST['mecspe_order'] ?? 'title-ASC' );
    [ $orderby, $order ] = array_pad( explode( '-', $order_raw, 2 ), 2, 'ASC' );
    $args['orderby'] = in_array( $orderby, [ 'title', 'date' ], true ) ? $orderby : 'title';
    $args['order']   = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';

    /* Meta query dai filtri ACF */
    $meta_query = [ 'relation' => 'AND' ];

    /* Filtri checkbox (mf_<meta_key>) */
    $meta_groups = array_keys( mecspe_get_meta_filters() );
    foreach ( $meta_groups as $meta_key ) {
        $values = array_filter( array_map( 'sanitize_text_field', (array)( $_REQUEST[ 'mf_' . $meta_key ] ?? [] ) ) );
        if ( empty( $values ) ) continue;

        if ( $meta_key === 'prima_immatricolazione' ) {
            /* Anno: LIKE '%YYYY' per ogni anno selezionato */
            $year_group = [ 'relation' => 'OR' ];
            foreach ( $values as $year ) {
                $year_group[] = [ 'key' => $meta_key, 'value' => $year, 'compare' => 'LIKE' ];
            }
            $meta_query[] = $year_group;
        } else {
            $meta_query[] = [ 'key' => $meta_key, 'value' => $values, 'compare' => 'IN' ];
        }
    }

    /* Filtro range KM */
    $km_min = (int)( $_REQUEST['mecspe_km_min'] ?? 0 );
    $km_max = (int)( $_REQUEST['mecspe_km_max'] ?? 0 );
    if ( $km_min > 0 || $km_max > 0 ) {
        if ( $km_min > 0 && $km_max > 0 ) {
            $meta_query[] = [ 'key' => 'km_percorsi', 'value' => [ $km_min, $km_max ], 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ];
        } elseif ( $km_min > 0 ) {
            $meta_query[] = [ 'key' => 'km_percorsi', 'value' => $km_min, 'compare' => '>=', 'type' => 'NUMERIC' ];
        } else {
            $meta_query[] = [ 'key' => 'km_percorsi', 'value' => $km_max, 'compare' => '<=', 'type' => 'NUMERIC' ];
        }
    }

    if ( count( $meta_query ) > 1 ) $args['meta_query'] = $meta_query;

    return $args;
}

/* =========================================================
   8. AJAX
   ========================================================= */
add_action( 'wp_ajax_mecspe_filter',        'mecspe_ajax_filter' );
add_action( 'wp_ajax_nopriv_mecspe_filter', 'mecspe_ajax_filter' );
function mecspe_ajax_filter() {
    check_ajax_referer( 'mecspe_filter_nonce', 'nonce' );

    $per_page = 12;
    $paged    = max( 1, (int)( $_REQUEST['paged'] ?? 1 ) );
    $query    = new WP_Query( mecspe_build_query_args( $per_page, $paged ) );

    ob_start();
    mecspe_render_cards( $query );

    wp_send_json_success( [
        'html'      => ob_get_clean(),
        'found'     => $query->found_posts,
        'max_pages' => $query->max_num_pages,
        'paged'     => $paged,
    ] );
}

/* =========================================================
   9. DEBUG SHORTCODE  [mecspe_debug]  (solo admin)
   ========================================================= */
add_shortcode( 'mecspe_debug', function() {
    if ( ! current_user_can( 'manage_options' ) ) return '';

    $post = get_posts( [ 'post_type' => MECSPE_POST_TYPE, 'posts_per_page' => 1 ] );
    if ( empty( $post ) ) return '<p>Nessun post trovato per il tipo: <strong>' . MECSPE_POST_TYPE . '</strong></p>';

    $id   = $post[0]->ID;
    $meta = get_post_meta( $id );
    $taxs = get_object_taxonomies( MECSPE_POST_TYPE );

    ob_start(); ?>
    <div style="background:#f5f5f5;border:1px solid #ccc;padding:16px;font-family:monospace;font-size:13px;margin:20px 0">
        <strong>DEBUG — Post ID <?php echo $id; ?> (<?php echo esc_html( $post[0]->post_title ); ?>)</strong>
        <hr style="margin:10px 0">
        <strong>META KEYS disponibili:</strong><br>
        <?php foreach ( $meta as $key => $val ) : ?>
            <span style="color:#006"><?php echo esc_html( $key ); ?></span>
            = <?php echo esc_html( is_array($val) ? $val[0] : $val ); ?><br>
        <?php endforeach; ?>
        <hr style="margin:10px 0">
        <strong>TASSONOMIE sul CPT:</strong><br>
        <?php foreach ( $taxs as $t ) : ?>
            <?php $terms = get_the_terms( $id, $t ); ?>
            <span style="color:#006"><?php echo esc_html( $t ); ?></span>
            = <?php echo $terms && !is_wp_error($terms) ? esc_html( implode(', ', wp_list_pluck($terms,'name')) ) : '(nessuno)'; ?><br>
        <?php endforeach; ?>
    </div>
    <?php return ob_get_clean();
} );

/* =========================================================
   10. FLUSH REWRITE
   ========================================================= */
register_activation_hook( __FILE__, function () {
    mecspe_register_taxonomies();
    flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
