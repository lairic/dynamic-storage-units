<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="dsu-builder-wrap">

	<!-- Left: Config form -->
	<div class="dsu-builder-left">
		<h2><?php esc_html_e( 'Display Configuration Builder', 'dynamic-storage-units' ); ?></h2>
		<p>
			<?php esc_html_e( 'Build a display configuration and preview it live on the right. Once created, it moves to Saved Displays.', 'dynamic-storage-units' ); ?>
			<?php esc_html_e( 'Shortcode format:', 'dynamic-storage-units' ); ?>
			<code>[storage_units config="CONFIG_NAME"]</code>
		</p>

		<div id="dsu-builder-message" style="display:none;" class="notice is-dismissible"></div>

		<form id="dsu-configs-form" novalidate>
			<div id="dsu-configs-list"></div>

			<button type="button" id="dsu-add-config" class="button button-secondary">
				+ <?php esc_html_e( 'Add Configuration', 'dynamic-storage-units' ); ?>
			</button>

			<div id="dsu-builder-submit-wrap" style="display:none; margin-top:16px;">
				<button type="submit" id="dsu-create-configs-btn" class="button button-primary button-large">
					<?php esc_html_e( 'Create Configuration(s)', 'dynamic-storage-units' ); ?>
				</button>
				<span id="dsu-create-status" class="dsu-status-msg" style="margin-left:12px;"></span>
			</div>
		</form>
	</div>

	<!-- Right: Live preview -->
	<div class="dsu-builder-right">
		<div class="dsu-preview-panel">
			<div class="dsu-preview-header">
				<span class="dsu-preview-title"><?php esc_html_e( 'Live Preview', 'dynamic-storage-units' ); ?></span>
				<span style="font-size:11px;color:#888;"><?php esc_html_e( 'Sample units — special, plain, sold out', 'dynamic-storage-units' ); ?></span>
			</div>
			<div class="dsu-preview-body">
				<div id="dsu-preview-output"></div>
			</div>
			<p class="dsu-preview-note">
				<?php esc_html_e( 'Preview uses sample data. Change Layout to see it update instantly.', 'dynamic-storage-units' ); ?>
			</p>
		</div>
	</div>

</div>

<script type="text/html" id="dsu-config-template">
<?php
$index   = '__INDEX__';
$config  = [];
$context = 'builder';
include __DIR__ . '/config-row.php';
?>
</script>
