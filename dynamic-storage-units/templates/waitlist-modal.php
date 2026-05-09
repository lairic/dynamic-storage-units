<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="dsu-waitlist-modal" class="dsu-modal" role="dialog" aria-modal="true" aria-labelledby="dsu-modal-title" hidden>
	<div class="dsu-modal-overlay dsu-close-modal"></div>
	<div class="dsu-modal-dialog">
		<button type="button" class="dsu-modal-close dsu-close-modal" aria-label="<?php esc_attr_e( 'Close', 'dynamic-storage-units' ); ?>">&#10005;</button>

		<h2 id="dsu-modal-title"><?php esc_html_e( 'Join the Waitlist', 'dynamic-storage-units' ); ?></h2>
		<p class="dsu-modal-subtitle">
			<?php esc_html_e( "We'll notify you when this unit becomes available.", 'dynamic-storage-units' ); ?>
		</p>

		<form id="dsu-waitlist-form" novalidate>
			<input type="hidden" name="group_label"   id="dsu-wl-group-label"   value="" />
			<input type="hidden" name="group_id"      id="dsu-wl-group-id"      value="" />
			<input type="hidden" name="facility_code" id="dsu-wl-facility-code" value="" />
			<input type="hidden" name="config_name"   id="dsu-wl-config-name"   value="" />

			<div class="dsu-field">
				<label for="dsu-wl-name"><?php esc_html_e( 'Name', 'dynamic-storage-units' ); ?> <span class="required">*</span></label>
				<input type="text" id="dsu-wl-name" name="name" required autocomplete="name" />
			</div>

			<div class="dsu-field">
				<label for="dsu-wl-email"><?php esc_html_e( 'Email', 'dynamic-storage-units' ); ?> <span class="required">*</span></label>
				<input type="email" id="dsu-wl-email" name="email" required autocomplete="email" />
			</div>

			<div class="dsu-field">
				<label for="dsu-wl-phone"><?php esc_html_e( 'Phone (optional)', 'dynamic-storage-units' ); ?></label>
				<input type="tel" id="dsu-wl-phone" name="phone" autocomplete="tel" />
			</div>

			<div id="dsu-wl-messages" aria-live="polite"></div>

			<button type="submit" class="dsu-btn dsu-btn-primary dsu-wl-submit">
				<?php esc_html_e( 'Join Waitlist', 'dynamic-storage-units' ); ?>
			</button>
		</form>
	</div>
</div>
