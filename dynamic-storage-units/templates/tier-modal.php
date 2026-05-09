<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Variables: $modal_id, $name, $tiers, $tier_labels, $tier_classes, $had_overflow
// From outer scope: $soldout_handling, $config_name, $facility_code, $group_map
?>
<div id="<?php echo esc_attr( $modal_id ); ?>"
     class="dsu-tier-modal-overlay"
     hidden
     role="dialog"
     aria-modal="true"
     aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title">

	<div class="dsu-tier-modal-dialog">

		<div class="dsu-tier-modal-header">
			<h2 id="<?php echo esc_attr( $modal_id ); ?>-title" class="dsu-tier-modal-title">
				<?php echo esc_html( $name ); ?>
			</h2>
			<p class="dsu-tier-modal-subtitle">
				<?php esc_html_e( 'Compare options and choose the right fit for you.', 'dynamic-storage-units' ); ?>
			</p>
			<button type="button"
			        class="dsu-tier-modal-close dsu-close-tier-modal"
			        aria-label="<?php esc_attr_e( 'Close', 'dynamic-storage-units' ); ?>">
				&times;
			</button>
		</div>

		<div class="dsu-tier-cols">
			<?php foreach ( $tiers as $i => $tier ) :
				$gid         = $tier['id'] ?? '';
				$wp_data     = $group_map[ $gid ] ?? [];
				$width       = (float) ( $wp_data['width']  ?? 0 );
				$depth       = (float) ( $wp_data['depth']  ?? 0 );
				$height      = (float) ( $wp_data['height'] ?? 0 );
				$sqft        = (float) ( $wp_data['sqft']   ?? 0 );
				$features    = $wp_data['features'] ?? [];
				$v1_price    = (float) ( $wp_data['v1_price']         ?? $tier['streetRate'] ?? 0 );
				$v1_sp_price = (float) ( $wp_data['v1_special_price'] ?? 0 );
				$v1_sp_label = $wp_data['v1_special_label'] ?? '';
				$avail_total = (int) ( $tier['availableTotal'] ?? 0 );
				$is_avail    = $avail_total > 0;
				$move_in_url = $tier['_move_in_url'] ?? '';
				$reserve_url = $tier['_reserve_url'] ?? '';

				$has_discount  = $v1_sp_price > 0 && $v1_sp_price < $v1_price;
				$display_price = $has_discount ? $v1_sp_price : $v1_price;

				$tier_label = $tier_labels[ $i ] ?? '';
				$tier_class = $tier_classes[ $i ] ?? '';
				// Use the customer-facing v1 name, then v2 label, then outer group name
				$display_name = ( $wp_data['v1_name'] ?? '' ) ?: ( $tier['label'] ?? '' ) ?: $name;
			?>
			<div class="dsu-tier-col <?php echo esc_attr( $tier_class ); ?><?php echo ! $is_avail ? ' dsu-tier-col--soldout' : ''; ?>">

				<div class="dsu-tier-label"><?php echo esc_html( $tier_label ); ?></div>

				<h3 class="dsu-tier-unit-type"><?php echo esc_html( $display_name ); ?></h3>

				<?php if ( $width > 0 && $depth > 0 ) : ?>
					<p class="dsu-tier-dims">
						<?php echo esc_html( number_format( $width, 0 ) . '&times;' . number_format( $depth, 0 ) ); ?>
						<?php if ( $height > 0 ) : ?>
							<?php echo esc_html( '&times;' . number_format( $height, 0 ) . '\'' ); ?>
						<?php endif; ?>
						<?php if ( $sqft > 0 ) : ?>
							<span class="dsu-tier-sqft">(<?php echo esc_html( number_format( $sqft, 0 ) ); ?> sq ft)</span>
						<?php endif; ?>
					</p>
				<?php elseif ( $sqft > 0 ) : ?>
					<p class="dsu-tier-dims"><?php echo esc_html( number_format( $sqft, 0 ) ); ?> sq ft</p>
				<?php endif; ?>

				<?php if ( ! empty( $features ) ) : ?>
					<div class="dsu-tier-features" role="list">
						<?php foreach ( $features as $feat ) : ?>
							<?php echo DSU_Feature_Icons::render_tile( $feat ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="dsu-tier-price">
					<?php if ( ! $is_avail ) : ?>
						<div class="dsu-pricing-soldout"><?php esc_html_e( 'Sold Out', 'dynamic-storage-units' ); ?></div>
					<?php elseif ( $has_discount ) : ?>
						<div class="dsu-online-rate-label"><?php esc_html_e( 'Online Only Rate', 'dynamic-storage-units' ); ?></div>
						<?php if ( $v1_sp_label ) : ?>
							<div class="dsu-special-callout">&#11088; <?php echo esc_html( $v1_sp_label ); ?></div>
						<?php endif; ?>
						<div class="dsu-street-rate-strike">
							$<?php echo esc_html( number_format( $v1_price, 2 ) ); ?><span class="dsu-per-month-sm">/mo</span>
						</div>
						<div class="dsu-special-rate-main">
							$<?php echo esc_html( number_format( $display_price, 2 ) ); ?><span class="dsu-per-month-sm">/mo</span>
						</div>
					<?php else : ?>
						<div class="dsu-price-plain-value">
							$<?php echo esc_html( number_format( $v1_price, 2 ) ); ?><span class="dsu-per-month-sm">/mo</span>
						</div>
					<?php endif; ?>
				</div>

				<div class="dsu-tier-cta">
					<?php if ( $is_avail ) : ?>
						<?php if ( $move_in_url ) : ?>
							<a href="<?php echo esc_url( $move_in_url ); ?>"
							   class="dsu-btn dsu-btn-primary" rel="noopener"
							   aria-label="<?php echo esc_attr( sprintf( __( 'Rent Online – %s', 'dynamic-storage-units' ), $display_name ) ); ?>">
								<?php esc_html_e( 'Rent Online', 'dynamic-storage-units' ); ?>
							</a>
						<?php endif; ?>
						<?php if ( $reserve_url ) : ?>
							<a href="<?php echo esc_url( $reserve_url ); ?>"
							   class="dsu-btn dsu-btn-secondary" rel="noopener"
							   aria-label="<?php echo esc_attr( sprintf( __( 'Reserve – %s', 'dynamic-storage-units' ), $display_name ) ); ?>">
								<?php esc_html_e( 'Reserve', 'dynamic-storage-units' ); ?>
							</a>
						<?php endif; ?>
					<?php elseif ( $soldout_handling === 'waitlist' && ! $had_overflow ) : ?>
						<button type="button" class="dsu-btn dsu-btn-waitlist dsu-open-waitlist"
						        data-group-label="<?php echo esc_attr( $display_name ); ?>"
						        data-group-id="<?php echo esc_attr( $gid ); ?>"
						        data-facility-code="<?php echo esc_attr( $facility_code ); ?>"
						        data-config-name="<?php echo esc_attr( $config_name ); ?>"
						        aria-label="<?php echo esc_attr( sprintf( __( 'Join Waitlist for %s', 'dynamic-storage-units' ), $display_name ) ); ?>">
							<?php esc_html_e( 'Join Waitlist', 'dynamic-storage-units' ); ?>
						</button>
					<?php endif; ?>
				</div>

			</div><!-- .dsu-tier-col -->
			<?php endforeach; ?>
		</div><!-- .dsu-tier-cols -->

	</div><!-- .dsu-tier-modal-dialog -->
</div><!-- .dsu-tier-modal-overlay -->
