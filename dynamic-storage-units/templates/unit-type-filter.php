<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Variables: $unit_types (array of {slug, label}), $config_name, $all_label (optional), $utf_size (optional), $hide_utf_mobile (optional)
$all_label       = ! empty( $all_label ) ? $all_label : __( 'All', 'dynamic-storage-units' );
$utf_size        = $utf_size ?? 'md';
$utf_class       = $utf_size !== 'md' ? ' dsu-utf-' . $utf_size : '';
$utf_hide_class  = ! empty( $hide_utf_mobile ) ? ' dsu-hide-mobile' : '';
?>
<div class="dsu-unit-type-wrap<?php echo esc_attr( $utf_class . $utf_hide_class ); ?>" data-config="<?php echo esc_attr( $config_name ); ?>">
	<div class="dsu-unit-type-filter" role="group"
	     aria-label="<?php esc_attr_e( 'Filter by unit type', 'dynamic-storage-units' ); ?>">

		<button type="button" class="dsu-unit-type-btn dsu-unit-type-btn--active"
		        data-unit-type="" aria-pressed="true">
			<?php echo esc_html( $all_label ); ?>
		</button>

		<?php foreach ( $unit_types as $ut ) : ?>
		<button type="button" class="dsu-unit-type-btn"
		        data-unit-type="<?php echo esc_attr( $ut['slug'] ); ?>"
		        aria-pressed="false">
			<?php echo esc_html( $ut['label'] ); ?>
		</button>
		<?php endforeach; ?>

	</div>
</div>
