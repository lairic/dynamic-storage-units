<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
// $index, $config, and optional $context ('builder'|'edit') must be set by the including file.
$context  = $context ?? 'builder';
$opt      = DSU_OPTION_CONFIGS;
$name     = $config['name'] ?? '';
$fac_code = $config['facility_code'] ?? '';
$f_label  = $config['filter_label'] ?? '';
$f_spec   = ! empty( $config['filter_has_special'] );
$max      = $config['max_units'] ?? '';
$sort     = $config['sort'] ?? '';
$format   = $config['display_format'] ?? 'grid';
$soldout  = $config['soldout_handling'] ?? 'hide';
$wl_em    = $config['waitlist_email'] ?? '';
$wl_sub   = $config['waitlist_subject'] ?? '';
$wl_msg   = $config['waitlist_message'] ?? '';
$show_tiles         = ! empty( $config['show_size_tiles'] );
$tiles_handling     = $config['unavailable_tile_handling'] ?? 'dim';
$tiles_alignment    = $config['tiles_alignment'] ?? 'left';
$hide_tiles_mobile  = ! empty( $config['hide_tiles_mobile'] );
$feat_tag_size      = $config['feature_tag_size'] ?? 'sm';
$show_promo_bar     = ! empty( $config['show_promo_bar'] );
$hide_promo_mobile  = ! empty( $config['hide_promo_mobile'] );
$utf_size           = $config['unit_type_filter_size'] ?? 'md';
$utf_all_label      = $config['unit_type_all_label'] ?? '';
$hide_utf_mobile    = ! empty( $config['hide_utf_mobile'] );
$hide_all_tile      = ! empty( $config['hide_all_tile'] );
$img_size           = $config['img_size']     ?? 'md';
$title_size         = $config['title_size']   ?? 'md';
$special_size       = $config['special_size'] ?? 'md';
$price_size         = $config['price_size']   ?? 'md';
$scarcity_size      = $config['scarcity_size'] ?? 'md';

$sort_opts = [
	''             => __( '— Default (API order) —', 'dynamic-storage-units' ),
	'price_asc'    => __( 'Price: Low → High', 'dynamic-storage-units' ),
	'price_desc'   => __( 'Price: High → Low', 'dynamic-storage-units' ),
	'availability' => __( 'Availability: Most first', 'dynamic-storage-units' ),
	'label'        => __( 'Label: A → Z', 'dynamic-storage-units' ),
];

$field_prefix = $context === 'edit' ? 'dsu_edit_config' : $opt . '[' . $index . ']';
?>
<div class="dsu-config-row postbox" data-index="<?php echo esc_attr( $index ); ?>">
	<div class="dsu-config-header">
		<span class="dsu-config-title">
			<?php echo $name ? esc_html( $name ) : esc_html__( 'New Configuration', 'dynamic-storage-units' ); ?>
		</span>
		<?php if ( $context === 'builder' ) : ?>
		<button type="button" class="button-link dsu-toggle-config">&#9660;</button>
		<button type="button" class="button-link dsu-delete-config" style="color:#b32d2e;">
			<?php esc_html_e( 'Remove', 'dynamic-storage-units' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<div class="dsu-config-body">

		<?php if ( $context === 'edit' && $name ) : ?>
		<div class="dsu-shortcode-display">
			<label><?php esc_html_e( 'Shortcode', 'dynamic-storage-units' ); ?></label>
			<div class="dsu-shortcode-copy-row">
				<input type="text" class="dsu-shortcode-input regular-text"
				       value="<?php echo esc_attr( '[storage_units config="' . $name . '"]' ); ?>"
				       readonly />
				<button type="button" class="button button-small dsu-copy-shortcode">
					<?php esc_html_e( 'Copy', 'dynamic-storage-units' ); ?>
				</button>
			</div>
		</div>
		<?php endif; ?>

		<h4><?php esc_html_e( 'General', 'dynamic-storage-units' ); ?></h4>
		<table class="form-table form-table--nested">
			<tr>
				<th><label><?php esc_html_e( 'Name', 'dynamic-storage-units' ); ?> <span class="required">*</span></label></th>
				<td>
					<input type="text" name="<?php echo $field_prefix; ?>[name]"
					       value="<?php echo esc_attr( $name ); ?>" class="regular-text dsu-config-name-input"
					       placeholder="<?php esc_attr_e( 'e.g. main-storage', 'dynamic-storage-units' ); ?>" required />
					<p class="description"><?php esc_html_e( 'Used in shortcode: [storage_units config="NAME"]', 'dynamic-storage-units' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Facility Code Override', 'dynamic-storage-units' ); ?></label></th>
				<td>
					<input type="text" name="<?php echo $field_prefix; ?>[facility_code]"
					       value="<?php echo esc_attr( $fac_code ); ?>" class="regular-text"
					       placeholder="<?php esc_attr_e( 'Leave blank to use the default from Settings', 'dynamic-storage-units' ); ?>" />
					<p class="description"><?php esc_html_e( 'Only needed if this configuration shows a different facility.', 'dynamic-storage-units' ); ?></p>
				</td>
			</tr>
		</table>

		<h4><?php esc_html_e( 'Display Options', 'dynamic-storage-units' ); ?></h4>
		<table class="form-table form-table--nested">
			<tr>
				<th><?php esc_html_e( 'Layout', 'dynamic-storage-units' ); ?></th>
				<td>
					<label style="margin-right:20px;">
						<input type="radio" name="<?php echo $field_prefix; ?>[display_format]"
						       value="grid" <?php checked( $format, 'grid' ); ?> />
						<?php esc_html_e( 'Grid', 'dynamic-storage-units' ); ?>
					</label>
					<label>
						<input type="radio" name="<?php echo $field_prefix; ?>[display_format]"
						       value="list" <?php checked( $format, 'list' ); ?> />
						<?php esc_html_e( 'List', 'dynamic-storage-units' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Max Groups to Show', 'dynamic-storage-units' ); ?></label></th>
				<td>
					<input type="number" name="<?php echo $field_prefix; ?>[max_units]"
					       value="<?php echo esc_attr( $max ); ?>" class="small-text" min="0" />
					<p class="description"><?php esc_html_e( '0 = show all.', 'dynamic-storage-units' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Sort By', 'dynamic-storage-units' ); ?></label></th>
				<td>
					<select name="<?php echo $field_prefix; ?>[sort]">
						<?php foreach ( $sort_opts as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $sort, $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Size Category Tiles', 'dynamic-storage-units' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo $field_prefix; ?>[show_size_tiles]"
						       value="1" <?php checked( $show_tiles ); ?> />
						<?php esc_html_e( 'Show size category tiles above this listing', 'dynamic-storage-units' ); ?>
					</label>
					<div class="dsu-tiles-handling-wrap" style="<?php echo ! $show_tiles ? 'display:none;' : ''; ?> margin-top:8px;">
						<label style="margin-right:16px;">
							<input type="radio" name="<?php echo $field_prefix; ?>[unavailable_tile_handling]"
							       value="dim" <?php checked( $tiles_handling, 'dim' ); ?> />
							<?php esc_html_e( 'Dim unavailable categories', 'dynamic-storage-units' ); ?>
						</label>
						<label>
							<input type="radio" name="<?php echo $field_prefix; ?>[unavailable_tile_handling]"
							       value="hide" <?php checked( $tiles_handling, 'hide' ); ?> />
							<?php esc_html_e( 'Hide unavailable categories', 'dynamic-storage-units' ); ?>
						</label>
						<div style="margin-top:8px;">
							<label style="margin-right:16px;">
								<input type="radio" name="<?php echo $field_prefix; ?>[tiles_alignment]"
								       value="left" <?php checked( $tiles_alignment, 'left' ); ?> />
								<?php esc_html_e( 'Left-align tiles', 'dynamic-storage-units' ); ?>
							</label>
							<label>
								<input type="radio" name="<?php echo $field_prefix; ?>[tiles_alignment]"
								       value="center" <?php checked( $tiles_alignment, 'center' ); ?> />
								<?php esc_html_e( 'Center tiles', 'dynamic-storage-units' ); ?>
							</label>
						</div>
						<div style="margin-top:8px;">
							<label>
								<input type="checkbox" name="<?php echo $field_prefix; ?>[hide_tiles_mobile]"
								       value="1" <?php checked( $hide_tiles_mobile ); ?> />
								<?php esc_html_e( 'Hide size category tiles on mobile', 'dynamic-storage-units' ); ?>
							</label>
						</div>
						<div style="margin-top:8px;">
							<label>
								<input type="checkbox" name="<?php echo $field_prefix; ?>[hide_all_tile]"
								       value="1" <?php checked( $hide_all_tile ); ?> />
								<?php esc_html_e( 'Hide the "All Units" tile', 'dynamic-storage-units' ); ?>
							</label>
						</div>
					</div>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Promotion Bar', 'dynamic-storage-units' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo $field_prefix; ?>[show_promo_bar]"
						       value="1" <?php checked( $show_promo_bar ); ?> />
						<?php esc_html_e( 'Show a clickable promotion bar above the unit listing (hidden automatically when no active promotions exist)', 'dynamic-storage-units' ); ?>
					</label>
					<div style="margin-top:6px;">
						<label>
							<input type="checkbox" name="<?php echo $field_prefix; ?>[hide_promo_mobile]"
							       value="1" <?php checked( $hide_promo_mobile ); ?> />
							<?php esc_html_e( 'Hide promotion bar on mobile', 'dynamic-storage-units' ); ?>
						</label>
					</div>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Unit Type Filter', 'dynamic-storage-units' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo $field_prefix; ?>[show_unit_type_filter]"
						       value="1" <?php checked( ! empty( $config['show_unit_type_filter'] ) ); ?> />
						<?php esc_html_e( 'Show unit type filter bar above the listing (e.g. Storage / Parking)', 'dynamic-storage-units' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Can be used independently or stacked above size category tiles.', 'dynamic-storage-units' ); ?></p>
					<div style="margin-top:8px;">
						<label for="<?php echo esc_attr( $field_prefix . '_utf_all_label' ); ?>" style="margin-right:8px;font-weight:600;">
							<?php esc_html_e( '"All" Button Label:', 'dynamic-storage-units' ); ?>
						</label>
						<input type="text" id="<?php echo esc_attr( $field_prefix . '_utf_all_label' ); ?>"
						       name="<?php echo $field_prefix; ?>[unit_type_all_label]"
						       value="<?php echo esc_attr( $utf_all_label ); ?>"
						       class="regular-text" placeholder="<?php esc_attr_e( 'All', 'dynamic-storage-units' ); ?>"
						       style="max-width:180px;" />
					</div>
					<div style="margin-top:8px;">
						<label>
							<input type="checkbox" name="<?php echo $field_prefix; ?>[hide_utf_mobile]"
							       value="1" <?php checked( $hide_utf_mobile ); ?> />
							<?php esc_html_e( 'Hide unit type filter on mobile', 'dynamic-storage-units' ); ?>
						</label>
					</div>
					<div style="margin-top:8px;">
						<?php foreach ( [ 'sm' => __( 'Small', 'dynamic-storage-units' ), 'md' => __( 'Medium', 'dynamic-storage-units' ), 'lg' => __( 'Large', 'dynamic-storage-units' ) ] as $sz => $sz_label ) : ?>
						<label style="margin-right:18px;">
							<input type="radio"
							       name="<?php echo esc_attr( $field_prefix . '[unit_type_filter_size]' ); ?>"
							       value="<?php echo esc_attr( $sz ); ?>"
							       <?php checked( $utf_size, $sz ); ?> />
							<?php echo esc_html( $sz_label ); ?>
						</label>
						<?php endforeach; ?>
						<span class="description" style="margin-left:4px;"><?php esc_html_e( 'Filter button size', 'dynamic-storage-units' ); ?></span>
					</div>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Feature Tag Size', 'dynamic-storage-units' ); ?></th>
				<td>
					<label style="margin-right:18px;">
						<input type="radio" name="<?php echo $field_prefix; ?>[feature_tag_size]"
						       value="sm" <?php checked( $feat_tag_size, 'sm' ); ?> />
						<?php esc_html_e( 'Small', 'dynamic-storage-units' ); ?>
					</label>
					<label style="margin-right:18px;">
						<input type="radio" name="<?php echo $field_prefix; ?>[feature_tag_size]"
						       value="md" <?php checked( $feat_tag_size, 'md' ); ?> />
						<?php esc_html_e( 'Medium', 'dynamic-storage-units' ); ?>
					</label>
					<label>
						<input type="radio" name="<?php echo $field_prefix; ?>[feature_tag_size]"
						       value="lg" <?php checked( $feat_tag_size, 'lg' ); ?> />
						<?php esc_html_e( 'Large', 'dynamic-storage-units' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Controls the font size and padding of feature tags on unit cards. Small is the default compact size.', 'dynamic-storage-units' ); ?></p>
				</td>
			</tr>
		</table>

		<h4><?php esc_html_e( 'Text & Image Sizing', 'dynamic-storage-units' ); ?></h4>
		<table class="form-table form-table--nested">
			<?php
			$size_rows = [
				[ 'key' => 'img_size',      'val' => $img_size,      'label' => __( 'Image Size', 'dynamic-storage-units' ),          'desc' => __( 'Controls the aspect ratio of the unit group image in grid cards, and the image column width in list cards.', 'dynamic-storage-units' ) ],
				[ 'key' => 'title_size',    'val' => $title_size,    'label' => __( 'Title Size', 'dynamic-storage-units' ),          'desc' => __( 'Controls the unit name / group heading font size.', 'dynamic-storage-units' ) ],
				[ 'key' => 'special_size',  'val' => $special_size,  'label' => __( 'Special Label Size', 'dynamic-storage-units' ),  'desc' => __( 'Controls the promotion callout box and inline special label text.', 'dynamic-storage-units' ) ],
				[ 'key' => 'price_size',    'val' => $price_size,    'label' => __( 'Price & Detail Size', 'dynamic-storage-units' ), 'desc' => __( 'Controls the main rate figure, crossed-out street rate, "Online Only Rate" label, and "no credit card needed" line.', 'dynamic-storage-units' ) ],
				[ 'key' => 'scarcity_size', 'val' => $scarcity_size, 'label' => __( 'Scarcity Text Size', 'dynamic-storage-units' ), 'desc' => __( 'Controls the "Only X left!" availability text on cards.', 'dynamic-storage-units' ) ],
			];
			foreach ( $size_rows as $row ) :
			?>
			<tr>
				<th><?php echo esc_html( $row['label'] ); ?></th>
				<td>
					<?php foreach ( [ 'sm' => __( 'Small', 'dynamic-storage-units' ), 'md' => __( 'Medium', 'dynamic-storage-units' ), 'lg' => __( 'Large', 'dynamic-storage-units' ) ] as $sz => $sz_label ) : ?>
					<label style="margin-right:18px;">
						<input type="radio"
						       name="<?php echo esc_attr( $field_prefix . '[' . $row['key'] . ']' ); ?>"
						       value="<?php echo esc_attr( $sz ); ?>"
						       <?php checked( $row['val'], $sz ); ?> />
						<?php echo esc_html( $sz_label ); ?>
					</label>
					<?php endforeach; ?>
					<p class="description"><?php echo esc_html( $row['desc'] ); ?></p>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>

		<h4><?php esc_html_e( 'Filters', 'dynamic-storage-units' ); ?></h4>
		<table class="form-table form-table--nested">
			<tr>
				<th><label><?php esc_html_e( 'Label Contains', 'dynamic-storage-units' ); ?></label></th>
				<td>
					<input type="text" name="<?php echo $field_prefix; ?>[filter_label]"
					       value="<?php echo esc_attr( $f_label ); ?>" class="regular-text"
					       placeholder="<?php esc_attr_e( 'e.g. 5x10 or Climate', 'dynamic-storage-units' ); ?>" />
					<p class="description"><?php esc_html_e( 'Only show groups whose label contains this text. Leave blank to show all.', 'dynamic-storage-units' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Promotions Only', 'dynamic-storage-units' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo $field_prefix; ?>[filter_has_special]"
						       value="1" <?php checked( $f_spec ); ?> />
						<?php esc_html_e( 'Only show groups that currently have a special/promotion', 'dynamic-storage-units' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h4><?php esc_html_e( 'Availability Rules', 'dynamic-storage-units' ); ?></h4>
		<table class="form-table form-table--nested">
			<tr>
				<th><label><?php esc_html_e( 'Sold-Out Handling', 'dynamic-storage-units' ); ?></label></th>
				<td>
					<label>
						<input type="radio" name="<?php echo $field_prefix; ?>[soldout_handling]"
						       value="hide" <?php checked( $soldout, 'hide' ); ?> class="dsu-soldout-radio" />
						<?php esc_html_e( 'Hide sold-out groups', 'dynamic-storage-units' ); ?>
					</label><br />
					<label>
						<input type="radio" name="<?php echo $field_prefix; ?>[soldout_handling]"
						       value="waitlist" <?php checked( $soldout, 'waitlist' ); ?> class="dsu-soldout-radio" />
						<?php esc_html_e( 'Show with waitlist option', 'dynamic-storage-units' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<div class="dsu-waitlist-settings" style="<?php echo $soldout !== 'waitlist' ? 'display:none;' : ''; ?>">
			<h4><?php esc_html_e( 'Waitlist Settings', 'dynamic-storage-units' ); ?></h4>
			<table class="form-table form-table--nested">
				<tr>
					<th><label><?php esc_html_e( 'Email Recipient', 'dynamic-storage-units' ); ?></label></th>
					<td>
						<input type="email" name="<?php echo $field_prefix; ?>[waitlist_email]"
						       value="<?php echo esc_attr( $wl_em ); ?>" class="regular-text"
						       placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Email Subject', 'dynamic-storage-units' ); ?></label></th>
					<td>
						<input type="text" name="<?php echo $field_prefix; ?>[waitlist_subject]"
						       value="<?php echo esc_attr( $wl_sub ); ?>" class="regular-text"
						       placeholder="<?php esc_attr_e( 'New Waitlist Signup', 'dynamic-storage-units' ); ?>" />
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Message Template', 'dynamic-storage-units' ); ?></label></th>
					<td>
						<textarea name="<?php echo $field_prefix; ?>[waitlist_message]"
						          class="large-text" rows="6"
						          placeholder="<?php esc_attr_e( 'Leave blank for default. Tokens: {name}, {email}, {phone}, {group_label}, {group_id}, {facility_code}', 'dynamic-storage-units' ); ?>"><?php echo esc_textarea( $wl_msg ); ?></textarea>
					</td>
				</tr>
			</table>
		</div>

	</div><!-- .dsu-config-body -->
</div><!-- .dsu-config-row -->
