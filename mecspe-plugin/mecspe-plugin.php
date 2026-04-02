<?php
/**
 * Plugin Name:  MECSPE Prodotti
 * Plugin URI:   https://github.com/ubska/mecspe-scraper
 * Description:  Visualizza i veicoli usati con filtri dropdown, sidebar e card orizzontali.
 * Version:      2.0.7
 * Author:       MECSPE Scraper
 * Text Domain:  mecspe-plugin
 * License:      GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MECSPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MECSPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MECSPE_POST_TYPE',  'prodotti' );

/* Legge lo slug della pagina archivio dalle impostazioni (default: pagina-filtri) */
function mecspe_archive_slug(): string {
    return sanitize_title( get_option( 'mecspe_archive_slug', 'pagina-filtri' ) );
}

/* Legge la mappa slug→offerta dalle impostazioni */
function mecspe_slug_offerta_map(): array {
    $saved = get_option( 'mecspe_slug_map', '' );
    if ( ! $saved ) {
        return [
            'seminuovo-exrent'            => 'Seminuovo ExRent',
            'usato-controllato-garantito' => 'Usato Controllato Garantito',
            'usato-cgt-trucks'            => 'Usato CGT Trucks',
            'usato-multimarca'            => 'Usato Multimarca',
        ];
    }
    $map = [];
    foreach ( explode( "\n", $saved ) as $line ) {
        $line = trim( $line );
        if ( ! $line || ! str_contains( $line, '=' ) ) continue;
        [ $slug, $offerta ] = array_map( 'trim', explode( '=', $line, 2 ) );
        if ( $slug && $offerta ) $map[ sanitize_title($slug) ] = $offerta;
    }
    return $map;
}

/* =========================================================
   PAGINA IMPOSTAZIONI ADMIN
   ========================================================= */
add_action( 'admin_menu', function() {
    add_options_page( 'MECSPE Prodotti', 'MECSPE Prodotti', 'manage_options', 'mecspe-settings', 'mecspe_settings_page' );
} );

add_action( 'admin_init', function() {
    register_setting( 'mecspe_settings', 'mecspe_archive_slug', [ 'sanitize_callback' => 'sanitize_title' ] );
    register_setting( 'mecspe_settings', 'mecspe_slug_map',     [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
} );

/* Dopo il salvataggio delle impostazioni, flush rewrite rules */
add_action( 'update_option_mecspe_archive_slug', function() { flush_rewrite_rules(); } );
add_action( 'update_option_mecspe_slug_map',     function() { flush_rewrite_rules(); } );

function mecspe_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $saved_slug = get_option( 'mecspe_archive_slug', 'pagina-filtri' );
    $saved_map  = get_option( 'mecspe_slug_map', "seminuovo-exrent = Seminuovo ExRent\nusato-controllato-garantito = Usato Controllato Garantito\nusato-cgt-trucks = Usato CGT Trucks\nusato-multimarca = Usato Multimarca" );
    ?>
    <div class="wrap">
        <h1>MECSPE Prodotti — Impostazioni</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'mecspe_settings' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="mecspe_archive_slug">Pagina archivio veicoli</label></th>
                    <td>
                        <select name="mecspe_archive_slug" id="mecspe_archive_slug">
                            <?php
                            foreach ( get_pages() as $page ) {
                                $slug = $page->post_name;
                                echo '<option value="' . esc_attr($slug) . '"' . selected($saved_slug, $slug, false) . '>'
                                   . esc_html( $page->post_title ) . ' (/' . esc_html($slug) . '/)</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Seleziona la pagina che contiene lo shortcode <code>[mecspe_prodotti]</code></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mecspe_slug_map">URL categorie → Tipo offerta</label></th>
                    <td>
                        <textarea name="mecspe_slug_map" id="mecspe_slug_map" rows="10" cols="60" class="large-text"><?php echo esc_textarea( $saved_map ); ?></textarea>
                        <p class="description">
                            Una riga per categoria: <code>slug-url = Valore Tipo Offerta</code><br>
                            Esempio: <code>seminuovo-exrent = Seminuovo ExRent</code><br>
                            Dopo il salvataggio i permalink si aggiornano automaticamente.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Salva impostazioni' ); ?>
        </form>
    </div>
    <?php
}

/* =========================================================
   REWRITE RULES — URL puliti per categoria
   ========================================================= */
add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'mecspe_offerta';
    return $vars;
} );

add_action( 'init', 'mecspe_add_rewrite_rules', 5 );
function mecspe_add_rewrite_rules() {
    $archive = mecspe_archive_slug();
    foreach ( mecspe_slug_offerta_map() as $slug => $offerta ) {
        add_rewrite_rule(
            '^' . preg_quote( $slug, '/' ) . '/?$',
            'index.php?pagename=' . $archive . '&mecspe_offerta=' . rawurlencode( $offerta ),
            'top'
        );
    }
}

/* Evita che WordPress faccia redirect canonical verso la pagina archivio */
add_filter( 'redirect_canonical', function( $redirect ) {
    if ( get_query_var( 'mecspe_offerta' ) ) return false;
    return $redirect;
} );

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
    /* Carica solo sulle pagine che contengono lo shortcode */
    global $post;
    $has_sc = is_a( $post, 'WP_Post' ) && (
        has_shortcode( $post->post_content, 'mecspe_prodotti' ) ||
        has_shortcode( $post->post_content, 'mecspe_espositori' )
    );
    if ( ! $has_sc ) return;

    wp_enqueue_style(  'mecspe-style',   MECSPE_PLUGIN_URL . 'assets/css/style.css',   [], '2.0.7' );
    wp_enqueue_script( 'mecspe-filters', MECSPE_PLUGIN_URL . 'assets/js/filters.js', ['jquery'], '2.0.7', true );
    wp_localize_script( 'mecspe-filters', 'MecspeAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'mecspe_filter_nonce' ),
    ] );
    /* CSS critico inline per battere qualsiasi tema */
    wp_add_inline_style( 'mecspe-style', '
        .mecspe-acc-toggle { display:flex!important; align-items:center!important; gap:8px!important; padding:10px 14px!important; font-size:13px!important; font-weight:700!important; color:#222!important; background:#fff!important; border-bottom:1px solid #e0e0e0!important; cursor:pointer!important; width:100%!important; box-sizing:border-box!important; }
        .mecspe-acc-toggle span { color:#1a6eb5!important; font-size:18px!important; display:inline-block!important; transition:transform .2s!important; }
        .mecspe-acc-toggle.open span { transform:rotate(90deg)!important; }
        .mecspe-cb-label { display:flex!important; align-items:center!important; gap:7px!important; font-size:12.5px!important; color:#444!important; cursor:pointer!important; padding:3px 0!important; }
        .mecspe-card-title a { color:#1a6eb5!important; text-decoration:none!important; font-size:17px!important; font-weight:700!important; }
        .mecspe-select-sort { width:auto!important; max-width:200px!important; }
        .mecspe-results-bar { display:flex!important; align-items:center!important; gap:10px!important; }
        .mecspe-card-specs { display:grid!important; grid-template-columns:1fr 1fr!important; gap:5px 16px!important; }
        .mecspe-btn-dettagli { background:#1a6eb5!important; color:#fff!important; text-decoration:none!important; }
    ' );
}

/* =========================================================
   3. SHORTCODE  [mecspe_prodotti]
   ========================================================= */
add_shortcode( 'mecspe_prodotti',   'mecspe_shortcode' );
add_shortcode( 'mecspe_espositori', 'mecspe_shortcode' );
function mecspe_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'per_page' => 12, 'offerta' => '' ], $atts );
    ob_start();
    mecspe_render_archive( (int) $atts['per_page'], sanitize_text_field( $atts['offerta'] ) );
    return ob_get_clean();
}

/* =========================================================
   4. RENDER ARCHIVIO
   ========================================================= */
function mecspe_render_archive( int $per_page = 12, string $offerta_preset = '' ) {
    /* Priorità: attributo shortcode > rewrite rule > parametro ?offerta= URL */
    $offerta_pre = $offerta_preset
        ?: sanitize_text_field( get_query_var( 'mecspe_offerta' ) )
        ?: sanitize_text_field( $_GET['offerta'] ?? '' );

    $meta_filters = mecspe_get_meta_filters();
    $args  = mecspe_build_query_args( $per_page, 1, $offerta_pre );
    $query = new WP_Query( $args );

    /* Opzioni per i dropdown in cima */
    $dd_marca  = mecspe_get_meta_options( 'marca_prodotto_0_testo_marca' );
    $dd_anno   = mecspe_get_year_options( 'prima_immatricolazione_prodotto' );
    $dd_cambio = mecspe_get_meta_options( 'cambio_prodotto_0_testo_cambio' );
    $dd_allest = mecspe_get_meta_options( 'allestimento_prodotto_0_testo_allestimento' );

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
                    <div class="mecspe-acc-toggle" role="button" aria-expanded="false">
                        <span>&#8250;</span> KM percorsi
                    </div>
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
                    /* Pre-spunta dal parametro ?offerta= per il filtro tipo_offerta */
                    if ( $meta_key === 'tipo_offerta_prodotto_0_testo_tipo_offerta' && $offerta_pre && empty($active) ) {
                        $active = [ $offerta_pre ];
                    }
                    $is_open = ! empty( $active );
                ?>
                <div class="mecspe-acc-item">
                    <div class="mecspe-acc-toggle<?php echo $is_open ? ' open' : ''; ?>" role="button" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
                        <span>&#8250;</span> <?php echo esc_html( $filter['label'] ); ?>
                    </div>
                    <div class="mecspe-acc-body" style="<?php echo $is_open ? '' : 'display:none'; ?>"><?php // phpcs:ignore ?>
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

        /* Campi semplici (meta key con suffisso _prodotto) */
        $modello  = get_post_meta( $id, 'modello_prodotto',               true );
        $km       = get_post_meta( $id, 'km_percorsi_prodotto',           true );
        $anno_raw = get_post_meta( $id, 'prima_immatricolazione_prodotto',true );
        $cavalli  = get_post_meta( $id, 'cavalli_prodotto',               true );
        $prezzo   = get_post_meta( $id, 'prezzo_prodotto',                true );
        $targa    = get_post_meta( $id, 'targa_prodotto',                 true );
        $cod      = get_post_meta( $id, 'codice_interno_prodotto',        true );
        $pronto   = get_post_meta( $id, 'veicolo_pronto_prodotto',        true );
        $raw_trat = get_post_meta( $id, 'trattativa_in_sede',             true );
        $is_trattativa = in_array( $raw_trat, ['Sì', 'sì', '1', 1, true], true );

        /* Repeater: formato {campo}_prodotto_0_testo_{campo} */
        $marca   = get_post_meta( $id, 'marca_prodotto_0_testo_marca',                        true );
        $cambio  = get_post_meta( $id, 'cambio_prodotto_0_testo_cambio',                      true );
        $motore  = get_post_meta( $id, 'motore_prodotto_0_testo_motore',                      true );
        $cabina  = get_post_meta( $id, 'cabina_prodotto_0_testo_cabina',                      true );
        $allest  = get_post_meta( $id, 'allestimento_prodotto_0_testo_allestimento',          true );
        $offerta = get_post_meta( $id, 'tipo_offerta_prodotto_0_testo_tipo_offerta',          true );

        /* Anno: estrai 4 cifre da "Maggio 2019" */
        $anno = '';
        if ( $anno_raw && preg_match('/\d{4}/', $anno_raw, $m) ) $anno = $m[0];

        /* KM: rimuovi separatori italiani ("557.206" → 557206) */
        $km_num = (int) preg_replace('/[^\d]/', '', $km);
        $km_fmt = $km_num > 0 ? number_format( $km_num, 0, ',', '.' ) : '';

        /* Prezzo: rimuovi separatori ("39.500" → 39500) */
        $prezzo_num = (int) preg_replace('/[^\d]/', '', $prezzo);
        $prezzo_fmt = $prezzo_num > 0 ? number_format( $prezzo_num, 0, ',', '.' ) : '';

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
                <div class="mecspe-card-badges">
                    <?php if ($km_fmt) : ?><span class="mecspe-badge">Km <?php echo esc_html($km_fmt); ?></span><?php endif; ?>
                    <?php if ($anno)   : ?><span class="mecspe-badge">Anno <?php echo esc_html($anno); ?></span><?php endif; ?>
                    <?php if ($motore) : ?><span class="mecspe-badge"><?php echo esc_html($motore); ?></span><?php endif; ?>
                </div>
                <?php if ($is_trattativa) : ?>
                <div class="mecspe-card-trattativa">Trattativa Riservata</div>
                <?php elseif ($prezzo_fmt) : ?>
                <div class="mecspe-card-prezzo">Tuo a <strong>&euro; <?php echo esc_html($prezzo_fmt); ?></strong></div>
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
                            <?php echo esc_html( implode('  ', array_filter([$cabina, $targa ? 'Rif: '.$targa : ''])) ); ?>
                        </p>
                    </div>
                    <?php if ($prezzo_fmt && !$is_trattativa) : ?>
                    <div class="mecspe-card-price-top">&euro; <?php echo esc_html($prezzo_fmt); ?></div>
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
function mecspe_get_meta_options( string $meta_key ): array {
    global $wpdb;
    $rows = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish'
           AND pm.meta_value != '' AND LENGTH(pm.meta_value) < 150
         ORDER BY pm.meta_value ASC LIMIT 200",
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
        'marca_prodotto_0_testo_marca'                     => 'Marca',
        'prima_immatricolazione_prodotto'                  => 'Anno immatricolazione',
        'cabina_prodotto_0_testo_cabina'                   => 'Cabina',
        'cambio_prodotto_0_testo_cambio'                   => 'Cambio',
        'allestimento_prodotto_0_testo_allestimento'       => 'Allestimento',
        'tipo_offerta_prodotto_0_testo_tipo_offerta'       => 'Tipo offerta',
        'motore_prodotto_0_testo_motore'                   => 'Motore',
        'equipaggiamento_prodotto_0_testo_equipaggiamento' => 'Equipaggiamento',
        'pneumatici_prodotto_0_testo_pneumatici'           => 'Pneumatici',
        'fender_laterale_prodotto_0_testo_fender'          => 'Fender laterale',
        'spoiler_prodotto_0_testo_spoiler'                 => 'Spoiler',
        'minigonne_prodotto_0_testo_minigonne'             => 'Minigonne',
    ];
    $result = [];
    foreach ( $groups as $key => $label ) {
        $options = ( $key === 'prima_immatricolazione_prodotto' )
            ? mecspe_get_year_options( $key )
            : mecspe_get_meta_options( $key );
        if ( ! empty($options) ) $result[$key] = [ 'label' => $label, 'options' => $options ];
    }
    return $result;
}

/* =========================================================
   7. QUERY ARGS
   ========================================================= */
function mecspe_build_query_args( int $per_page, int $paged = 1, string $offerta_forced = '' ): array {
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

    /* Parametro offerta: da shortcode, rewrite rule, ?offerta= URL, o AJAX */
    $offerta_shortcut = $offerta_forced
        ?: sanitize_text_field( get_query_var( 'mecspe_offerta' ) )
        ?: sanitize_text_field( $_REQUEST['offerta'] ?? '' );
    if ( $offerta_shortcut ) {
        $meta_query[] = [
            'key'     => 'tipo_offerta_prodotto_0_testo_tipo_offerta',
            'value'   => $offerta_shortcut,
            'compare' => '=',
        ];
    }

    /* Dropdown top bar */
    $dd_map = [
        'dd_marca'  => 'marca_prodotto_0_testo_marca',
        'dd_anno'   => 'prima_immatricolazione_prodotto',
        'dd_cambio' => 'cambio_prodotto_0_testo_cambio',
        'dd_allest' => 'allestimento_prodotto_0_testo_allestimento',
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
        if ( $meta_key === 'prima_immatricolazione_prodotto' ) {
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
    /* KM: il valore è in formato italiano "557.206" (punto = migliaia)
       Convertiamo in numero intero per il confronto */
    if ( $km_min > 0 && $km_max > 0 ) {
        $meta_query[] = [ 'key' => 'km_percorsi_prodotto', 'value' => [$km_min,$km_max], 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ];
    } elseif ( $km_min > 0 ) {
        $meta_query[] = [ 'key' => 'km_percorsi_prodotto', 'value' => $km_min, 'compare' => '>=', 'type' => 'NUMERIC' ];
    } elseif ( $km_max > 0 ) {
        $meta_query[] = [ 'key' => 'km_percorsi_prodotto', 'value' => $km_max, 'compare' => '<=', 'type' => 'NUMERIC' ];
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

/* =========================================================
   10. DEBUG — shortcode [mecspe_debug] (da rimuovere dopo)
   ========================================================= */
add_shortcode( 'mecspe_debug', function() {
    if ( ! current_user_can('administrator') ) return '';
    $posts = get_posts(['post_type'=>MECSPE_POST_TYPE,'posts_per_page'=>1,'post_status'=>'publish']);
    if ( empty($posts) ) return '<p>Nessun post prodotti trovato.</p>';
    $id   = $posts[0]->ID;
    $meta = get_post_meta( $id );
    ob_start();
    echo '<div style="font-family:monospace;font-size:12px;background:#f5f5f5;padding:16px;border:1px solid #ccc;max-height:600px;overflow:auto">';
    echo '<strong>Post ID: ' . $id . ' — ' . esc_html(get_the_title($id)) . '</strong><br><br>';
    echo '<strong>ACF get_field (campi semplici):</strong><br>';
    foreach(['modello','targa','km_percorsi','prezzo','prima_immatricolazione','cavalli','trattativa_in_sede','veicolo_pronto','codice_interno'] as $k) {
        $v = function_exists('get_field') ? get_field($k,$id) : '(ACF non attivo)';
        echo esc_html($k) . ' = <em>' . (is_array($v)?json_encode($v):esc_html((string)$v)) . '</em><br>';
    }
    echo '<br><strong>ACF get_field (repeater):</strong><br>';
    foreach(['marche','cabine','cambi','motori','allestimenti','tipi_offerta','equipaggiamenti','pneumatici','fender_laterali','elenco_spoiler','minigonne'] as $k) {
        $v = function_exists('get_field') ? get_field($k,$id) : '(ACF non attivo)';
        echo esc_html($k) . ' = <em>' . (is_array($v)?json_encode($v):esc_html((string)$v)) . '</em><br>';
    }
    echo '<br><strong>Tutti i meta nel DB (wp_postmeta):</strong><br>';
    ksort($meta);
    foreach ( $meta as $key => $vals ) {
        if ( substr($key,0,1)==='_' ) continue; // nascondi chiavi interne ACF
        echo esc_html($key) . ' = <em>' . esc_html( substr(implode(', ',$vals),0,120) ) . '</em><br>';
    }
    echo '</div>';
    return ob_get_clean();
} );
