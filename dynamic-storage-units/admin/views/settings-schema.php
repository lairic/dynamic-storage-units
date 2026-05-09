<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<form method="post" action="options.php">
	<?php settings_fields( 'dsu_schema_group' ); ?>
	<?php $s = get_option( DSU_OPTION_SCHEMA, [] ); ?>

	<p style="margin-bottom:16px;">
		<?php esc_html_e( 'Generates a SelfStorage JSON-LD schema on any page that contains the [storage_units] shortcode. Unit listing data is pulled live from the API.', 'dynamic-storage-units' ); ?>
	</p>

	<table class="form-table" role="presentation">

		<!-- Enable toggle -->
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Schema Output', 'dynamic-storage-units' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo DSU_OPTION_SCHEMA; ?>[enabled]" value="1"
					       <?php checked( ! empty( $s['enabled'] ) ); ?> />
					<?php esc_html_e( 'Emit SelfStorage JSON-LD on pages with the [storage_units] shortcode', 'dynamic-storage-units' ); ?>
				</label>
			</td>
		</tr>

		<tr><td colspan="2"><hr style="border:none;border-top:1px solid #eee;margin:4px 0;"></td></tr>

		<!-- Fetch from API -->
		<tr>
			<th scope="row"><?php esc_html_e( 'Auto-Fill from API', 'dynamic-storage-units' ); ?></th>
			<td>
				<button type="button" id="dsu-fetch-facility-info" class="button">
					<?php esc_html_e( 'Fetch from API', 'dynamic-storage-units' ); ?>
				</button>
				<span id="dsu-fetch-facility-info-status" style="margin-left:10px;font-style:italic;"></span>
				<p class="description"><?php esc_html_e( 'Attempts to populate Name, Telephone, and Address from the API using your configured facility code.', 'dynamic-storage-units' ); ?></p>
			<div id="dsu-facility-raw-dump" style="display:none; margin-top:16px;">
				<strong><?php esc_html_e( 'Raw API Response', 'dynamic-storage-units' ); ?></strong>
				<p class="description" style="margin-bottom:6px;"><?php esc_html_e( 'Every field returned by the facility endpoint. Use this to see what data is available (look for amenities, features, hours, etc.).', 'dynamic-storage-units' ); ?></p>
				<pre style="background:#f6f7f7; border:1px solid #ddd; border-radius:4px; padding:14px; overflow:auto; max-height:400px; font-size:12px; line-height:1.5; white-space:pre-wrap; word-break:break-all;"></pre>
			</div>
			</td>
		</tr>

		<tr><td colspan="2"><hr style="border:none;border-top:1px solid #eee;margin:4px 0;"></td></tr>

		<tr><td colspan="2"><h2 style="margin:16px 0 0;"><?php esc_html_e( 'Business Info', 'dynamic-storage-units' ); ?></h2></td></tr>

		<tr>
			<th scope="row"><label for="dsu_schema_name"><?php esc_html_e( 'Business Name', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="text" id="dsu_schema_name" name="<?php echo DSU_OPTION_SCHEMA; ?>[name]"
				       value="<?php echo esc_attr( $s['name'] ?? '' ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsu_schema_telephone"><?php esc_html_e( 'Telephone', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="text" id="dsu_schema_telephone" name="<?php echo DSU_OPTION_SCHEMA; ?>[telephone]"
				       value="<?php echo esc_attr( $s['telephone'] ?? '' ); ?>" class="regular-text"
				       placeholder="+1-555-555-5555" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsu_schema_url"><?php esc_html_e( 'Business URL', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="url" id="dsu_schema_url" name="<?php echo DSU_OPTION_SCHEMA; ?>[url]"
				       value="<?php echo esc_attr( $s['url'] ?? '' ); ?>" class="regular-text"
				       placeholder="<?php echo esc_attr( get_site_url() ); ?>" />
				<p class="description"><?php esc_html_e( 'Defaults to the site URL if left blank.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsu_schema_description"><?php esc_html_e( 'Description', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<textarea id="dsu_schema_description" name="<?php echo DSU_OPTION_SCHEMA; ?>[description]"
				          rows="3" class="large-text"><?php echo esc_textarea( $s['description'] ?? '' ); ?></textarea>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsu_schema_image_url"><?php esc_html_e( 'Facility Image URL', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="url" id="dsu_schema_image_url" name="<?php echo DSU_OPTION_SCHEMA; ?>[image_url]"
				       value="<?php echo esc_attr( $s['image_url'] ?? '' ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'A photo of the facility exterior or entrance.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>

		<tr><td colspan="2"><h2 style="margin:16px 0 0;"><?php esc_html_e( 'Address', 'dynamic-storage-units' ); ?></h2></td></tr>

		<tr>
			<th scope="row"><label for="dsu_schema_street_address"><?php esc_html_e( 'Street Address', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="text" id="dsu_schema_street_address" name="<?php echo DSU_OPTION_SCHEMA; ?>[street_address]"
				       value="<?php echo esc_attr( $s['street_address'] ?? '' ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsu_schema_address_locality"><?php esc_html_e( 'City', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="text" id="dsu_schema_address_locality" name="<?php echo DSU_OPTION_SCHEMA; ?>[address_locality]"
				       value="<?php echo esc_attr( $s['address_locality'] ?? '' ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsu_schema_address_region"><?php esc_html_e( 'State / Province', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="text" id="dsu_schema_address_region" name="<?php echo DSU_OPTION_SCHEMA; ?>[address_region]"
				       value="<?php echo esc_attr( $s['address_region'] ?? '' ); ?>" class="small-text"
				       placeholder="IL" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsu_schema_postal_code"><?php esc_html_e( 'Postal Code', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="text" id="dsu_schema_postal_code" name="<?php echo DSU_OPTION_SCHEMA; ?>[postal_code]"
				       value="<?php echo esc_attr( $s['postal_code'] ?? '' ); ?>" class="small-text" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsu_schema_address_country"><?php esc_html_e( 'Country Code', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="text" id="dsu_schema_address_country" name="<?php echo DSU_OPTION_SCHEMA; ?>[address_country]"
				       value="<?php echo esc_attr( $s['address_country'] ?? 'US' ); ?>" class="small-text"
				       placeholder="US" maxlength="2" />
				<p class="description"><?php esc_html_e( 'Two-letter ISO country code (e.g. US, CA, GB).', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>

		<tr><td colspan="2"><h2 style="margin:16px 0 0;"><?php esc_html_e( 'Coordinates', 'dynamic-storage-units' ); ?></h2></td></tr>

		<tr>
			<th scope="row"><label for="dsu_schema_latitude"><?php esc_html_e( 'Latitude', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="text" id="dsu_schema_latitude" name="<?php echo DSU_OPTION_SCHEMA; ?>[latitude]"
				       value="<?php echo esc_attr( $s['latitude'] ?? '' ); ?>" class="small-text"
				       placeholder="41.8827" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dsu_schema_longitude"><?php esc_html_e( 'Longitude', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="text" id="dsu_schema_longitude" name="<?php echo DSU_OPTION_SCHEMA; ?>[longitude]"
				       value="<?php echo esc_attr( $s['longitude'] ?? '' ); ?>" class="small-text"
				       placeholder="-87.6233" />
			</td>
		</tr>

		<tr><td colspan="2">
			<h2 style="margin:16px 0 4px;"><?php esc_html_e( 'Opening Hours', 'dynamic-storage-units' ); ?></h2>
			<p class="description" style="margin-bottom:12px;"><?php esc_html_e( 'Most facilities will add their gate hours in this section as it populates via Google, but you can choose to add your office hours here instead. This generates only for schema markup and does not affect any hours you may have posted on your website.', 'dynamic-storage-units' ); ?></p>
		</td></tr>

		<tr>
			<td colspan="2">
				<?php
				$days   = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
				$hours  = $s['hours'] ?? [];
				?>
				<table class="widefat" style="max-width:560px;">
					<thead>
						<tr>
							<th style="width:110px;"><?php esc_html_e( 'Day', 'dynamic-storage-units' ); ?></th>
							<th style="width:70px;"><?php esc_html_e( 'Open?', 'dynamic-storage-units' ); ?></th>
							<th><?php esc_html_e( 'Opens', 'dynamic-storage-units' ); ?></th>
							<th><?php esc_html_e( 'Closes', 'dynamic-storage-units' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $days as $day ) :
						$day_data = $hours[ $day ] ?? [];
						$enabled  = ! empty( $day_data['enabled'] );
						$opens    = esc_attr( $day_data['opens']  ?? '09:00' );
						$closes   = esc_attr( $day_data['closes'] ?? '18:00' );
						$opt      = DSU_OPTION_SCHEMA . '[hours][' . $day . ']';
					?>
						<tr>
							<td><?php echo esc_html( $day ); ?></td>
							<td style="text-align:center;">
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enabled]"
								       value="1" <?php checked( $enabled ); ?> />
							</td>
							<td>
								<input type="time" name="<?php echo esc_attr( $opt ); ?>[opens]"
								       value="<?php echo $opens; ?>" style="width:130px;" />
							</td>
							<td>
								<input type="time" name="<?php echo esc_attr( $opt ); ?>[closes]"
								       value="<?php echo $closes; ?>" style="width:130px;" />
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</td>
		</tr>

		<tr><td colspan="2"><h2 style="margin:16px 0 0;"><?php esc_html_e( 'Payment Accepted', 'dynamic-storage-units' ); ?></h2></td></tr>

		<tr>
			<td colspan="2">
				<?php
				$payment_options = [ 'Cash', 'Check', 'Credit Card', 'Debit Card', 'ACH Transfer', 'Money Order' ];
				$payment_saved   = $s['payment_accepted'] ?? [];
				foreach ( $payment_options as $p ) :
				?>
					<label style="display:inline-block;margin-right:20px;margin-bottom:8px;">
						<input type="checkbox" name="<?php echo DSU_OPTION_SCHEMA; ?>[payment_accepted][]"
						       value="<?php echo esc_attr( $p ); ?>"
						       <?php checked( in_array( $p, $payment_saved, true ) ); ?> />
						<?php echo esc_html( $p ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>

		<tr><td colspan="2"><h2 style="margin:16px 0 0;"><?php esc_html_e( 'Advanced', 'dynamic-storage-units' ); ?></h2></td></tr>

		<tr>
			<th scope="row"><label for="dsu_schema_id"><?php esc_html_e( 'Schema @id URL', 'dynamic-storage-units' ); ?></label></th>
			<td>
				<input type="url" id="dsu_schema_id" name="<?php echo DSU_OPTION_SCHEMA; ?>[schema_id]"
				       value="<?php echo esc_attr( $s['schema_id'] ?? '' ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'A stable, unique URL identifying this business entity. Usually your Google Business Profile URL or your facility\'s main page URL. Leave blank to omit @id.', 'dynamic-storage-units' ); ?></p>
			</td>
		</tr>

	</table>

	<?php submit_button(); ?>
</form>
