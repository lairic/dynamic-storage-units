<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
$saved        = get_option( DSU_OPTION_SOURCE_MAP, [] );
$lead_sources = $saved['lead_sources'] ?? [];
$fallback_id  = $saved['fallback_id']  ?? '';
?>

<div style="margin-top:16px;max-width:700px;">
	<p><?php esc_html_e( 'Connect UTM traffic sources to lead sources in your facility management software. Start by pulling the lead source list from your facility, then choose which lead source to use as the fallback for visitors with no UTM parameters or unmatched traffic.', 'dynamic-storage-units' ); ?></p>
	<button type="button" id="dsu-refresh-lead-sources" class="button">
		<?php esc_html_e( 'Refresh from API', 'dynamic-storage-units' ); ?>
	</button>
	<span id="dsu-lead-sources-status" style="margin-left:10px;font-style:italic;color:#555;"></span>
</div>

<form method="post" action="options.php" style="margin-top:28px;max-width:580px;">
	<?php settings_fields( 'dsu_source_map_group' ); ?>

	<table class="form-table" style="max-width:580px;">
		<tr>
			<th scope="row" style="width:200px;">
				<label for="dsu-fallback-select">
					<?php esc_html_e( 'Fallback Lead Source', 'dynamic-storage-units' ); ?>
				</label>
			</th>
			<td>
				<?php if ( empty( $lead_sources ) ) : ?>
				<p id="dsu-no-sources-msg" style="margin:0 0 8px;font-style:italic;color:#888;">
					<?php esc_html_e( 'No lead sources loaded yet. Click "Refresh from API" above.', 'dynamic-storage-units' ); ?>
				</p>
				<?php else : ?>
				<p id="dsu-no-sources-msg" style="display:none;margin:0 0 8px;font-style:italic;color:#888;">
					<?php esc_html_e( 'No lead sources loaded yet. Click "Refresh from API" above.', 'dynamic-storage-units' ); ?>
				</p>
				<?php endif; ?>

				<select name="<?php echo esc_attr( DSU_OPTION_SOURCE_MAP . '[fallback_id]' ); ?>"
				        id="dsu-fallback-select"
				        style="min-width:260px;">
					<option value=""><?php esc_html_e( '— None —', 'dynamic-storage-units' ); ?></option>
					<?php foreach ( $lead_sources as $source ) : ?>
					<option value="<?php echo esc_attr( $source['id'] ); ?>"
					        <?php selected( $fallback_id, $source['id'] ); ?>>
						<?php echo esc_html( $source['name'] ); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<p class="description" style="margin-top:6px;">
					<?php esc_html_e( 'Applied when a visitor arrives with no UTM parameters, or when their source/medium does not match any rule you define later.', 'dynamic-storage-units' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save', 'dynamic-storage-units' ) ); ?>
</form>

<script>
jQuery(function ($) {
	var ajaxUrl      = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce        = <?php echo wp_json_encode( wp_create_nonce( 'dsu_admin_nonce' ) ); ?>;
	var savedFallback = <?php echo wp_json_encode( $fallback_id ); ?>;

	$('#dsu-refresh-lead-sources').on('click', function () {
		var $btn    = $(this);
		var $status = $('#dsu-lead-sources-status');
		$btn.prop('disabled', true);
		$status.css('color', '#555').text(<?php echo wp_json_encode( __( 'Fetching from API…', 'dynamic-storage-units' ) ); ?>);

		$.post(ajaxUrl, {
			action: 'dsu_fetch_lead_sources',
			nonce:  nonce,
		}, function (res) {
			$btn.prop('disabled', false);

			if (!res.success) {
				$status.css('color', 'red').text(
					res.data && res.data.message
						? res.data.message
						: <?php echo wp_json_encode( __( 'Error fetching lead sources.', 'dynamic-storage-units' ) ); ?>
				);
				return;
			}

			$status.css('color', 'green').text(<?php echo wp_json_encode( __( 'Done.', 'dynamic-storage-units' ) ); ?>);

			var sources = res.data.sources || [];
			var $select = $('#dsu-fallback-select');

			// Rebuild options, preserving the current selection
			var currentVal = $select.val();
			$select.find('option:not(:first)').remove();
			$.each(sources, function (_, s) {
				var $opt = $('<option>').val(s.id).text(s.name);
				if (s.id === (currentVal || savedFallback)) {
					$opt.prop('selected', true);
				}
				$select.append($opt);
			});

			if (sources.length > 0) {
				$('#dsu-no-sources-msg').hide();
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.css('color', 'red').text(<?php echo wp_json_encode( __( 'Request failed. Check your browser console for details.', 'dynamic-storage-units' ) ); ?>);
		});
	});
});
</script>
