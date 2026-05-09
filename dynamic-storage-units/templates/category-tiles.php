<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Variables: $tiles, $config_name, $unavailable_handling, $tiles_alignment, $hide_tiles_mobile (optional), $hide_all_tile (optional)
// $tiles = [ [ slug, label, description, available, from_price, move_in_total ], ... ]
$show_unavailable  = ( $unavailable_handling !== 'hide' );
$tiles_align_class = ( ( $tiles_alignment ?? 'left' ) === 'center' ) ? ' dsu-cat-tiles--center' : '';
$tiles_wrap_class  = ! empty( $hide_tiles_mobile ) ? ' dsu-hide-mobile' : '';
$hide_all_tile     = ! empty( $hide_all_tile );
?>
<div class="dsu-cat-tiles-wrap<?php echo esc_attr( $tiles_wrap_class ); ?>" data-config="<?php echo esc_attr( $config_name ); ?>">
	<div class="dsu-cat-tiles<?php echo esc_attr( $tiles_align_class ); ?>" role="group" aria-label="<?php esc_attr_e( 'Filter by unit size', 'dynamic-storage-units' ); ?>">

		<?php if ( ! $hide_all_tile ) : ?>
		<button type="button" class="dsu-cat-tile dsu-cat-tile--all dsu-cat-tile--active"
		        data-category="" aria-pressed="true">
			<span class="dsu-cat-tile-label"><?php esc_html_e( 'All Units', 'dynamic-storage-units' ); ?></span>
			<span class="dsu-cat-tile-desc"><?php esc_html_e( 'Browse everything', 'dynamic-storage-units' ); ?></span>
		</button>
		<?php endif; ?>

		<?php foreach ( $tiles as $tile ) :
			$available = $tile['available'];
			if ( ! $available && ! $show_unavailable ) {
				continue;
			}
		?>
		<button type="button"
		        class="dsu-cat-tile<?php echo ! $available ? ' dsu-cat-tile--unavailable' : ''; ?>"
		        data-category="<?php echo esc_attr( $tile['slug'] ); ?>"
		        data-unit-type="<?php echo esc_attr( $tile['unit_type'] ?? '' ); ?>"
		        aria-pressed="false"
		        <?php echo ! $available ? 'disabled aria-disabled="true"' : ''; ?>>
			<span class="dsu-cat-tile-label"><?php echo esc_html( $tile['label'] ); ?></span>
			<span class="dsu-cat-tile-desc"><?php echo esc_html( $tile['description'] ); ?></span>
			<?php if ( $available ) :
				// Show cents only when the from_price is not a whole dollar
				$from_decimals = ( fmod( round( $tile['from_price'], 2 ), 1.0 ) > 0.001 ) ? 2 : 0;
				$from_fmt      = number_format( $tile['from_price'], $from_decimals );
				$from_asterisk = ! empty( $tile['from_price_is_special'] ) ? '*' : '';
			?>
				<span class="dsu-cat-tile-price">
					<?php echo esc_html( sprintf(
						/* translators: 1: formatted price, 2: asterisk if special */
						__( 'From $%1$s/month%2$s', 'dynamic-storage-units' ),
						$from_fmt,
						$from_asterisk
					) ); ?>
				</span>
				<?php if ( $tile['move_in_total'] > 0 ) : ?>
					<span class="dsu-cat-tile-movein">
						<?php echo esc_html( sprintf(
							__( 'Move-in from $%s', 'dynamic-storage-units' ),
							number_format( $tile['move_in_total'], 2 )
						) ); ?>
					</span>
				<?php endif; ?>
			<?php else : ?>
				<span class="dsu-cat-tile-unavailable"><?php esc_html_e( 'Currently Unavailable', 'dynamic-storage-units' ); ?></span>
			<?php endif; ?>
		</button>
		<?php endforeach; ?>

	</div>
</div>
