<?php defined( 'ABSPATH' ) || exit; ?>

<div class="mecspe-trucks-wrap">

	<div class="mecspe-filters">
		<form id="mecspe-filter-form" class="mecspe-filter-form">

			<div class="mecspe-filter-group">
				<label for="mecspe-marca"><?php esc_html_e( 'Marca', 'mecspe-trucks' ); ?></label>
				<select id="mecspe-marca" name="marca">
					<option value=""><?php esc_html_e( 'Tutte le marche', 'mecspe-trucks' ); ?></option>
					<?php foreach ( $filters['marche'] as $m ) : ?>
						<option value="<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="mecspe-filter-group">
				<label for="mecspe-modello"><?php esc_html_e( 'Modello', 'mecspe-trucks' ); ?></label>
				<select id="mecspe-modello" name="modello">
					<option value=""><?php esc_html_e( 'Tutti i modelli', 'mecspe-trucks' ); ?></option>
					<?php foreach ( $filters['modelli'] as $m ) : ?>
						<option value="<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="mecspe-filter-group mecspe-filter-range">
				<label><?php esc_html_e( 'Prezzo (€)', 'mecspe-trucks' ); ?></label>
				<div class="mecspe-range-inputs">
					<input type="number" name="prezzo_min" placeholder="Min" min="0" step="1000">
					<span>–</span>
					<input type="number" name="prezzo_max" placeholder="Max" min="0" step="1000">
				</div>
			</div>

			<div class="mecspe-filter-group">
				<label for="mecspe-km"><?php esc_html_e( 'Km max', 'mecspe-trucks' ); ?></label>
				<input type="number" id="mecspe-km" name="km_max" placeholder="es. 300000" min="0" step="10000">
			</div>

			<div class="mecspe-filter-group mecspe-filter-actions">
				<button type="submit" class="mecspe-btn mecspe-btn-primary">
					<?php esc_html_e( 'Cerca', 'mecspe-trucks' ); ?>
				</button>
				<button type="reset" class="mecspe-btn mecspe-btn-secondary" id="mecspe-reset">
					<?php esc_html_e( 'Azzera', 'mecspe-trucks' ); ?>
				</button>
			</div>

		</form>
	</div>

	<div class="mecspe-results-header">
		<span id="mecspe-count"><?php printf( esc_html__( '%d veicoli trovati', 'mecspe-trucks' ), count( $trucks ) ); ?></span>
		<span class="mecspe-loader" id="mecspe-loader" style="display:none;">
			<?php esc_html_e( 'Caricamento...', 'mecspe-trucks' ); ?>
		</span>
	</div>

	<div class="mecspe-trucks-grid" id="mecspe-trucks-grid">
		<?php if ( empty( $trucks ) ) : ?>
			<p class="mecspe-no-results"><?php esc_html_e( 'Nessun veicolo disponibile.', 'mecspe-trucks' ); ?></p>
		<?php else : ?>
			<?php foreach ( $trucks as $truck ) : ?>
				<?php include __DIR__ . '/truck-card.php'; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

</div>
