<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
$unit_types = dsu_get_unit_types();
$categories = dsu_get_size_categories();
$ut_opt     = DSU_OPTION_UNIT_TYPES;
?>

<h2><?php esc_html_e( 'Unit Types', 'dynamic-storage-units' ); ?></h2>
<p><?php esc_html_e( 'Define the unit types used for filtering on the front end. Assign a type to each size category and unit group below.', 'dynamic-storage-units' ); ?></p>

<form method="post" action="options.php" id="dsu-unit-types-form">
	<?php settings_fields( 'dsu_unit_types_group' ); ?>
	<table class="widefat" id="dsu-unit-types-table" style="max-width:480px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Label', 'dynamic-storage-units' ); ?></th>
				<th><?php esc_html_e( 'Slug', 'dynamic-storage-units' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'dynamic-storage-units' ); ?></th>
			</tr>
		</thead>
		<tbody id="dsu-unit-types-tbody">
			<?php foreach ( $unit_types as $i => $ut ) : ?>
			<tr data-index="<?php echo esc_attr( $i ); ?>">
				<td>
					<input type="text" name="<?php echo $ut_opt; ?>[<?php echo $i; ?>][label]"
					       value="<?php echo esc_attr( $ut['label'] ?? '' ); ?>"
					       class="regular-text dsu-ut-label-input" required />
				</td>
				<td>
					<input type="text" name="<?php echo $ut_opt; ?>[<?php echo $i; ?>][slug]"
					       value="<?php echo esc_attr( $ut['slug'] ?? '' ); ?>"
					       class="regular-text dsu-ut-slug-input" required />
				</td>
				<td>
					<button type="button" class="button-link dsu-delete-unit-type" style="color:#b32d2e;">
						<?php esc_html_e( 'Delete', 'dynamic-storage-units' ); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p style="margin-top:10px;">
		<button type="button" id="dsu-add-unit-type" class="button button-secondary">
			+ <?php esc_html_e( 'Add Unit Type', 'dynamic-storage-units' ); ?>
		</button>
	</p>
	<?php submit_button( __( 'Save Unit Types', 'dynamic-storage-units' ) ); ?>
</form>

<script type="text/html" id="dsu-unit-type-row-template">
<tr data-index="__UT_INDEX__">
	<td><input type="text" name="<?php echo $ut_opt; ?>[__UT_INDEX__][label]" class="regular-text dsu-ut-label-input" required /></td>
	<td><input type="text" name="<?php echo $ut_opt; ?>[__UT_INDEX__][slug]" class="regular-text dsu-ut-slug-input" required /></td>
	<td><button type="button" class="button-link dsu-delete-unit-type" style="color:#b32d2e;"><?php esc_html_e( 'Delete', 'dynamic-storage-units' ); ?></button></td>
</tr>
</script>

<hr style="margin:28px 0;">

<h2><?php esc_html_e( 'Size Categories', 'dynamic-storage-units' ); ?></h2>
<p>
	<?php esc_html_e( 'Define the size categories shown in the category tile row. Assign unit groups to categories in the Unit Group Mapping tab.', 'dynamic-storage-units' ); ?>
	<?php esc_html_e( 'Use Sort Order to control the tile display sequence — lower numbers appear first.', 'dynamic-storage-units' ); ?>
</p>

<form method="post" action="options.php" id="dsu-categories-form">
	<?php settings_fields( 'dsu_categories_group' ); ?>

	<table class="widefat dsu-categories-table" id="dsu-categories-table">
		<thead>
			<tr>
				<th style="width:40px;"><?php esc_html_e( 'Order', 'dynamic-storage-units' ); ?></th>
				<th><?php esc_html_e( 'Label', 'dynamic-storage-units' ); ?></th>
				<th><?php esc_html_e( 'Slug', 'dynamic-storage-units' ); ?></th>
				<th><?php esc_html_e( 'Min sq ft', 'dynamic-storage-units' ); ?></th>
				<th><?php esc_html_e( 'Max sq ft', 'dynamic-storage-units' ); ?></th>
				<th><?php esc_html_e( 'Tile Description', 'dynamic-storage-units' ); ?></th>
				<th><?php esc_html_e( 'Unit Type', 'dynamic-storage-units' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'dynamic-storage-units' ); ?></th>
			</tr>
		</thead>
		<tbody id="dsu-categories-tbody">
			<?php foreach ( $categories as $index => $cat ) :
				$opt = DSU_OPTION_CATEGORIES;
			?>
			<tr data-index="<?php echo esc_attr( $index ); ?>">
				<td>
					<input type="number" name="<?php echo $opt; ?>[<?php echo $index; ?>][sort_order]"
					       value="<?php echo esc_attr( $cat['sort_order'] ?? $index ); ?>"
					       class="small-text" min="0" style="width:52px;" />
				</td>
				<td>
					<input type="text" name="<?php echo $opt; ?>[<?php echo $index; ?>][label]"
					       value="<?php echo esc_attr( $cat['label'] ?? '' ); ?>"
					       class="regular-text dsu-cat-label-input"
					       placeholder="<?php esc_attr_e( 'e.g. Small', 'dynamic-storage-units' ); ?>" required />
				</td>
				<td>
					<input type="text" name="<?php echo $opt; ?>[<?php echo $index; ?>][slug]"
					       value="<?php echo esc_attr( $cat['slug'] ?? '' ); ?>"
					       class="regular-text dsu-cat-slug-input"
					       placeholder="<?php esc_attr_e( 'e.g. small', 'dynamic-storage-units' ); ?>" required />
				</td>
				<td>
					<input type="number" name="<?php echo $opt; ?>[<?php echo $index; ?>][min_sqft]"
					       value="<?php echo esc_attr( $cat['min_sqft'] ?? 0 ); ?>"
					       class="small-text" min="0" />
				</td>
				<td>
					<input type="number" name="<?php echo $opt; ?>[<?php echo $index; ?>][max_sqft]"
					       value="<?php echo esc_attr( $cat['max_sqft'] ?? 9999 ); ?>"
					       class="small-text" min="0" />
				</td>
				<td>
					<input type="text" name="<?php echo $opt; ?>[<?php echo $index; ?>][description]"
					       value="<?php echo esc_attr( $cat['description'] ?? '' ); ?>"
					       class="regular-text"
					       placeholder="<?php esc_attr_e( 'e.g. Fits a 1-bedroom apartment', 'dynamic-storage-units' ); ?>" />
				</td>
				<td>
					<select name="<?php echo $opt; ?>[<?php echo $index; ?>][unit_type]" class="dsu-ut-cat-select">
						<option value=""><?php esc_html_e( '— All —', 'dynamic-storage-units' ); ?></option>
						<?php foreach ( $unit_types as $ut ) : ?>
							<option value="<?php echo esc_attr( $ut['slug'] ); ?>"
							        <?php selected( $cat['unit_type'] ?? '', $ut['slug'] ); ?>>
								<?php echo esc_html( $ut['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<button type="button" class="button-link dsu-delete-category" style="color:#b32d2e;">
						<?php esc_html_e( 'Delete', 'dynamic-storage-units' ); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p style="margin-top:12px;">
		<button type="button" id="dsu-add-category" class="button button-secondary">
			+ <?php esc_html_e( 'Add Category', 'dynamic-storage-units' ); ?>
		</button>
	</p>

	<?php submit_button( __( 'Save Size Categories', 'dynamic-storage-units' ) ); ?>
</form>

<script type="text/html" id="dsu-category-row-template">
<tr data-index="__CAT_INDEX__">
	<td><input type="number" name="<?php echo DSU_OPTION_CATEGORIES; ?>[__CAT_INDEX__][sort_order]" value="0" class="small-text" min="0" style="width:52px;" /></td>
	<td><input type="text" name="<?php echo DSU_OPTION_CATEGORIES; ?>[__CAT_INDEX__][label]" class="regular-text dsu-cat-label-input" placeholder="<?php esc_attr_e( 'e.g. Small', 'dynamic-storage-units' ); ?>" required /></td>
	<td><input type="text" name="<?php echo DSU_OPTION_CATEGORIES; ?>[__CAT_INDEX__][slug]" class="regular-text dsu-cat-slug-input" placeholder="<?php esc_attr_e( 'e.g. small', 'dynamic-storage-units' ); ?>" required /></td>
	<td><input type="number" name="<?php echo DSU_OPTION_CATEGORIES; ?>[__CAT_INDEX__][min_sqft]" value="0" class="small-text" min="0" /></td>
	<td><input type="number" name="<?php echo DSU_OPTION_CATEGORIES; ?>[__CAT_INDEX__][max_sqft]" value="9999" class="small-text" min="0" /></td>
	<td><input type="text" name="<?php echo DSU_OPTION_CATEGORIES; ?>[__CAT_INDEX__][description]" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Fits a 1-bedroom apartment', 'dynamic-storage-units' ); ?>" /></td>
	<td><select name="<?php echo DSU_OPTION_CATEGORIES; ?>[__CAT_INDEX__][unit_type]" class="dsu-ut-cat-select" data-ut-select="cat"></select></td>
	<td><button type="button" class="button-link dsu-delete-category" style="color:#b32d2e;"><?php esc_html_e( 'Delete', 'dynamic-storage-units' ); ?></button></td>
</tr>
</script>
