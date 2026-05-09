<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<form method="post" action="options.php">
	<?php settings_fields( 'dsu_api_group' ); ?>
	<?php $settings = get_option( DSU_OPTION_API, [] ); ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="dsu_base_url"><?php esc_html_e( 'API Base URL', 'dynamic-storage-units' ); ?></label>
			</th>
			<td>
				<input type="url" id="dsu_base_url" name="<?php echo DSU_OPTION_API; ?>[base_url]"
				       value="<?php echo esc_attr( $settings['base_url'] ?? '' ); ?>"
				       class="regular-text" placeholder="https://api.example.com" />
				<p class="description"><?php esc_html_e( 'No trailing slash. Token and data endpoints will be appended automatically.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="dsu_company_code"><?php esc_html_e( 'Company Code', 'dynamic-storage-units' ); ?></label>
			</th>
			<td>
				<input type="text" id="dsu_company_code" name="<?php echo DSU_OPTION_API; ?>[company_code]"
				       value="<?php echo esc_attr( $settings['company_code'] ?? '' ); ?>"
				       class="regular-text" placeholder="your-company-code" />
				<p class="description"><?php esc_html_e( 'Used in all API paths: /api/v2/companies/{companyCode}/…', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="dsu_facility_code"><?php esc_html_e( 'Facility Code', 'dynamic-storage-units' ); ?></label>
			</th>
			<td>
				<input type="text" id="dsu_facility_code" name="<?php echo DSU_OPTION_API; ?>[facility_code]"
				       value="<?php echo esc_attr( $settings['facility_code'] ?? '' ); ?>"
				       class="regular-text" placeholder="your-facility-code" />
				<p class="description"><?php esc_html_e( 'Your facility code. Used as the default for unit group image mapping and any configuration that does not specify its own.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="dsu_client_id"><?php esc_html_e( 'Client ID', 'dynamic-storage-units' ); ?></label>
			</th>
			<td>
				<input type="text" id="dsu_client_id" name="<?php echo DSU_OPTION_API; ?>[client_id]"
				       value="<?php echo esc_attr( $settings['client_id'] ?? '' ); ?>"
				       class="regular-text" autocomplete="off" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="dsu_client_secret"><?php esc_html_e( 'Client Secret', 'dynamic-storage-units' ); ?></label>
			</th>
			<td>
				<input type="password" id="dsu_client_secret" name="<?php echo DSU_OPTION_API; ?>[client_secret]"
				       value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>"
				       class="regular-text" autocomplete="new-password" />
				<p class="description"><?php esc_html_e( 'The plugin will exchange your Client ID and Secret for a bearer token automatically and cache it for the token lifetime.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="dsu_api_key"><?php esc_html_e( 'API Key (Alternative)', 'dynamic-storage-units' ); ?></label>
			</th>
			<td>
				<input type="password" id="dsu_api_key" name="<?php echo DSU_OPTION_API; ?>[api_key]"
				       value="<?php echo esc_attr( $settings['api_key'] ?? '' ); ?>"
				       class="regular-text" autocomplete="new-password" />
				<p class="description"><?php esc_html_e( 'Optional. A plain API key from a different section of your storage software (not the OAuth client credentials). Used only for testing via the Schema Variables tab — does not affect the main unit listing.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="dsu_cache_duration"><?php esc_html_e( 'Data Cache Duration (minutes)', 'dynamic-storage-units' ); ?></label>
			</th>
			<td>
				<input type="number" id="dsu_cache_duration" name="<?php echo DSU_OPTION_API; ?>[cache_duration]"
				       value="<?php echo esc_attr( $settings['cache_duration'] ?? 15 ); ?>"
				       class="small-text" min="1" max="1440" />
				<p class="description"><?php esc_html_e( 'Applies to unit groups and CTA URLs. The access token is cached separately based on its own expiry time. Default: 15 minutes.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th colspan="2"><hr style="border:none;border-top:1px solid #eee;margin:8px 0;"></th>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Primary Button Color', 'dynamic-storage-units' ); ?></th>
			<td>
				<input type="text" name="<?php echo DSU_OPTION_API; ?>[primary_color]"
				       class="dsu-color-picker"
				       value="<?php echo esc_attr( $settings['primary_color'] ?? '#1a73e8' ); ?>"
				       data-default-color="#1a73e8" />
				<p class="description"><?php esc_html_e( 'Background/border color for "Rent Now" and primary action buttons.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Primary Button Text Color', 'dynamic-storage-units' ); ?></th>
			<td>
				<input type="text" name="<?php echo DSU_OPTION_API; ?>[primary_text_color]"
				       class="dsu-color-picker"
				       value="<?php echo esc_attr( $settings['primary_text_color'] ?? '#ffffff' ); ?>"
				       data-default-color="#ffffff" />
				<p class="description"><?php esc_html_e( 'Text color for "Rent Now" buttons. Use a dark color if your primary button color is light.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Secondary Button Color', 'dynamic-storage-units' ); ?></th>
			<td>
				<input type="text" name="<?php echo DSU_OPTION_API; ?>[secondary_color]"
				       class="dsu-color-picker"
				       value="<?php echo esc_attr( $settings['secondary_color'] ?? '#1a73e8' ); ?>"
				       data-default-color="#1a73e8" />
				<p class="description"><?php esc_html_e( 'Border and text color for "Reserve" outline buttons; fill color on hover.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Secondary Button Text Color (Hover)', 'dynamic-storage-units' ); ?></th>
			<td>
				<input type="text" name="<?php echo DSU_OPTION_API; ?>[secondary_text_color]"
				       class="dsu-color-picker"
				       value="<?php echo esc_attr( $settings['secondary_text_color'] ?? '#ffffff' ); ?>"
				       data-default-color="#ffffff" />
				<p class="description"><?php esc_html_e( 'Text color for "Reserve" buttons when filled (hover/active). Use a dark color if your secondary button color is light.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th colspan="2"><hr style="border:none;border-top:1px solid #eee;margin:8px 0;"></th>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Promotion Bar Color', 'dynamic-storage-units' ); ?></th>
			<td>
				<input type="text" name="<?php echo DSU_OPTION_API; ?>[promo_bar_color]"
				       class="dsu-color-picker"
				       value="<?php echo esc_attr( $settings['promo_bar_color'] ?? '#d4a900' ); ?>"
				       data-default-color="#d4a900" />
				<p class="description"><?php esc_html_e( 'Accent color for the promotion bar — used for the border, icon, and text. The background tint is derived automatically. Default: golden yellow.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th colspan="2"><hr style="border:none;border-top:1px solid #eee;margin:8px 0;"></th>
		</tr>
		<tr>
			<th colspan="2">
				<h3 style="margin:0 0 4px;"><?php esc_html_e( 'Grouped Unit Settings', 'dynamic-storage-units' ); ?></h3>
				<p class="description" style="font-weight:normal;"><?php esc_html_e( 'When multiple unit types share the same name they are grouped into one card with a tier-selection modal.', 'dynamic-storage-units' ); ?></p>
			</th>
		</tr>
		<tr>
			<th scope="row">
				<label for="dsu_grouped_cta_text"><?php esc_html_e( 'Grouped Card CTA Text', 'dynamic-storage-units' ); ?></label>
			</th>
			<td>
				<input type="text" id="dsu_grouped_cta_text" name="<?php echo DSU_OPTION_API; ?>[grouped_cta_text]"
				       value="<?php echo esc_attr( $settings['grouped_cta_text'] ?? '' ); ?>"
				       class="regular-text" placeholder="Choose Your Space" />
				<p class="description"><?php esc_html_e( 'Button label on the grouped card that opens the tier-selection modal. Default: Choose Your Space.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Tier Labels', 'dynamic-storage-units' ); ?></th>
			<td>
				<label for="dsu_good_label" style="display:inline-block;width:60px;"><?php esc_html_e( 'Good:', 'dynamic-storage-units' ); ?></label>
				<input type="text" id="dsu_good_label" name="<?php echo DSU_OPTION_API; ?>[good_label]"
				       value="<?php echo esc_attr( $settings['good_label'] ?? '' ); ?>"
				       class="small-text" placeholder="Good" style="width:100px;" />
				&nbsp;&nbsp;
				<label for="dsu_better_label"><?php esc_html_e( 'Better:', 'dynamic-storage-units' ); ?></label>
				<input type="text" id="dsu_better_label" name="<?php echo DSU_OPTION_API; ?>[better_label]"
				       value="<?php echo esc_attr( $settings['better_label'] ?? '' ); ?>"
				       class="small-text" placeholder="Better" style="width:100px;" />
				&nbsp;&nbsp;
				<label for="dsu_best_label"><?php esc_html_e( 'Best:', 'dynamic-storage-units' ); ?></label>
				<input type="text" id="dsu_best_label" name="<?php echo DSU_OPTION_API; ?>[best_label]"
				       value="<?php echo esc_attr( $settings['best_label'] ?? '' ); ?>"
				       class="small-text" placeholder="Best" style="width:100px;" />
				<p class="description"><?php esc_html_e( 'Labels for the three price tiers in the modal (cheapest → most expensive). Defaults: Good / Better / Best.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>
