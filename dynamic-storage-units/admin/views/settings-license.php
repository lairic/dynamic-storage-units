<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_key = DSU_License::get_key();
$status      = $current_key ? DSU_License::get_status() : 'unlicensed';

$status_label = [
	'active'      => '<span style="color:#16a34a;font-weight:600;">&#10003; Active</span>',
	'invalid'     => '<span style="color:#dc2626;font-weight:600;">&#10007; Invalid or not activated for this site</span>',
	'unlicensed'  => '<span style="color:#6b7280;">No license key entered</span>',
	'unknown'     => '<span style="color:#d97706;">&#9888; Unknown (network error)</span>',
][ $status ] ?? '<span style="color:#6b7280;">—</span>';

$masked_key = $current_key
	? preg_replace( '/^(.{4}-.{4}-)(.+)(-.{4})$/', '$1••••••••$3', $current_key )
	: '';
?>

<div class="dsu-license-wrap" style="max-width:600px;margin-top:16px;">

	<div class="dsu-card" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:24px;margin-bottom:20px;">
		<h2 style="margin-top:0;font-size:16px;"><?php esc_html_e( 'License Status', 'dynamic-storage-units' ); ?></h2>

		<table class="form-table" style="margin:0">
			<tr>
				<th style="width:140px;"><?php esc_html_e( 'License Key', 'dynamic-storage-units' ); ?></th>
				<td>
					<?php if ( $current_key ) : ?>
						<code><?php echo esc_html( $masked_key ); ?></code>
					<?php else : ?>
						<span style="color:#6b7280;"><?php esc_html_e( 'None', 'dynamic-storage-units' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Status', 'dynamic-storage-units' ); ?></th>
				<td><?php echo $status_label; ?></td>
			</tr>
		</table>

		<?php if ( $current_key && $status === 'active' ) : ?>
			<p style="margin-top:20px;margin-bottom:0;">
				<button type="button" class="button button-secondary" id="dsu-deactivate-btn">
					<?php esc_html_e( 'Deactivate This Site', 'dynamic-storage-units' ); ?>
				</button>
			</p>
		<?php endif; ?>
	</div>

	<div class="dsu-card" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:24px;">
		<h2 style="margin-top:0;font-size:16px;">
			<?php echo $current_key
				? esc_html__( 'Update License Key', 'dynamic-storage-units' )
				: esc_html__( 'Enter License Key', 'dynamic-storage-units' ); ?>
		</h2>
		<p style="color:#555;margin-top:0;"><?php esc_html_e( 'Enter your license key and click Activate to register this site.', 'dynamic-storage-units' ); ?></p>

		<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
			<input
				type="text"
				id="dsu-license-key-input"
				class="regular-text"
				placeholder="XXXX-XXXX-XXXX-XXXX"
				style="font-family:monospace;letter-spacing:.05em;text-transform:uppercase;"
				maxlength="19"
			>
			<button type="button" class="button button-primary" id="dsu-activate-btn">
				<?php esc_html_e( 'Activate', 'dynamic-storage-units' ); ?>
			</button>
		</div>
		<p id="dsu-license-msg" style="margin-top:12px;display:none;"></p>
	</div>

</div>

<script>
(function($) {
	var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'dsu_admin_nonce' ) ); ?>;
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

	function showMsg(msg, success) {
		$('#dsu-license-msg')
			.text(msg)
			.css('color', success ? '#16a34a' : '#dc2626')
			.show();
	}

	$('#dsu-activate-btn').on('click', function() {
		var key = $('#dsu-license-key-input').val().trim().toUpperCase();
		if (!key) { showMsg('Please enter a license key.', false); return; }

		var $btn = $(this).prop('disabled', true).text('Activating…');
		$.post(ajaxUrl, { action: 'dsu_activate_license', nonce: nonce, license_key: key })
			.done(function(res) {
				if (res.success) {
					showMsg(res.data.message || 'Activated!', true);
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showMsg(res.data.message || 'Activation failed.', false);
					$btn.prop('disabled', false).text('Activate');
				}
			})
			.fail(function() {
				showMsg('Network error. Please try again.', false);
				$btn.prop('disabled', false).text('Activate');
			});
	});

	$('#dsu-deactivate-btn').on('click', function() {
		if (!confirm('Deactivate this site from the license? You can re-activate later.')) return;
		var $btn = $(this).prop('disabled', true).text('Deactivating…');
		$.post(ajaxUrl, { action: 'dsu_deactivate_license', nonce: nonce })
			.done(function(res) {
				if (res.success) {
					showMsg(res.data.message || 'Deactivated.', true);
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showMsg(res.data.message || 'Deactivation failed.', false);
					$btn.prop('disabled', false).text('Deactivate This Site');
				}
			})
			.fail(function() {
				showMsg('Network error. Please try again.', false);
				$btn.prop('disabled', false).text('Deactivate This Site');
			});
	});

	// Auto-format key as XXXX-XXXX-XXXX-XXXX
	$('#dsu-license-key-input').on('input', function() {
		var v = $(this).val().replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 16);
		$(this).val(v.match(/.{1,4}/g)?.join('-') || v);
	});
})(jQuery);
</script>
