<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
$configs        = get_option( DSU_OPTION_CONFIGS, [] );
$default_config = get_option( DSU_OPTION_DEFAULT_CONFIG, '' );
?>

<!-- === LIST VIEW (default) === -->
<div id="dsu-saved-list-wrap">

	<h2><?php esc_html_e( 'Saved Displays', 'dynamic-storage-units' ); ?></h2>
	<p><?php esc_html_e( 'All saved display configurations. Copy a shortcode to use it on any page or post.', 'dynamic-storage-units' ); ?></p>

	<div id="dsu-saved-status" class="notice" style="display:none;" aria-live="polite"></div>

	<?php if ( empty( $configs ) ) : ?>
		<div class="notice notice-info inline" style="margin-top:16px;">
			<p>
				<?php printf(
					wp_kses( __( 'No configurations saved yet. <a href="%s">Go to Display Configuration Builder</a> to create one.', 'dynamic-storage-units' ), [ 'a' => [ 'href' => [] ] ] ),
					esc_url( add_query_arg( 'tab', 'configs' ) )
				); ?>
			</p>
		</div>
	<?php else : ?>

	<table class="widefat striped dsu-saved-table" role="table" aria-label="<?php esc_attr_e( 'Saved display configurations', 'dynamic-storage-units' ); ?>">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Name', 'dynamic-storage-units' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Layout', 'dynamic-storage-units' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Shortcode', 'dynamic-storage-units' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Default', 'dynamic-storage-units' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'dynamic-storage-units' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $configs as $i => $config ) :
			$name      = $config['name'] ?? '';
			$format    = ucfirst( $config['display_format'] ?? 'grid' );
			$shortcode = '[storage_units config="' . esc_attr( $name ) . '"]';
		?>
			<tr data-index="<?php echo esc_attr( $i ); ?>">
				<td><strong><?php echo esc_html( $name ); ?></strong></td>
				<td><?php echo esc_html( $format ); ?></td>
				<td>
					<div class="dsu-shortcode-copy-row">
						<input type="text" class="dsu-shortcode-input"
						       value="<?php echo esc_attr( $shortcode ); ?>"
						       readonly
						       aria-label="<?php echo esc_attr( sprintf( __( 'Shortcode for %s', 'dynamic-storage-units' ), $name ) ); ?>" />
						<button type="button" class="button button-small dsu-copy-shortcode"
						        aria-label="<?php echo esc_attr( sprintf( __( 'Copy shortcode for %s', 'dynamic-storage-units' ), $name ) ); ?>">
							<?php esc_html_e( 'Copy', 'dynamic-storage-units' ); ?>
						</button>
					</div>
				</td>
				<td>
					<?php if ( $name === $default_config ) : ?>
						<span class="dsu-default-badge" aria-label="<?php esc_attr_e( 'Default config', 'dynamic-storage-units' ); ?>">
							⭐ <?php esc_html_e( 'Default', 'dynamic-storage-units' ); ?>
						</span>
					<?php else : ?>
						<button type="button" class="button button-small dsu-set-default-btn"
						        data-config-name="<?php echo esc_attr( $name ); ?>">
							<?php esc_html_e( 'Set as Default', 'dynamic-storage-units' ); ?>
						</button>
					<?php endif; ?>
				</td>
				<td>
					<button type="button" class="button button-small dsu-edit-config-btn"
					        data-index="<?php echo esc_attr( $i ); ?>"
					        aria-label="<?php echo esc_attr( sprintf( __( 'Edit %s', 'dynamic-storage-units' ), $name ) ); ?>">
						<?php esc_html_e( 'Edit', 'dynamic-storage-units' ); ?>
					</button>
					<button type="button" class="button-link dsu-delete-config-btn"
					        data-config-name="<?php echo esc_attr( $name ); ?>"
					        style="color:#b32d2e; margin-left:10px;"
					        aria-label="<?php echo esc_attr( sprintf( __( 'Delete %s', 'dynamic-storage-units' ), $name ) ); ?>">
						<?php esc_html_e( 'Delete', 'dynamic-storage-units' ); ?>
					</button>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php endif; ?>
</div>

<!-- === EDIT VIEW (builder-style, hidden by default) === -->
<div id="dsu-saved-edit-view" style="display:none;">

	<div class="dsu-saved-edit-nav">
		<button type="button" id="dsu-saved-back-btn" class="button">
			&#8592; <?php esc_html_e( 'Back to Saved Displays', 'dynamic-storage-units' ); ?>
		</button>
		<h2 id="dsu-saved-edit-title"></h2>
	</div>

	<div class="dsu-builder-wrap">

		<!-- Left: edit form (one panel per config) -->
		<div class="dsu-builder-left">
			<?php foreach ( $configs as $i => $config ) : ?>
			<div class="dsu-edit-panel" data-index="<?php echo esc_attr( $i ); ?>" style="display:none;">
				<form class="dsu-edit-config-form"
				      data-original-name="<?php echo esc_attr( $config['name'] ?? '' ); ?>">
					<?php
					$index   = $i;
					$context = 'edit';
					include __DIR__ . '/config-row.php';
					?>
					<div class="dsu-edit-submit-row">
						<button type="submit" class="button button-primary button-large dsu-save-config-btn">
							<?php esc_html_e( 'Save Changes', 'dynamic-storage-units' ); ?>
						</button>
						<span class="dsu-status-msg dsu-edit-status" aria-live="polite" style="margin-left:12px;"></span>
					</div>
				</form>
			</div>
			<?php endforeach; ?>
		</div>

		<!-- Right: live preview -->
		<div class="dsu-builder-right">
			<div class="dsu-preview-panel">
				<div class="dsu-preview-header">
					<span class="dsu-preview-title"><?php esc_html_e( 'Live Preview', 'dynamic-storage-units' ); ?></span>
					<span style="font-size:11px;color:#888;"><?php esc_html_e( 'Sample units — special, plain, sold out', 'dynamic-storage-units' ); ?></span>
				</div>
				<div class="dsu-preview-body">
					<div id="dsu-preview-output-saved" aria-live="polite"></div>
				</div>
				<p class="dsu-preview-note">
					<?php esc_html_e( 'Preview uses sample data. Change Layout to update.', 'dynamic-storage-units' ); ?>
				</p>
			</div>
		</div>

	</div>
</div>
