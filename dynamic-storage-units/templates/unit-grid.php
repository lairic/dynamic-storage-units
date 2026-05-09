<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Variables available: $display_units, $groups, $config, $group_map, $facility_code, $promo_label
$placeholder_url  = DSU_PLUGIN_URL . 'assets/img/placeholder.svg';
$soldout_handling = $config['soldout_handling'] ?? 'hide';
$config_name      = $config['name'] ?? '';
$display_format   = $config['display_format'] ?? 'grid';
$grid_class       = 'dsu-grid' . ( $display_format === 'list' ? ' dsu-grid--list' : '' );

$promo_label  = $promo_label ?? '';
$no_card_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/><line x1="17" y1="15" x2="21" y2="15" opacity="0.4"/></svg>';

$feat_size       = $config['feature_tag_size'] ?? 'sm';
$feat_size_class = in_array( $feat_size, [ 'md', 'lg' ], true ) ? ' dsu-feat-icons-row--' . $feat_size : '';
$feat_list_class = in_array( $feat_size, [ 'md', 'lg' ], true ) ? ' dsu-features--' . $feat_size : '';

$display_units = $display_units ?? [];
$tier_modals   = [];
?>
<div class="<?php echo esc_attr( $grid_class ); ?>"
     data-config="<?php echo esc_attr( $config_name ); ?>"
     role="list"
     aria-label="<?php esc_attr_e( 'Storage unit listings', 'dynamic-storage-units' ); ?>">
	<?php
	$rendered = 0;
	foreach ( $display_units as $item ) :

		// ==========================================
		// SINGLE UNIT CARD
		// ==========================================
		if ( $item['type'] === 'single' ) :
			$group       = $item['group'];
			$group_id    = $group['id'] ?? '';
			$street_rate = (float) ( $group['streetRate'] ?? 0 );
			$avail_total = (int) ( $group['availableTotal'] ?? 0 );
			$special     = $group['availableSpecial'] ?? null;
			$move_in_url = $group['_move_in_url'] ?? '';
			$reserve_url = $group['_reserve_url'] ?? '';

			$is_available = $avail_total > 0;

			$wp_data       = $group_map[ $group_id ] ?? [];
			// v1 `name` is the customer-facing display label; v2 `label` is the backend unit type
			$label         = $wp_data['v1_name'] ?: ( $group['label'] ?? '' );
			$unit_type     = $wp_data['unit_type'] ?? '';
			$image_url     = $wp_data['image_url'] ?? '';
			$features      = $wp_data['features'] ?? [];
			$size_category = $wp_data['size_category'] ?? '';
			$v1_price      = (float) ( $wp_data['v1_price'] ?? 0 );
			if ( empty( $image_url ) ) {
				$image_url = $placeholder_url;
			}

			// Use v1 price when available
			$display_rate = $v1_price > 0 ? $v1_price : $street_rate;

			$display_price = $display_rate;
			$has_special   = false;
			$special_label = '';
			$special_rate  = 0.0;

			// Check v1 special first
			$v1_sp_price = (float) ( $wp_data['v1_special_price'] ?? 0 );
			$v1_sp_label = $wp_data['v1_special_label'] ?? '';
			if ( $v1_sp_price > 0 && $v1_sp_price < $display_rate ) {
				$has_special   = true;
				$special_rate  = $v1_sp_price;
				$special_label = $v1_sp_label;
				$display_price = $v1_sp_price;
			} elseif ( is_array( $special ) && ! empty( $special ) ) {
				$has_special   = true;
				$special_label = $special['label'] ?? $special['promotionName'] ?? '';
				$special_rate  = (float) ( $special['specialRate'] ?? $special['rate'] ?? 0 );
				if ( $special_rate > 0 && $special_rate < $display_rate ) {
					$display_price = $special_rate;
				}
			}
			$has_discount = $has_special && $special_rate > 0 && $special_rate < $display_rate;

			$special_months = 0;
			if ( $has_special && preg_match( '/(\d+)\s*month/i', $special_label, $mx ) ) {
				$special_months = (int) $mx[1];
			}

			$is_featured_promo = ( $promo_label !== '' && $has_special && $special_label === $promo_label );
			$rendered++;
		?>

		<?php if ( $display_format === 'list' ) : ?>
		<!-- ========== LIST CARD (single) ========== -->
		<div class="dsu-list-card" role="listitem"
		     data-category="<?php echo esc_attr( $size_category ); ?>"
		     data-unit-type="<?php echo esc_attr( $unit_type ); ?>"
		     data-has-promo="<?php echo $is_featured_promo ? '1' : '0'; ?>">

			<div class="dsu-list-image-col">
				<div class="dsu-list-image-wrap">
					<img src="<?php echo esc_url( $image_url ); ?>"
					     alt="<?php echo esc_attr( $label ); ?>"
					     loading="lazy" />
				</div>
			</div>

			<div class="dsu-list-info-col">
				<h3 class="dsu-unit-size"><?php echo esc_html( $label ); ?></h3>
				<?php if ( $has_special && $special_label ) : ?>
					<p class="dsu-list-special-callout">&#11088; <strong><?php esc_html_e( 'Online Special:', 'dynamic-storage-units' ); ?></strong> <?php echo esc_html( $special_label ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $features ) ) : ?>
					<div class="dsu-feat-icons-row<?php echo esc_attr( $feat_size_class ); ?>"
					     role="list"
					     aria-label="<?php esc_attr_e( 'Features', 'dynamic-storage-units' ); ?>">
						<?php foreach ( $features as $feat ) : ?>
							<?php echo DSU_Feature_Icons::render_tile( $feat ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="dsu-list-rate-col">
				<?php if ( ! $is_available ) : ?>
					<div class="dsu-pricing-soldout"><?php esc_html_e( 'Sold Out', 'dynamic-storage-units' ); ?></div>
				<?php elseif ( $has_discount ) : ?>
					<div class="dsu-pricing-special">
						<div class="dsu-online-rate-label"><?php esc_html_e( 'Online Only Rate', 'dynamic-storage-units' ); ?></div>
						<div class="dsu-street-rate-strike">
							$<?php echo esc_html( number_format( $display_rate, 2 ) ); ?><span class="dsu-per-month-sm">/mo</span>
						</div>
						<div class="dsu-special-rate-main">
							$<?php echo esc_html( number_format( $display_price, 2 ) ); ?><span class="dsu-per-month-sm">/mo</span><sup class="dsu-asterisk">*</sup>
						</div>
						<p class="dsu-reserve-desc">
							<?php
							if ( ! empty( $move_in_url ) && ! empty( $reserve_url ) ) {
								esc_html_e( 'Rent or reserve today and lock in this special.', 'dynamic-storage-units' );
							} elseif ( ! empty( $move_in_url ) ) {
								esc_html_e( 'Rent today and lock in this special.', 'dynamic-storage-units' );
							} else {
								esc_html_e( 'Reserve today and lock in this special.', 'dynamic-storage-units' );
							}
							?>
						</p>
						<p class="dsu-no-card-line">
							<?php esc_html_e( 'No credit card needed to reserve', 'dynamic-storage-units' ); ?>
							<span class="dsu-no-card-icon"><?php echo $no_card_icon; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</p>
					</div>
				<?php else : ?>
					<div class="dsu-pricing-plain">
						<div class="dsu-price-plain-value">
							$<?php echo esc_html( number_format( $display_rate, 2 ) ); ?><span class="dsu-per-month-sm">/mo</span>
						</div>
						<?php if ( $is_available && $avail_total <= 2 ) : ?>
							<p class="dsu-scarcity-text"><?php printf( esc_html__( 'Only %d left!', 'dynamic-storage-units' ), $avail_total ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="dsu-list-cta-col">
				<?php if ( $is_available ) : ?>
					<?php if ( $move_in_url ) : ?>
						<a href="<?php echo esc_url( $move_in_url ); ?>"
						   class="dsu-btn dsu-btn-primary" rel="noopener"
						   aria-label="<?php echo esc_attr( sprintf( __( 'Rent Online – %s', 'dynamic-storage-units' ), $label ) ); ?>">
							<?php esc_html_e( 'Rent Online', 'dynamic-storage-units' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $reserve_url ) : ?>
						<a href="<?php echo esc_url( $reserve_url ); ?>"
						   class="dsu-btn dsu-btn-secondary" rel="noopener"
						   aria-label="<?php echo esc_attr( sprintf( __( 'Reserve – %s', 'dynamic-storage-units' ), $label ) ); ?>">
							<?php esc_html_e( 'Reserve', 'dynamic-storage-units' ); ?>
						</a>
					<?php endif; ?>
				<?php elseif ( $soldout_handling === 'waitlist' ) : ?>
					<button type="button" class="dsu-btn dsu-btn-waitlist dsu-open-waitlist"
					        data-group-label="<?php echo esc_attr( $label ); ?>"
					        data-group-id="<?php echo esc_attr( $group_id ); ?>"
					        data-facility-code="<?php echo esc_attr( $facility_code ); ?>"
					        data-config-name="<?php echo esc_attr( $config_name ); ?>"
					        aria-label="<?php echo esc_attr( sprintf( __( 'Join Waitlist for %s', 'dynamic-storage-units' ), $label ) ); ?>">
						<?php esc_html_e( 'Join Waitlist', 'dynamic-storage-units' ); ?>
					</button>
				<?php endif; ?>
			</div>

		</div><!-- .dsu-list-card -->

		<?php else : ?>
		<!-- ========== GRID CARD (single) ========== -->
		<div class="dsu-unit-card" role="listitem"
		     data-category="<?php echo esc_attr( $size_category ); ?>"
		     data-unit-type="<?php echo esc_attr( $unit_type ); ?>"
		     data-has-promo="<?php echo $is_featured_promo ? '1' : '0'; ?>">

			<div class="dsu-card-image">
				<img src="<?php echo esc_url( $image_url ); ?>"
				     alt="<?php echo esc_attr( $label ); ?>"
				     loading="lazy" />
			</div>

			<div class="dsu-card-body">

				<h3 class="dsu-unit-size"><?php echo esc_html( $label ); ?></h3>

				<?php if ( $has_special && $special_label ) : ?>
					<div class="dsu-special-callout">
						&#11088; <?php echo esc_html( $special_label ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $features ) ) : ?>
					<div class="dsu-feat-icons-row<?php echo esc_attr( $feat_size_class ); ?>"
					     role="list"
					     aria-label="<?php esc_attr_e( 'Features', 'dynamic-storage-units' ); ?>">
						<?php foreach ( $features as $feat ) : ?>
							<?php echo DSU_Feature_Icons::render_tile( $feat ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! $is_available ) : ?>
					<div class="dsu-availability dsu-soldout">
						<?php esc_html_e( 'Sold Out', 'dynamic-storage-units' ); ?>
					</div>
				<?php elseif ( $avail_total <= 2 ) : ?>
					<div class="dsu-availability dsu-low-stock">
						<?php echo esc_html( sprintf(
							_n( 'Only %d left', 'Only %d left', $avail_total, 'dynamic-storage-units' ),
							$avail_total
						) ); ?>
					</div>
				<?php endif; ?>

				<div class="dsu-price">
					$<?php echo esc_html( number_format( $display_price, 2 ) ); ?><span class="dsu-per-month">/month</span>
					<?php if ( $has_discount ) : ?>
						<span class="dsu-original-price">$<?php echo esc_html( number_format( $display_rate, 2 ) ); ?></span>
					<?php endif; ?>
				</div>

				<div class="dsu-cta-buttons">
					<?php if ( $is_available ) : ?>
						<?php if ( $move_in_url ) : ?>
							<a href="<?php echo esc_url( $move_in_url ); ?>"
							   class="dsu-btn dsu-btn-primary" rel="noopener"
							   aria-label="<?php echo esc_attr( sprintf( __( 'Rent Now – %s', 'dynamic-storage-units' ), $label ) ); ?>">
								<?php esc_html_e( 'Rent Now', 'dynamic-storage-units' ); ?>
							</a>
						<?php endif; ?>
						<?php if ( $reserve_url ) : ?>
							<a href="<?php echo esc_url( $reserve_url ); ?>"
							   class="dsu-btn dsu-btn-secondary" rel="noopener"
							   aria-label="<?php echo esc_attr( sprintf( __( 'Reserve – %s', 'dynamic-storage-units' ), $label ) ); ?>">
								<?php esc_html_e( 'Reserve', 'dynamic-storage-units' ); ?>
							</a>
						<?php endif; ?>
					<?php elseif ( $soldout_handling === 'waitlist' ) : ?>
						<button type="button" class="dsu-btn dsu-btn-waitlist dsu-open-waitlist"
						        data-group-label="<?php echo esc_attr( $label ); ?>"
						        data-group-id="<?php echo esc_attr( $group_id ); ?>"
						        data-facility-code="<?php echo esc_attr( $facility_code ); ?>"
						        data-config-name="<?php echo esc_attr( $config_name ); ?>"
						        aria-label="<?php echo esc_attr( sprintf( __( 'Join Waitlist for %s', 'dynamic-storage-units' ), $label ) ); ?>">
							<?php esc_html_e( 'Join Waitlist', 'dynamic-storage-units' ); ?>
						</button>
					<?php endif; ?>
				</div>

			</div>
		</div><!-- .dsu-unit-card -->

		<?php endif; // grid vs list (single) ?>

		<?php
		// ==========================================
		// GROUPED TIER CARD
		// ==========================================
		elseif ( $item['type'] === 'grouped' ) :
			$gname        = $item['name'];
			$modal_id     = $item['modal_id'];
			$tiers        = $item['tiers'];
			$from_price   = $item['from_price'];
			$from_is_spec = $item['from_is_special'];
			$from_regular = $item['from_regular'];
			$grouped_cta  = $item['grouped_cta'];

			// Good tier = cheapest ($tiers[0]) — used for image + features
			$good_tier     = $tiers[0];
			$good_gid      = $good_tier['id'] ?? '';
			$good_wp        = $group_map[ $good_gid ] ?? [];
			$good_image     = $good_wp['image_url'] ?? $placeholder_url;
			if ( empty( $good_image ) ) $good_image = $placeholder_url;
			$good_features  = $good_wp['features'] ?? [];
			$good_size_cat  = $good_wp['size_category'] ?? '';
			$good_unit_type = $good_wp['unit_type'] ?? '';

			// Featured promo check: any tier has the promo label
			$is_featured_promo = false;
			if ( $promo_label !== '' ) {
				foreach ( $tiers as $t ) {
					$sp = $t['availableSpecial'] ?? null;
					$sl = is_array( $sp ) ? ( $sp['label'] ?? $sp['promotionName'] ?? '' ) : '';
					if ( $sl === $promo_label ) { $is_featured_promo = true; break; }
				}
			}

			$tier_modals[] = $item;
			$rendered++;
		?>

		<?php if ( $display_format === 'list' ) : ?>
		<!-- ========== LIST CARD (grouped) ========== -->
		<div class="dsu-list-card dsu-list-card--grouped" role="listitem"
		     data-category="<?php echo esc_attr( $good_size_cat ); ?>"
		     data-unit-type="<?php echo esc_attr( $good_unit_type ); ?>"
		     data-has-promo="<?php echo $is_featured_promo ? '1' : '0'; ?>">

			<div class="dsu-list-image-col">
				<div class="dsu-list-image-wrap">
					<img src="<?php echo esc_url( $good_image ); ?>"
					     alt="<?php echo esc_attr( $gname ); ?>"
					     loading="lazy" />
				</div>
			</div>

			<div class="dsu-list-info-col">
				<h3 class="dsu-unit-size"><?php echo esc_html( $gname ); ?></h3>
				<?php if ( ! empty( $good_features ) ) : ?>
					<div class="dsu-feat-icons-row<?php echo esc_attr( $feat_size_class ); ?>"
					     role="list"
					     aria-label="<?php esc_attr_e( 'Features', 'dynamic-storage-units' ); ?>">
						<?php foreach ( $good_features as $feat ) : ?>
							<?php echo DSU_Feature_Icons::render_tile( $feat ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="dsu-list-rate-col">
				<div class="dsu-pricing-grouped">
					<div class="dsu-from-label"><?php esc_html_e( 'From', 'dynamic-storage-units' ); ?></div>
					<?php if ( $from_is_spec ) : ?>
						<div class="dsu-street-rate-strike">$<?php echo esc_html( number_format( $from_regular, 2 ) ); ?><span class="dsu-per-month-sm">/mo</span></div>
					<?php endif; ?>
					<div class="dsu-special-rate-main">$<?php echo esc_html( number_format( $from_price, 2 ) ); ?><span class="dsu-per-month-sm">/mo</span></div>
				</div>
			</div>

			<div class="dsu-list-cta-col">
				<button type="button" class="dsu-btn dsu-btn-primary dsu-open-tier-modal"
				        data-modal="<?php echo esc_attr( $modal_id ); ?>"
				        aria-haspopup="dialog">
					<?php echo esc_html( $grouped_cta ); ?>
				</button>
			</div>

		</div><!-- .dsu-list-card--grouped -->

		<?php else : ?>
		<!-- ========== GRID CARD (grouped) ========== -->
		<div class="dsu-unit-card dsu-unit-card--grouped" role="listitem"
		     data-category="<?php echo esc_attr( $good_size_cat ); ?>"
		     data-unit-type="<?php echo esc_attr( $good_unit_type ); ?>"
		     data-has-promo="<?php echo $is_featured_promo ? '1' : '0'; ?>">

			<div class="dsu-card-image">
				<img src="<?php echo esc_url( $good_image ); ?>"
				     alt="<?php echo esc_attr( $gname ); ?>"
				     loading="lazy" />
			</div>

			<div class="dsu-card-body">

				<h3 class="dsu-unit-size"><?php echo esc_html( $gname ); ?></h3>

				<?php if ( ! empty( $good_features ) ) : ?>
					<div class="dsu-feat-icons-row<?php echo esc_attr( $feat_size_class ); ?>"
					     role="list"
					     aria-label="<?php esc_attr_e( 'Features', 'dynamic-storage-units' ); ?>">
						<?php foreach ( $good_features as $feat ) : ?>
							<?php echo DSU_Feature_Icons::render_tile( $feat ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="dsu-price dsu-price--grouped">
					<span class="dsu-from-label"><?php esc_html_e( 'From', 'dynamic-storage-units' ); ?></span>
					$<?php echo esc_html( number_format( $from_price, 2 ) ); ?><span class="dsu-per-month">/month</span>
					<?php if ( $from_is_spec ) : ?>
						<span class="dsu-original-price">$<?php echo esc_html( number_format( $from_regular, 2 ) ); ?></span>
					<?php endif; ?>
				</div>

				<div class="dsu-cta-buttons">
					<button type="button" class="dsu-btn dsu-btn-primary dsu-open-tier-modal"
					        data-modal="<?php echo esc_attr( $modal_id ); ?>"
					        aria-haspopup="dialog">
						<?php echo esc_html( $grouped_cta ); ?>
					</button>
				</div>

			</div>
		</div><!-- .dsu-unit-card--grouped -->

		<?php endif; // grid vs list (grouped) ?>

	<?php endif; // single vs grouped ?>

	<?php endforeach; // display_units ?>

	<?php if ( $rendered === 0 ) : ?>
		<p class="dsu-no-results" role="status" aria-live="polite">
			<?php esc_html_e( 'No units are currently available.', 'dynamic-storage-units' ); ?>
		</p>
	<?php endif; ?>
</div>

<?php foreach ( $tier_modals as $tier_item ) :
	$modal_id     = $tier_item['modal_id'];
	$name         = $tier_item['name'];
	$tiers        = $tier_item['tiers'];
	$tier_labels  = $tier_item['tier_labels'];
	$tier_classes = $tier_item['tier_classes'];
	$had_overflow = $tier_item['had_overflow'];
	include DSU_PLUGIN_DIR . 'templates/tier-modal.php';
endforeach; ?>

<?php include DSU_PLUGIN_DIR . 'templates/waitlist-modal.php'; ?>
