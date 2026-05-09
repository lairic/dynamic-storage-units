<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
$api_settings   = get_option( DSU_OPTION_API, [] );
$facility_code  = $api_settings['facility_code'] ?? '';
$group_map      = get_option( DSU_OPTION_IMAGES, [] );
$all_categories = dsu_get_size_categories();
?>

<div class="dsu-images-section">
	<h2><?php esc_html_e( 'Unit Group Mapping', 'dynamic-storage-units' ); ?></h2>

	<?php if ( empty( $facility_code ) ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php printf(
					wp_kses( __( 'No Facility Code is set. <a href="%s">Go to API Settings</a> to add one.', 'dynamic-storage-units' ), [ 'a' => [ 'href' => [] ] ] ),
					esc_url( add_query_arg( 'tab', 'api' ) )
				); ?>
			</p>
		</div>
	<?php else : ?>
		<p>
			<?php printf(
				esc_html__( 'Showing groups for facility: %s. Assign images and size categories, then click Save. Features are pulled automatically from the API.', 'dynamic-storage-units' ),
				'<strong>' . esc_html( $facility_code ) . '</strong>'
			); ?>
			<button type="button" id="dsu-refresh-groups" class="button button-link" style="margin-left:12px;">
				&#8635; <?php esc_html_e( 'Refresh from API', 'dynamic-storage-units' ); ?>
			</button>
			<button type="button" id="dsu-bust-cache" class="button button-link" style="margin-left:8px;">
				<?php esc_html_e( 'Clear Cache', 'dynamic-storage-units' ); ?>
			</button>
			<span id="dsu-fetch-status" class="dsu-status-msg" style="margin-left:8px;"></span>
		</p>
	<?php endif; ?>

	<form method="post" action="options.php" id="dsu-images-form">
		<?php settings_fields( 'dsu_images_group' ); ?>

		<div id="dsu-groups-table-wrap" <?php echo empty( $group_map ) ? 'style="display:none;"' : ''; ?>>
			<table class="widefat dsu-image-table" id="dsu-groups-table">
				<thead>
					<tr>
						<th class="dsu-sort-th" data-sort="label" style="cursor:pointer;"><?php esc_html_e( 'Label', 'dynamic-storage-units' ); ?> <span class="dsu-sort-ind"></span></th>
						<th class="dsu-sort-th" data-sort="sqft" style="cursor:pointer;"><?php esc_html_e( 'W × D', 'dynamic-storage-units' ); ?> <span class="dsu-sort-ind"></span></th>
						<th class="dsu-sort-th dsu-live-col" data-sort="avail" style="cursor:pointer;"><?php esc_html_e( 'Avail / Total', 'dynamic-storage-units' ); ?> <span class="dsu-sort-ind"></span></th>
						<th class="dsu-sort-th dsu-live-col" data-sort="rate" style="cursor:pointer;"><?php esc_html_e( 'Rate', 'dynamic-storage-units' ); ?> <span class="dsu-sort-ind"></span></th>
						<th class="dsu-sort-th dsu-live-col" data-sort="special" style="cursor:pointer;"><?php esc_html_e( 'Special', 'dynamic-storage-units' ); ?> <span class="dsu-sort-ind"></span></th>
						<th><?php esc_html_e( 'Image', 'dynamic-storage-units' ); ?></th>
						<th class="dsu-sort-th" data-sort="category" style="cursor:pointer;"><?php esc_html_e( 'Size Category', 'dynamic-storage-units' ); ?> <span class="dsu-sort-ind"></span></th>
						<th><?php esc_html_e( 'Unit Type', 'dynamic-storage-units' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'dynamic-storage-units' ); ?></th>
					</tr>
				</thead>
				<tbody id="dsu-groups-tbody">
					<?php foreach ( $group_map as $group_id => $data ) :
						$image_url = $data['image_url'] ?? '';
						$label     = $data['label'] ?? $group_id;
					?>
					<tr data-group-id="<?php echo esc_attr( $group_id ); ?>">
						<td>
							<strong><?php echo esc_html( $label ); ?></strong><br>
							<code style="font-size:11px;color:#888;"><?php echo esc_html( $group_id ); ?></code>
							<input type="hidden"
							       name="<?php echo DSU_OPTION_IMAGES; ?>[<?php echo esc_attr( $group_id ); ?>][label]"
							       value="<?php echo esc_attr( $label ); ?>" />
						</td>
						<td class="dsu-col-dims">—</td>
						<td class="dsu-live-col">—</td>
						<td class="dsu-live-col">—</td>
						<td class="dsu-live-col">—</td>
						<td class="dsu-col-image">
							<?php if ( $image_url ) : ?>
								<img src="<?php echo esc_url( $image_url ); ?>" class="dsu-thumb" alt="" />
							<?php endif; ?>
							<input type="hidden"
							       name="<?php echo DSU_OPTION_IMAGES; ?>[<?php echo esc_attr( $group_id ); ?>][image_url]"
							       value="<?php echo esc_attr( $image_url ); ?>"
							       class="dsu-image-url-input" />
						</td>
						<td>
							<select name="<?php echo DSU_OPTION_IMAGES; ?>[<?php echo esc_attr( $group_id ); ?>][size_category]" class="dsu-size-cat-select">
								<option value=""><?php esc_html_e( '— None —', 'dynamic-storage-units' ); ?></option>
								<?php foreach ( $all_categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat['slug'] ); ?>"
									        <?php selected( $data['size_category'] ?? '', $cat['slug'] ); ?>>
										<?php echo esc_html( $cat['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<select name="<?php echo DSU_OPTION_IMAGES; ?>[<?php echo esc_attr( $group_id ); ?>][unit_type]" class="dsu-unit-type-select">
								<option value=""><?php esc_html_e( '— None —', 'dynamic-storage-units' ); ?></option>
								<?php foreach ( dsu_get_unit_types() as $ut ) : ?>
									<option value="<?php echo esc_attr( $ut['slug'] ); ?>"
									        <?php selected( $data['unit_type'] ?? '', $ut['slug'] ); ?>>
										<?php echo esc_html( $ut['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<button type="button" class="button button-small dsu-select-image">
								<?php esc_html_e( 'Select Image', 'dynamic-storage-units' ); ?>
							</button>
							<button type="button" class="button-link dsu-remove-image">
								<?php esc_html_e( 'Remove', 'dynamic-storage-units' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Group Mappings', 'dynamic-storage-units' ) ); ?>
		</div>

		<?php if ( empty( $group_map ) ) : ?>
			<p id="dsu-no-groups-msg" class="dsu-no-groups">
				<?php esc_html_e( 'Loading groups from API…', 'dynamic-storage-units' ); ?>
			</p>
		<?php endif; ?>
	</form>
</div>
