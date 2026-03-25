<?php
/**
 * Plugin Name: MECSPE Espositori
 * Plugin URI:  https://github.com/ubska/mecspe-scraper
 * Description: Visualizza gli espositori MECSPE con filtri laterali e superiori.
 * Version:     1.0.0
 * Author:      MECSPE Scraper
 * Text Domain: mecspe-plugin
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MECSPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MECSPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* =========================================================
   1. CUSTOM POST TYPE
   ========================================================= */
add_action( 'init', 'mecspe_register_cpt' );
function mecspe_register_cpt() {
    register_post_type( 'mecspe_espositore', [
        'labels'        => [
            'name'          => 'Espositori MECSPE',
            'singular_name' => 'Espositore',
            'add_new_item'  => 'Aggiungi Espositore',
            'edit_item'     => 'Modifica Espositore',
            'search_items'  => 'Cerca Espositori',
            'not_found'     => 'Nessun espositore trovato.',
        ],
        'public'        => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-building',
        'supports'      => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
        'has_archive'   => true,
        'rewrite'       => [ 'slug' => 'espositori' ],
        'show_in_rest'  => true,
    ] );
}

/* =========================================================
   2. TASSONOMIE
   ========================================================= */
add_action( 'init', 'mecspe_register_taxonomies' );
function mecspe_register_taxonomies() {
    // Settore merceologico
    register_taxonomy( 'mecspe_settore', 'mecspe_espositore', [
        'labels'            => [
            'name'          => 'Settori',
            'singular_name' => 'Settore',
            'all_items'     => 'Tutti i settori',
        ],
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'rewrite'           => [ 'slug' => 'settore' ],
    ] );

    // Padiglione
    register_taxonomy( 'mecspe_padiglione', 'mecspe_espositore', [
        'labels'            => [
            'name'          => 'Padiglioni',
            'singular_name' => 'Padiglione',
            'all_items'     => 'Tutti i padiglioni',
        ],
        'hierarchical'      => false,
        'show_in_rest'      => true,
        'rewrite'           => [ 'slug' => 'padiglione' ],
    ] );
}

/* =========================================================
   3. ASSETS
   ========================================================= */
add_action( 'wp_enqueue_scripts', 'mecspe_enqueue_assets' );
function mecspe_enqueue_assets() {
    wp_enqueue_style(
        'mecspe-style',
        MECSPE_PLUGIN_URL . 'assets/css/style.css',
        [],
        '1.0.0'
    );
    wp_enqueue_script(
        'mecspe-filters',
        MECSPE_PLUGIN_URL . 'assets/js/filters.js',
        [ 'jquery' ],
        '1.0.0',
        true
    );
    wp_localize_script( 'mecspe-filters', 'MecspeAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'mecspe_filter_nonce' ),
    ] );
}

/* =========================================================
   4. SHORTCODE  [mecspe_espositori]
   ========================================================= */
add_shortcode( 'mecspe_espositori', 'mecspe_shortcode' );
function mecspe_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'per_page' => 12,
    ], $atts );

    ob_start();
    mecspe_render_archive( (int) $atts['per_page'] );
    return ob_get_clean();
}

/* =========================================================
   5. RENDER PRINCIPALE
   ========================================================= */
function mecspe_render_archive( $per_page = 12 ) {
    $settori    = get_terms( [ 'taxonomy' => 'mecspe_settore',    'hide_empty' => true ] );
    $padiglioni = get_terms( [ 'taxonomy' => 'mecspe_padiglione', 'hide_empty' => true ] );

    $args = mecspe_build_query_args( $per_page );
    $query = new WP_Query( $args );
    ?>
    <div class="mecspe-wrap" id="mecspe-wrap">

        <!-- ── TOP BAR ── -->
        <div class="mecspe-topbar">
            <div class="mecspe-search-wrap">
                <input type="text"
                       id="mecspe-search"
                       placeholder="Cerca espositore&hellip;"
                       value="<?php echo esc_attr( $_GET['mecspe_s'] ?? '' ); ?>"
                       autocomplete="off">
                <span class="mecspe-search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </span>
            </div>
            <div class="mecspe-topbar-right">
                <span class="mecspe-results-count" id="mecspe-count">
                    <?php echo $query->found_posts; ?> espositori
                </span>
                <select id="mecspe-orderby" class="mecspe-select">
                    <option value="title-ASC"  <?php selected( ($_GET['mecspe_order'] ?? 'title-ASC'), 'title-ASC' ); ?>>A &ndash; Z</option>
                    <option value="title-DESC" <?php selected( ($_GET['mecspe_order'] ?? ''), 'title-DESC' ); ?>>Z &ndash; A</option>
                    <option value="date-DESC"  <?php selected( ($_GET['mecspe_order'] ?? ''), 'date-DESC' ); ?>>Più recenti</option>
                </select>
                <button class="mecspe-btn-toggle-sidebar" id="mecspe-toggle-sidebar" aria-label="Mostra/nascondi filtri">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" y1="6" x2="20" y2="6"/>
                        <line x1="8" y1="12" x2="20" y2="12"/>
                        <line x1="12" y1="18" x2="20" y2="18"/>
                    </svg>
                    Filtri
                </button>
            </div>
        </div>

        <!-- ── LAYOUT ── -->
        <div class="mecspe-layout">

            <!-- ── SIDEBAR ── -->
            <aside class="mecspe-sidebar" id="mecspe-sidebar">
                <div class="mecspe-sidebar-header">
                    <h3>Filtra per</h3>
                    <button class="mecspe-reset-btn" id="mecspe-reset">Azzera filtri</button>
                </div>

                <?php if ( ! empty( $settori ) && ! is_wp_error( $settori ) ) : ?>
                <div class="mecspe-filter-group">
                    <button class="mecspe-filter-group-toggle" aria-expanded="true">
                        Settore
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="mecspe-filter-options">
                        <?php foreach ( $settori as $term ) : ?>
                        <label class="mecspe-checkbox-label">
                            <input type="checkbox"
                                   class="mecspe-filter-check"
                                   data-taxonomy="mecspe_settore"
                                   value="<?php echo esc_attr( $term->slug ); ?>"
                                   <?php echo in_array( $term->slug, (array)( $_GET['mecspe_settore'] ?? [] ) ) ? 'checked' : ''; ?>>
                            <span class="mecspe-checkbox-custom"></span>
                            <?php echo esc_html( $term->name ); ?>
                            <span class="mecspe-term-count">(<?php echo $term->count; ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $padiglioni ) && ! is_wp_error( $padiglioni ) ) : ?>
                <div class="mecspe-filter-group">
                    <button class="mecspe-filter-group-toggle" aria-expanded="true">
                        Padiglione
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="mecspe-filter-options">
                        <?php foreach ( $padiglioni as $term ) : ?>
                        <label class="mecspe-checkbox-label">
                            <input type="checkbox"
                                   class="mecspe-filter-check"
                                   data-taxonomy="mecspe_padiglione"
                                   value="<?php echo esc_attr( $term->slug ); ?>"
                                   <?php echo in_array( $term->slug, (array)( $_GET['mecspe_padiglione'] ?? [] ) ) ? 'checked' : ''; ?>>
                            <span class="mecspe-checkbox-custom"></span>
                            <?php echo esc_html( $term->name ); ?>
                            <span class="mecspe-term-count">(<?php echo $term->count; ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </aside>

            <!-- ── GRID ── -->
            <div class="mecspe-main" id="mecspe-main">
                <div class="mecspe-grid" id="mecspe-grid">
                    <?php mecspe_render_cards( $query ); ?>
                </div>

                <?php if ( $query->max_num_pages > 1 ) : ?>
                <div class="mecspe-pagination" id="mecspe-pagination">
                    <button class="mecspe-load-more" id="mecspe-load-more"
                            data-page="2" data-max="<?php echo $query->max_num_pages; ?>">
                        Carica altri
                    </button>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /.mecspe-layout -->
    </div><!-- /.mecspe-wrap -->
    <?php
    wp_reset_postdata();
}

/* =========================================================
   6. RENDER CARD SINGOLA
   ========================================================= */
function mecspe_render_cards( WP_Query $query ) {
    if ( ! $query->have_posts() ) {
        echo '<div class="mecspe-no-results"><p>Nessun espositore trovato con i filtri selezionati.</p></div>';
        return;
    }
    while ( $query->have_posts() ) {
        $query->the_post();
        $settori    = get_the_terms( get_the_ID(), 'mecspe_settore' );
        $padiglioni = get_the_terms( get_the_ID(), 'mecspe_padiglione' );
        $sito       = get_post_meta( get_the_ID(), '_mecspe_sito_web', true );
        $stand      = get_post_meta( get_the_ID(), '_mecspe_stand', true );
        ?>
        <article class="mecspe-card">
            <a href="<?php the_permalink(); ?>" class="mecspe-card-link">
                <div class="mecspe-card-thumb">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'medium', [ 'class' => 'mecspe-card-img' ] ); ?>
                    <?php else : ?>
                        <div class="mecspe-card-no-img">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mecspe-card-body">
                    <h2 class="mecspe-card-title"><?php the_title(); ?></h2>

                    <?php if ( ! empty( $settori ) && ! is_wp_error( $settori ) ) : ?>
                    <div class="mecspe-card-tags">
                        <?php foreach ( $settori as $s ) : ?>
                        <span class="mecspe-tag"><?php echo esc_html( $s->name ); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mecspe-card-meta">
                        <?php if ( ! empty( $padiglioni ) && ! is_wp_error( $padiglioni ) ) : ?>
                        <span class="mecspe-meta-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            </svg>
                            <?php echo esc_html( implode( ', ', wp_list_pluck( $padiglioni, 'name' ) ) ); ?>
                        </span>
                        <?php endif; ?>
                        <?php if ( $stand ) : ?>
                        <span class="mecspe-meta-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
                            </svg>
                            Stand <?php echo esc_html( $stand ); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if ( has_excerpt() ) : ?>
                    <p class="mecspe-card-excerpt"><?php echo wp_trim_words( get_the_excerpt(), 15 ); ?></p>
                    <?php endif; ?>
                </div>
            </a>
            <?php if ( $sito ) : ?>
            <div class="mecspe-card-footer">
                <a href="<?php echo esc_url( $sito ); ?>" target="_blank" rel="noopener noreferrer"
                   class="mecspe-btn-sito">Visita il sito</a>
            </div>
            <?php endif; ?>
        </article>
        <?php
    }
}

/* =========================================================
   7. QUERY ARGS HELPER
   ========================================================= */
function mecspe_build_query_args( int $per_page, int $paged = 1 ): array {
    $args = [
        'post_type'      => 'mecspe_espositore',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'post_status'    => 'publish',
    ];

    // Ricerca
    $s = sanitize_text_field( $_REQUEST['mecspe_s'] ?? '' );
    if ( $s ) $args['s'] = $s;

    // Ordinamento
    $order_raw = sanitize_text_field( $_REQUEST['mecspe_order'] ?? 'title-ASC' );
    [ $orderby, $order ] = array_pad( explode( '-', $order_raw, 2 ), 2, 'ASC' );
    $allowed_orderby = [ 'title', 'date' ];
    $args['orderby'] = in_array( $orderby, $allowed_orderby ) ? $orderby : 'title';
    $args['order']   = ( strtoupper( $order ) === 'DESC' ) ? 'DESC' : 'ASC';

    // Filtri tassonomia
    $tax_query = [];
    foreach ( [ 'mecspe_settore', 'mecspe_padiglione' ] as $tax ) {
        $values = array_filter( array_map( 'sanitize_text_field', (array)( $_REQUEST[ $tax ] ?? [] ) ) );
        if ( ! empty( $values ) ) {
            $tax_query[] = [
                'taxonomy' => $tax,
                'field'    => 'slug',
                'terms'    => $values,
            ];
        }
    }
    if ( ! empty( $tax_query ) ) {
        $tax_query['relation'] = 'AND';
        $args['tax_query']     = $tax_query;
    }

    return $args;
}

/* =========================================================
   8. AJAX HANDLER
   ========================================================= */
add_action( 'wp_ajax_mecspe_filter',        'mecspe_ajax_filter' );
add_action( 'wp_ajax_nopriv_mecspe_filter', 'mecspe_ajax_filter' );
function mecspe_ajax_filter() {
    check_ajax_referer( 'mecspe_filter_nonce', 'nonce' );

    $per_page = 12;
    $paged    = max( 1, (int)( $_REQUEST['paged'] ?? 1 ) );
    $args     = mecspe_build_query_args( $per_page, $paged );
    $query    = new WP_Query( $args );

    ob_start();
    mecspe_render_cards( $query );
    $html = ob_get_clean();
    wp_reset_postdata();

    wp_send_json_success( [
        'html'      => $html,
        'found'     => $query->found_posts,
        'max_pages' => $query->max_num_pages,
        'paged'     => $paged,
    ] );
}

/* =========================================================
   9. FLUSH REWRITE RULES
   ========================================================= */
register_activation_hook( __FILE__, function () {
    mecspe_register_cpt();
    mecspe_register_taxonomies();
    flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
