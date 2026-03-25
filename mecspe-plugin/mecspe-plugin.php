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

    /* Raccogli tutte le tassonomie registrate su questo CPT */
    $tax_filters = mecspe_get_tax_filters();

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

                <?php foreach ( $tax_filters as $tax_slug => $tax_data ) :
                    if ( empty( $tax_data['terms'] ) ) continue;
                    $active = (array)( $_GET[ $tax_slug ] ?? [] );
                ?>
                <div class="mecspe-filter-group">
                    <button class="mecspe-filter-group-toggle" aria-expanded="true">
                        <?php echo esc_html( $tax_data['label'] ); ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="mecspe-filter-options">
                        <?php foreach ( $tax_data['terms'] as $term ) : ?>
                        <label class="mecspe-checkbox-label">
                            <input type="checkbox"
                                   class="mecspe-filter-check"
                                   data-taxonomy="<?php echo esc_attr( $tax_slug ); ?>"
                                   data-label="<?php echo esc_attr( $term->name ); ?>"
                                   value="<?php echo esc_attr( $term->slug ); ?>"
                                   <?php checked( in_array( $term->slug, $active ) ); ?>>
                            <span class="mecspe-checkbox-custom"></span>
                            <span class="mecspe-checkbox-text"><?php echo esc_html( $term->name ); ?></span>
                            <span class="mecspe-term-count"><?php echo $term->count; ?></span>
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
           . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
           . '<p>Nessun prodotto trovato con i filtri selezionati.</p>'
           . '<p><small>Prova a rimuovere qualche filtro.</small></p>'
           . '</div>';
        return;
    }

    while ( $query->have_posts() ) {
        $query->the_post();
        $id   = get_the_ID();

        /* Recupera tassonomie */
        $tax_filters = mecspe_get_tax_filters();
        $tax_badges  = [];
        foreach ( $tax_filters as $tax_slug => $tax_data ) {
            $terms = get_the_terms( $id, $tax_slug );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $tax_badges = array_merge( $tax_badges, wp_list_pluck( $terms, 'name' ) );
            }
        }

        /* Meta comuni */
        $sito     = get_post_meta( $id, '_mecspe_sito_web', true )
                 ?: get_post_meta( $id, 'sito_web', true )
                 ?: get_post_meta( $id, 'website', true );
        $stand    = get_post_meta( $id, '_mecspe_stand', true )
                 ?: get_post_meta( $id, 'stand', true );
        $pad      = get_post_meta( $id, '_mecspe_padiglione', true )
                 ?: get_post_meta( $id, 'padiglione', true );
        ?>
        <article class="mecspe-card" id="post-<?php echo $id; ?>">

            <!-- Thumbnail -->
            <a href="<?php the_permalink(); ?>" class="mecspe-card-thumb-link" tabindex="-1" aria-hidden="true">
                <div class="mecspe-card-thumb">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'medium', [ 'class' => 'mecspe-card-img', 'loading' => 'lazy' ] ); ?>
                    <?php else : ?>
                        <div class="mecspe-card-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <path d="M3 9h18M9 21V9"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
            </a>

            <!-- Body -->
            <div class="mecspe-card-body">
                <h2 class="mecspe-card-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h2>

                <?php if ( ! empty( $tax_badges ) ) : ?>
                <div class="mecspe-card-tags">
                    <?php foreach ( array_slice( $tax_badges, 0, 3 ) as $badge ) : ?>
                    <span class="mecspe-tag"><?php echo esc_html( $badge ); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ( $pad || $stand ) : ?>
                <ul class="mecspe-card-specs">
                    <?php if ( $pad ) : ?>
                    <li>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>
                        Pad. <?php echo esc_html( $pad ); ?>
                    </li>
                    <?php endif; ?>
                    <?php if ( $stand ) : ?>
                    <li>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
                        </svg>
                        Stand <?php echo esc_html( $stand ); ?>
                    </li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>

                <?php if ( has_excerpt() ) : ?>
                <p class="mecspe-card-excerpt"><?php echo wp_trim_words( get_the_excerpt(), 18 ); ?></p>
                <?php endif; ?>
            </div><!-- /.mecspe-card-body -->

            <!-- Footer -->
            <div class="mecspe-card-footer">
                <a href="<?php the_permalink(); ?>" class="mecspe-btn-dettaglio">
                    Dettaglio
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>
                <?php if ( $sito ) : ?>
                <a href="<?php echo esc_url( $sito ); ?>" target="_blank" rel="noopener noreferrer"
                   class="mecspe-btn-sito">Sito web</a>
                <?php endif; ?>
            </div>

        </article>
        <?php
    }
    wp_reset_postdata();
}

/* =========================================================
   6. HELPER: tassonomie disponibili per il CPT
   ========================================================= */
function mecspe_get_tax_filters(): array {
    $result = [];
    $taxonomies = get_object_taxonomies( MECSPE_POST_TYPE, 'objects' );
    /* Escludi tassonomie interne WP */
    $exclude = [ 'post_format', 'post_tag' ];
    foreach ( $taxonomies as $tax ) {
        if ( in_array( $tax->name, $exclude, true ) ) continue;
        $terms = get_terms( [ 'taxonomy' => $tax->name, 'hide_empty' => true, 'number' => 100 ] );
        if ( empty( $terms ) || is_wp_error( $terms ) ) continue;
        $result[ $tax->name ] = [
            'label' => $tax->label,
            'terms' => $terms,
        ];
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

    $tax_query = [];
    $taxonomies = array_keys( mecspe_get_tax_filters() );
    foreach ( $taxonomies as $tax ) {
        $values = array_filter( array_map( 'sanitize_text_field', (array)( $_REQUEST[ $tax ] ?? [] ) ) );
        if ( ! empty( $values ) ) {
            $tax_query[] = [ 'taxonomy' => $tax, 'field' => 'slug', 'terms' => $values ];
        }
    }
    if ( ! empty( $tax_query ) ) {
        $tax_query['relation'] = 'AND';
        $args['tax_query']     = $tax_query;
    }

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
   9. FLUSH REWRITE
   ========================================================= */
register_activation_hook( __FILE__, function () {
    mecspe_register_taxonomies();
    flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
