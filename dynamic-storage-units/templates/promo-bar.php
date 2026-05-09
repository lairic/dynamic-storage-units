<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Variables: $promo_data, $config_name, $hide_promo_mobile (optional)
// $promo_data = [ 'label' => '...', 'count' => N ]
$bar_label      = $promo_data['label'];
$promo_wrap_class = ! empty( $hide_promo_mobile ) ? ' dsu-hide-mobile' : '';
$aria_label = sprintf(
	/* translators: %s: promotion name */
	__( 'Filter to units with %s promotion', 'dynamic-storage-units' ),
	$bar_label
);
?>
<div class="dsu-promo-bar-wrap<?php echo esc_attr( $promo_wrap_class ); ?>">
	<button type="button"
	        class="dsu-promo-bar"
	        data-config="<?php echo esc_attr( $config_name ); ?>"
	        aria-pressed="false"
	        aria-label="<?php echo esc_attr( $aria_label ); ?>">
		<span class="dsu-promo-bar-eyebrow">
			<?php esc_html_e( 'Find promotions on selected spaces', 'dynamic-storage-units' ); ?>
		</span>
		<span class="dsu-promo-bar-main">
			<span class="dsu-promo-bar-icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
			</span>
			<span class="dsu-promo-bar-label"><?php echo esc_html( $bar_label ); ?></span>
		</span>
	</button>
</div>
