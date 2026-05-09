<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div style="margin-top:16px;max-width:700px;">
	<p><?php esc_html_e( 'Assign an emoji to each featured feature string from your API. Features that match exactly are treated as the same feature across all unit groups. Clear the emoji field to show the feature label with no icon.', 'dynamic-storage-units' ); ?></p>
	<button type="button" id="dsu-refresh-features" class="button">
		<?php esc_html_e( 'Refresh from API', 'dynamic-storage-units' ); ?>
	</button>
	<span id="dsu-features-status" style="margin-left:10px;font-style:italic;color:#555;"></span>
</div>

<form method="post" action="options.php" id="dsu-feature-icons-form">
	<?php settings_fields( 'dsu_feature_icons_group' ); ?>

	<div id="dsu-feature-icons-table-wrap" style="margin-top:20px;">
		<?php
		$saved_map = get_option( DSU_OPTION_FEATURE_ICONS, [] );
		if ( ! empty( $saved_map ) ) :
		?>
		<table class="widefat" id="dsu-feature-icons-table" style="max-width:580px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Feature Text', 'dynamic-storage-units' ); ?></th>
					<th style="width:130px;"><?php esc_html_e( 'Emoji', 'dynamic-storage-units' ); ?></th>
				</tr>
			</thead>
			<tbody id="dsu-feature-icons-tbody">
				<?php foreach ( $saved_map as $feature => $emoji ) : ?>
				<tr>
					<td><?php echo esc_html( $feature ); ?></td>
					<td>
						<input type="text"
						       name="<?php echo esc_attr( DSU_OPTION_FEATURE_ICONS . '[' . $feature . ']' ); ?>"
						       value="<?php echo esc_attr( $emoji ); ?>"
						       style="width:80px;font-size:1.2em;" />
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
		<p id="dsu-features-empty"><?php esc_html_e( 'No features loaded yet. Click "Refresh from API" to pull the list of featured features from your facility.', 'dynamic-storage-units' ); ?></p>
		<table class="widefat" id="dsu-feature-icons-table" style="max-width:580px;display:none;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Feature Text', 'dynamic-storage-units' ); ?></th>
					<th style="width:130px;"><?php esc_html_e( 'Emoji', 'dynamic-storage-units' ); ?></th>
				</tr>
			</thead>
			<tbody id="dsu-feature-icons-tbody"></tbody>
		</table>
		<?php endif; ?>
	</div>

	<?php submit_button( __( 'Save Feature Icons', 'dynamic-storage-units' ) ); ?>
</form>

<script>
jQuery(function ($) {
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'dsu_admin_nonce' ) ); ?>;

	$('#dsu-refresh-features').on('click', function () {
		var $btn    = $(this);
		var $status = $('#dsu-features-status');
		$btn.prop('disabled', true);
		$status.css('color', '#555').text(<?php echo wp_json_encode( __( 'Fetching from API…', 'dynamic-storage-units' ) ); ?>);

		$.post(ajaxUrl, {
			action: 'dsu_fetch_featured_features',
			nonce:  nonce,
		}, function (res) {
			$btn.prop('disabled', false);

			if (!res.success) {
				$status.css('color', 'red').text(res.data && res.data.message
					? res.data.message
					: <?php echo wp_json_encode( __( 'Error fetching features.', 'dynamic-storage-units' ) ); ?>);
				return;
			}

			$status.css('color', 'green').text(<?php echo wp_json_encode( __( 'Done.', 'dynamic-storage-units' ) ); ?>);

			var features = res.data.features || [];
			var saved    = res.data.saved    || {};
			var $tbody   = $('#dsu-feature-icons-tbody');
			var optName  = <?php echo wp_json_encode( DSU_OPTION_FEATURE_ICONS ); ?>;

			// Build a map of features already in the table so we don't duplicate
			var existing = {};
			$tbody.find('input[type=text]').each(function () {
				existing[$(this).attr('name')] = true;
			});

			$.each(features, function (_, feat) {
				var fieldName = optName + '[' + feat + ']';
				if (existing[fieldName]) {
					return; // already in table
				}
				// Use saved value when it exists (even if empty); default new features to 🔒
				var value = saved.hasOwnProperty(feat) ? saved[feat] : '🔒';
				var $row = $(
					'<tr>' +
					'<td></td>' +
					'<td><input type="text" style="width:80px;font-size:1.2em;" /></td>' +
					'</tr>'
				);
				$row.find('td:first').text(feat);
				$row.find('input').attr('name', fieldName).val(value);
				$tbody.append($row);
			});

			$('#dsu-features-empty').hide();
			$('#dsu-feature-icons-table').show();
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.css('color', 'red').text(<?php echo wp_json_encode( __( 'Request failed. Check your browser console for details.', 'dynamic-storage-units' ) ); ?>);
		});
	});
});
</script>
