<?php defined( 'ABSPATH' ) || exit; ?>

<div class="mecspe-truck-card">

	<?php if ( ! empty( $truck['thumb'] ) ) : ?>
		<div class="mecspe-truck-thumb">
			<a href="<?php echo esc_url( $truck['link'] ); ?>">
				<img src="<?php echo esc_url( $truck['thumb'] ); ?>"
				     alt="<?php echo esc_attr( $truck['title'] ); ?>"
				     loading="lazy">
			</a>
		</div>
	<?php endif; ?>

	<div class="mecspe-truck-body">

		<h3 class="mecspe-truck-title">
			<a href="<?php echo esc_url( $truck['link'] ); ?>">
				<?php echo esc_html( $truck['title'] ); ?>
			</a>
		</h3>

		<ul class="mecspe-truck-meta">
			<?php if ( ! empty( $truck['prezzo'] ) ) : ?>
				<li>
					<span class="mecspe-meta-label"><?php esc_html_e( 'Prezzo', 'mecspe-trucks' ); ?></span>
					<span class="mecspe-meta-value mecspe-price">
						€ <?php echo esc_html( number_format( (float) $truck['prezzo'], 0, ',', '.' ) ); ?>
					</span>
				</li>
			<?php endif; ?>

			<?php if ( ! empty( $truck['km'] ) ) : ?>
				<li>
					<span class="mecspe-meta-label"><?php esc_html_e( 'Km', 'mecspe-trucks' ); ?></span>
					<span class="mecspe-meta-value"><?php echo esc_html( number_format( (int) $truck['km'], 0, ',', '.' ) ); ?> km</span>
				</li>
			<?php endif; ?>

			<?php if ( ! empty( $truck['prima_immatricolazione'] ) ) : ?>
				<li>
					<span class="mecspe-meta-label"><?php esc_html_e( 'Immatricolazione', 'mecspe-trucks' ); ?></span>
					<span class="mecspe-meta-value"><?php echo esc_html( $truck['prima_immatricolazione'] ); ?></span>
				</li>
			<?php endif; ?>

			<?php if ( ! empty( $truck['cavalli'] ) ) : ?>
				<li>
					<span class="mecspe-meta-label"><?php esc_html_e( 'Cavalli', 'mecspe-trucks' ); ?></span>
					<span class="mecspe-meta-value"><?php echo esc_html( $truck['cavalli'] ); ?> CV</span>
				</li>
			<?php endif; ?>
		</ul>

		<?php if ( ! empty( $truck['veicolo_pronto'] ) ) : ?>
			<span class="mecspe-badge mecspe-badge-ready"><?php esc_html_e( 'Pronto consegna', 'mecspe-trucks' ); ?></span>
		<?php endif; ?>

		<a href="<?php echo esc_url( $truck['link'] ); ?>" class="mecspe-btn mecspe-btn-primary mecspe-truck-cta">
			<?php esc_html_e( 'Vedi dettagli', 'mecspe-trucks' ); ?>
		</a>

	</div>

</div>
