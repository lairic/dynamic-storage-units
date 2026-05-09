/* global dsuAdmin, wp */
(function ($) {
	'use strict';

	var strings      = dsuAdmin.strings;
	var facilityCode = dsuAdmin.facilityCode || '';
	var iconMap      = dsuAdmin.iconMap || {};

	var allGroups    = [];
	var sortState    = { col: '', dir: 'asc' };
	var filterState  = { search: '', category: '', special: '' };
	var previewGroups = null;

	/* ---- Auto-fetch groups on images tab load ---- */
	if ( $('#dsu-groups-tbody').length && facilityCode ) {
		fetchGroups();
	}

	/* ---- Refresh button ---- */
	$('#dsu-refresh-groups').on('click', function () {
		fetchGroups( true );
	});

	/* ---- Clear cache ---- */
	$('#dsu-bust-cache').on('click', function () {
		var $status = $('#dsu-fetch-status');
		$.post(dsuAdmin.ajaxUrl, {
			action:        'dsu_bust_cache',
			nonce:         dsuAdmin.nonce,
			facility_code: facilityCode,
		}, function () {
			$status.text(strings.cacheBusted).addClass('dsu-success').removeClass('dsu-error');
			fetchGroups( true );
		});
	});

	function fetchGroups( showStatus ) {
		var $status = $('#dsu-fetch-status');
		if ( showStatus ) {
			$status.text(strings.fetchingGroups).removeClass('dsu-success dsu-error');
		}

		$.post(dsuAdmin.ajaxUrl, {
			action:        'dsu_fetch_unit_groups',
			nonce:         dsuAdmin.nonce,
			facility_code: facilityCode,
		}, function (res) {
			if (!res.success) {
				$status.text(res.data.message || 'Error loading groups.').addClass('dsu-error');
				return;
			}
			var groups = res.data.groups || [];
			if (!groups.length) {
				$status.text(strings.noGroups).addClass('dsu-error');
				return;
			}
			if ( showStatus ) {
				$status.text('Loaded ' + groups.length + ' group(s).').addClass('dsu-success');
			}
			allGroups = groups;
			ensureFilterToolbar();
			applyTableState();
		}).fail(function () {
			$status.text('Request failed. Check API settings.').addClass('dsu-error');
		});
	}

	/* ---- Sortable column headers ---- */
	$(document).on('click', '.dsu-sort-th', function () {
		var col = $(this).data('sort');
		if (!col || !allGroups.length) return;
		if (sortState.col === col) {
			sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
		} else {
			sortState.col = col;
			sortState.dir = 'asc';
		}
		applyTableState();
	});

	/* ---- Media Library image picker ---- */
	$(document).on('click', '.dsu-select-image', function () {
		var $row   = $(this).closest('tr');
		var $input = $row.find('.dsu-image-url-input');
		var $thumb = $row.find('.dsu-thumb');

		var frame = wp.media({
			title:    strings.selectImage,
			button:   { text: strings.useImage },
			multiple: false,
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var url        = attachment.url;
			$input.val(url);
			if ($thumb.length) {
				$thumb.attr('src', url);
			} else {
				$row.find('.dsu-col-image').prepend('<img class="dsu-thumb" src="' + escHtml(url) + '" alt="" />');
			}
		});

		frame.open();
	});

	/* ---- Remove image ---- */
	$(document).on('click', '.dsu-remove-image', function () {
		var $row = $(this).closest('tr');
		$row.find('.dsu-image-url-input').val('');
		$row.find('.dsu-thumb').remove();
	});

	function buildCatOptions( savedCat ) {
		var cats = dsuAdmin.categories || [];
		var opts = '<option value="">— None —</option>';
		cats.forEach(function (cat) {
			var sel = ( cat.slug === savedCat ) ? ' selected' : '';
			opts += '<option value="' + escHtml(cat.slug) + '"' + sel + '>' + escHtml(cat.label) + '</option>';
		});
		return opts;
	}

	function buildUnitTypeOptions( savedType ) {
		var types = dsuAdmin.unitTypes || [];
		var opts  = '<option value="">— None —</option>';
		types.forEach(function (ut) {
			var sel = ( ut.slug === savedType ) ? ' selected' : '';
			opts += '<option value="' + escHtml(ut.slug) + '"' + sel + '>' + escHtml(ut.label) + '</option>';
		});
		return opts;
	}

	function parseDims( label ) {
		var m = String(label).match(/(\d+(?:\.\d+)?)\s*[xX×]\s*(\d+(?:\.\d+)?)/);
		if (!m) return null;
		var w = parseFloat(m[1]);
		var d = parseFloat(m[2]);
		return { w: w, d: d, sqft: Math.round(w * d) };
	}

	function renderGroupsTable( groups, existingState ) {
		var $tbody = $('#dsu-groups-tbody');
		var $wrap  = $('#dsu-groups-table-wrap');
		var $noMsg = $('#dsu-no-groups-msg');

		var existing = existingState || getExistingGroupMap();
		$tbody.empty();

		groups.forEach(function (group) {
			var gid      = group.id || '';
			var label    = group.label || gid;
			var avail    = group.availableTotal != null ? group.availableTotal : '?';
			var total    = group.totalInGroup   != null ? group.totalInGroup   : '?';
			var rate     = group.streetRate     != null ? '$' + parseFloat(group.streetRate).toFixed(0) : '—';
			var saved     = existing[gid]        || {};
			var imgUrl    = saved.image_url      || '';
			var savedCat  = saved.size_category  || '';
			var savedType = saved.unit_type      || '';
			var thumb     = imgUrl ? '<img class="dsu-thumb" src="' + escHtml(imgUrl) + '" alt="" />' : '';

			var dims     = parseDims(label);
			var dimsHtml = dims
				? escHtml(String(dims.w)) + '&#215;' + escHtml(String(dims.d)) +
				  '<br><small style="color:#888;">' + escHtml(String(dims.sqft)) + ' sqft</small>'
				: '&#8212;';

			var sp         = group.availableSpecial;
			var hasSpecial = !!(sp && typeof sp === 'object' && (sp.specialRate || sp.rate));
			var spLabel    = hasSpecial ? (sp.label || sp.promotionName || 'Special') : '';
			var spDisplay  = spLabel.length > 28 ? spLabel.substring(0, 26) + '…' : spLabel;
			var specialHtml = hasSpecial
				? '<span style="color:#2e7d32;font-weight:600;" title="' + escHtml(spLabel) + '">&#10003; ' + escHtml(spDisplay) + '</span>'
				: '<span style="color:#999;">&#8212;</span>';

			var row =
				'<tr data-group-id="' + escHtml(gid) + '">' +
				'<td>' +
					'<strong>' + escHtml(label) + '</strong><br>' +
					'<code style="font-size:11px;color:#888;">' + escHtml(gid) + '</code>' +
					'<input type="hidden" name="dsu_image_mappings[' + escHtml(gid) + '][label]" value="' + escHtml(label) + '" />' +
				'</td>' +
				'<td class="dsu-col-dims">' + dimsHtml + '</td>' +
				'<td class="dsu-live-col">' + escHtml(String(avail)) + ' / ' + escHtml(String(total)) + '</td>' +
				'<td class="dsu-live-col">' + escHtml(rate) + '</td>' +
				'<td class="dsu-live-col">' + specialHtml + '</td>' +
				'<td class="dsu-col-image">' + thumb +
					'<input type="hidden" name="dsu_image_mappings[' + escHtml(gid) + '][image_url]" ' +
					'class="dsu-image-url-input" value="' + escHtml(imgUrl) + '" />' +
				'</td>' +
				'<td>' +
					'<select name="dsu_image_mappings[' + escHtml(gid) + '][size_category]" class="dsu-size-cat-select">' +
					buildCatOptions(savedCat) +
					'</select>' +
				'</td>' +
				'<td>' +
					'<select name="dsu_image_mappings[' + escHtml(gid) + '][unit_type]" class="dsu-unit-type-select">' +
					buildUnitTypeOptions(savedType) +
					'</select>' +
				'</td>' +
				'<td>' +
					'<button type="button" class="button button-small dsu-select-image">' + strings.selectImage + '</button> ' +
					'<button type="button" class="button-link dsu-remove-image">Remove</button>' +
				'</td>' +
				'</tr>';

			$tbody.append(row);
		});

		if ($noMsg.length) { $noMsg.hide(); }
		$wrap.show();
	}

	function getExistingGroupMap() {
		var map = {};
		$('#dsu-groups-tbody tr[data-group-id]').each(function () {
			var gid    = $(this).data('group-id');
			var imgUrl = $(this).find('.dsu-image-url-input').val() || '';
			var lbl    = $(this).find('input[name*="[label]"]').val() || '';
			var cat    = $(this).find('.dsu-size-cat-select').val() || '';
			var utype  = $(this).find('.dsu-unit-type-select').val() || '';
			if (gid) {
				map[gid] = { image_url: imgUrl, label: lbl, size_category: cat, unit_type: utype };
			}
		});
		return map;
	}

	function ensureFilterToolbar() {
		if ( $('#dsu-filter-toolbar').length ) return;
		var cats = dsuAdmin.categories || [];
		var catOpts = '<option value="">All Categories</option>';
		cats.forEach(function (c) {
			catOpts += '<option value="' + escHtml(c.slug) + '">' + escHtml(c.label) + '</option>';
		});
		catOpts += '<option value="__none__">&#8212; Uncategorized &#8212;</option>';

		var toolbar =
			'<div id="dsu-filter-toolbar" style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">' +
			'<input type="text" id="dsu-filter-search" placeholder="Search label…" style="width:180px;" class="regular-text" />' +
			'<select id="dsu-filter-category">' + catOpts + '</select>' +
			'<select id="dsu-filter-special">' +
				'<option value="">All (Special / No Special)</option>' +
				'<option value="yes">Has Special</option>' +
				'<option value="no">No Special</option>' +
			'</select>' +
			'<button type="button" id="dsu-filter-clear" class="button button-small">Clear Filters</button>' +
			'<span id="dsu-filter-count" style="color:#666;font-size:12px;"></span>' +
			'</div>';

		$('#dsu-groups-table-wrap').prepend(toolbar);

		$('#dsu-filter-search').on('input', function () {
			filterState.search = $(this).val();
			applyTableState();
		});
		$('#dsu-filter-category').on('change', function () {
			filterState.category = $(this).val();
			applyTableState();
		});
		$('#dsu-filter-special').on('change', function () {
			filterState.special = $(this).val();
			applyTableState();
		});
		$('#dsu-filter-clear').on('click', function () {
			filterState = { search: '', category: '', special: '' };
			$('#dsu-filter-search').val('');
			$('#dsu-filter-category').val('');
			$('#dsu-filter-special').val('');
			applyTableState();
		});
	}

	function applyTableState() {
		if (!allGroups.length) return;
		var existing = getExistingGroupMap();

		var filtered = allGroups.filter(function (g) {
			var gid = g.id || '';
			if (filterState.search) {
				if (String(g.label || '').toLowerCase().indexOf(filterState.search.toLowerCase()) === -1) return false;
			}
			if (filterState.category !== '') {
				var cat = (existing[gid] || {}).size_category || '';
				if (filterState.category === '__none__') {
					if (cat !== '') return false;
				} else {
					if (cat !== filterState.category) return false;
				}
			}
			if (filterState.special === 'yes' || filterState.special === 'no') {
				var gsp = g.availableSpecial;
				var gHas = !!(gsp && typeof gsp === 'object' && (gsp.specialRate || gsp.rate));
				if (filterState.special === 'yes' && !gHas) return false;
				if (filterState.special === 'no'  &&  gHas) return false;
			}
			return true;
		});

		if (sortState.col) {
			filtered.sort(function (a, b) {
				var av, bv;
				switch (sortState.col) {
					case 'label':
						av = String(a.label || '').toLowerCase();
						bv = String(b.label || '').toLowerCase();
						break;
					case 'sqft':
						var ad = parseDims(a.label), bd = parseDims(b.label);
						av = ad ? ad.sqft : -1;
						bv = bd ? bd.sqft : -1;
						break;
					case 'avail':
						av = a.availableTotal || 0;
						bv = b.availableTotal || 0;
						break;
					case 'rate':
						av = a.streetRate || 0;
						bv = b.streetRate || 0;
						break;
					case 'special':
						var asp = a.availableSpecial, bsp = b.availableSpecial;
						av = !!(asp && typeof asp === 'object' && (asp.specialRate || asp.rate)) ? 1 : 0;
						bv = !!(bsp && typeof bsp === 'object' && (bsp.specialRate || bsp.rate)) ? 1 : 0;
						break;
					case 'category':
						av = (existing[a.id || ''] || {}).size_category || '';
						bv = (existing[b.id || ''] || {}).size_category || '';
						break;
					default:
						av = 0; bv = 0;
				}
				if (av < bv) return sortState.dir === 'asc' ? -1 : 1;
				if (av > bv) return sortState.dir === 'asc' ?  1 : -1;
				return 0;
			});
		}

		renderGroupsTable(filtered, existing);
		updateSortIndicators();
		$('#dsu-filter-count').text('Showing ' + filtered.length + ' of ' + allGroups.length + ' groups');
	}

	function updateSortIndicators() {
		$('.dsu-sort-th').each(function () {
			var col  = $(this).data('sort');
			var $ind = $(this).find('.dsu-sort-ind');
			$ind.text(col === sortState.col ? (sortState.dir === 'asc' ? ' ▲' : ' ▼') : '');
		});
	}

	/* ---- Size Category row management ---- */
	var catIndex = $('#dsu-categories-tbody tr').length;

	$('#dsu-add-category').on('click', function () {
		var tpl = $('#dsu-category-row-template').html();
		if ( !tpl ) return;
		var html = tpl.replace(/__CAT_INDEX__/g, catIndex);
		$('#dsu-categories-tbody').append(html);
		catIndex++;
	});

	$(document).on('click', '.dsu-delete-category', function () {
		$(this).closest('tr').remove();
		reindexCategoryRows();
	});

	/* Auto-slug from label */
	$(document).on('input', '.dsu-cat-label-input', function () {
		var $row  = $(this).closest('tr');
		var $slug = $row.find('.dsu-cat-slug-input');
		if ( !$slug.data('manual') ) {
			$slug.val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));
		}
	});
	$(document).on('input', '.dsu-cat-slug-input', function () {
		$(this).data('manual', true);
	});

	function reindexCategoryRows() {
		$('#dsu-categories-tbody tr').each(function (i) {
			$(this).attr('data-index', i);
			$(this).find('[name]').each(function () {
				var n = $(this).attr('name');
				if (n) { $(this).attr('name', n.replace(/\[\d+\]/, '[' + i + ']')); }
			});
		});
		catIndex = $('#dsu-categories-tbody tr').length;
	}

	/* ---- Unit Type row management ---- */
	var utIndex = $('#dsu-unit-types-tbody tr').length;

	$('#dsu-add-unit-type').on('click', function () {
		var tpl = $('#dsu-unit-type-row-template').html();
		if (!tpl) return;
		var html = tpl.replace(/__UT_INDEX__/g, utIndex);
		$('#dsu-unit-types-tbody').append(html);
		utIndex++;
	});

	$(document).on('click', '.dsu-delete-unit-type', function () {
		$(this).closest('tr').remove();
		reindexUnitTypeRows();
	});

	/* Auto-slug from label for unit types */
	$(document).on('input', '.dsu-ut-label-input', function () {
		var $row  = $(this).closest('tr');
		var $slug = $row.find('.dsu-ut-slug-input');
		if (!$slug.data('manual')) {
			$slug.val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));
		}
	});
	$(document).on('input', '.dsu-ut-slug-input', function () {
		$(this).data('manual', true);
	});

	function reindexUnitTypeRows() {
		$('#dsu-unit-types-tbody tr').each(function (i) {
			$(this).attr('data-index', i);
			$(this).find('[name]').each(function () {
				var n = $(this).attr('name');
				if (n) { $(this).attr('name', n.replace(/\[\d+\]/, '[' + i + ']')); }
			});
		});
		utIndex = $('#dsu-unit-types-tbody tr').length;
	}

	/* Populate unit-type dropdowns in new category rows */
	$(document).on('change', '.dsu-ut-cat-select[data-ut-select="cat"]', function () {
		// Nothing extra needed — this select is already wired by PHP or template
	});

	/* ---- Display Configuration Builder ---- */
	var configIndex = 0;

	$('#dsu-add-config').on('click', function () {
		var tpl = $('#dsu-config-template').html();
		if (!tpl) return;
		var html = tpl.replace(/__INDEX__/g, configIndex);
		$('#dsu-configs-list').append(html);

		// Pre-populate from default config if one is set
		var defaultName = dsuAdmin.defaultConfig || '';
		if (defaultName) {
			var configs = dsuAdmin.configs || [];
			for (var d = 0; d < configs.length; d++) {
				if (configs[d].name === defaultName) {
					applyDefaultToRow($('.dsu-config-row[data-index="' + configIndex + '"]'), configs[d]);
					break;
				}
			}
		}

		configIndex++;
		$('#dsu-builder-submit-wrap').show();
		renderPreview();
	});

	/* Builder AJAX submit */
	$('#dsu-configs-form').on('submit', function (e) {
		e.preventDefault();
		var $btn    = $('#dsu-create-configs-btn');
		var $status = $('#dsu-create-status');
		var data    = $(this).serializeArray();

		// Build POST data object
		var postData = { action: 'dsu_create_configs', nonce: dsuAdmin.nonce };
		data.forEach(function (field) { postData[field.name] = field.value; });

		$btn.prop('disabled', true).text('Creating…');
		$status.text('').removeClass('dsu-success dsu-error');

		$.post(dsuAdmin.ajaxUrl, postData, function (res) {
			$btn.prop('disabled', false).text('Create Configuration(s)');
			if (!res.success) {
				$status.text(res.data.message || 'Error.').addClass('dsu-error');
				return;
			}
			// Success: clear the builder rows, show message with link to Saved Displays
			$('#dsu-configs-list').empty();
			configIndex = 0;
			$('#dsu-builder-submit-wrap').hide();
			var savedUrl = dsuAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'options-general.php?page=dynamic-storage-units&tab=saved';
			var $msg = $('#dsu-builder-message');
			$msg.removeClass('notice-error').addClass('notice-success')
				.html('<p>' + escHtml(res.data.message) + ' <a href="' + savedUrl + '">View in Saved Displays →</a></p>')
				.show();
			renderPreview();
		}).fail(function () {
			$btn.prop('disabled', false).text('Create Configuration(s)');
			$status.text('Request failed.').addClass('dsu-error');
		});
	});

	$(document).on('click', '.dsu-delete-config', function () {
		if (!window.confirm(strings.confirmDelete)) return;
		$(this).closest('.dsu-config-row').remove();
		reindexConfigs();
		if ($('.dsu-config-row', '#dsu-configs-list').length === 0) {
			$('#dsu-builder-submit-wrap').hide();
		}
		renderPreview();
	});

	$(document).on('click', '.dsu-config-header', function (e) {
		if ($(e.target).closest('.dsu-delete-config, .dsu-toggle-config').length &&
		    !$(e.target).is('.dsu-toggle-config')) return;
		var $row  = $(this).closest('.dsu-config-row');
		var $body = $row.find('.dsu-config-body');
		var $icon = $row.find('.dsu-toggle-config');
		$body.toggle();
		$icon.html($body.is(':visible') ? '&#9660;' : '&#9654;');
	});

	$(document).on('change', '.dsu-soldout-radio', function () {
		var $settings = $(this).closest('.dsu-config-row').find('.dsu-waitlist-settings');
		$settings.toggle($(this).val() === 'waitlist' && $(this).is(':checked'));
		renderPreview();
	});

	/* Show/hide tiles handling options */
	$(document).on('change', 'input[name*="[show_size_tiles]"]', function () {
		$(this).closest('td').find('.dsu-tiles-handling-wrap').toggle($(this).is(':checked'));
	});

	/* Update preview when layout format changes */
	$(document).on('change', 'input[name*="[display_format]"]', function () {
		renderPreview();
	});

	/* Update preview when any sizing, show/hide, or soldout option changes */
	$(document).on('change',
		'input[name*="[img_size]"], input[name*="[title_size]"], ' +
		'input[name*="[special_size]"], input[name*="[price_size]"], ' +
		'input[name*="[scarcity_size]"], input[name*="[feature_tag_size]"], ' +
		'input[name*="[unit_type_filter_size]"], input[name*="[hide_all_tile]"], ' +
		'input[name*="[show_promo_bar]"], input[name*="[show_unit_type_filter]"], ' +
		'input[name*="[show_size_tiles]"], input[name*="[soldout_handling]"]',
		function () {
			if ( $('#dsu-preview-output').length ) renderPreview();
			if ( $('#dsu-preview-output-saved').length ) renderSavedPreview();
		}
	);

	/* Update preview when "All" label text changes */
	$(document).on('input', 'input[name*="[unit_type_all_label]"]', function () {
		if ( $('#dsu-preview-output').length ) renderPreview();
		if ( $('#dsu-preview-output-saved').length ) renderSavedPreview();
	});

	/* ---- Copy shortcode ---- */
	$(document).on('click', '.dsu-copy-shortcode', function () {
		var $input = $(this).siblings('.dsu-shortcode-input');
		if ( !$input.length ) { $input = $(this).closest('.dsu-saved-shortcode-row, .dsu-shortcode-copy-row').find('.dsu-shortcode-input'); }
		$input[0].select();
		document.execCommand('copy');
		var $btn = $(this);
		var orig = $btn.text();
		$btn.text('Copied!');
		setTimeout(function () { $btn.text(orig); }, 2000);
	});

	/* ---- Update shortcode when name changes ---- */
	$(document).on('input', '.dsu-config-name-input', function () {
		var val  = $(this).val().trim();
		var $row = $(this).closest('.dsu-config-row');
		var $sc  = $row.find('.dsu-shortcode-input');
		$row.find('.dsu-config-title').text(val || 'New Configuration');
		if ($sc.length) {
			$sc.val(val ? '[storage_units config="' + val + '"]' : '');
		}
	});

	function reindexConfigs() {
		$('.dsu-config-row').each(function (i) {
			$(this).attr('data-index', i);
			$(this).find('[name]').each(function () {
				var name = $(this).attr('name');
				if (name) {
					$(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
				}
			});
		});
		configIndex = $('.dsu-config-row').length;
	}

	function applyDefaultToRow($row, cfg) {
		// Copy display options (not name, facility_code, filter_label, max_units)
		var fmt = cfg.display_format || 'grid';
		$row.find('input[name*="[display_format]"][value="' + fmt + '"]').prop('checked', true);
		$row.find('select[name*="[sort]"]').val(cfg.sort || '');
		var tiles = !!( parseInt(cfg.show_size_tiles, 10) );
		$row.find('input[name*="[show_size_tiles]"]').prop('checked', tiles);
		$row.find('.dsu-tiles-handling-wrap').toggle(tiles);
		$row.find('input[name*="[unavailable_tile_handling]"][value="' + (cfg.unavailable_tile_handling || 'dim') + '"]').prop('checked', true);
		$row.find('input[name*="[tiles_alignment]"][value="' + (cfg.tiles_alignment || 'left') + '"]').prop('checked', true);
		$row.find('input[name*="[hide_tiles_mobile]"]').prop('checked', !!parseInt(cfg.hide_tiles_mobile, 10));
		$row.find('input[name*="[feature_tag_size]"][value="' + (cfg.feature_tag_size || 'sm') + '"]').prop('checked', true);
		$row.find('input[name*="[show_promo_bar]"]').prop('checked', !!parseInt(cfg.show_promo_bar, 10));
		$row.find('input[name*="[hide_promo_mobile]"]').prop('checked', !!parseInt(cfg.hide_promo_mobile, 10));
		$row.find('input[name*="[show_unit_type_filter]"]').prop('checked', !!parseInt(cfg.show_unit_type_filter, 10));
		var soldout = cfg.soldout_handling || 'hide';
		$row.find('input[name*="[soldout_handling]"][value="' + soldout + '"]').prop('checked', true);
		$row.find('.dsu-waitlist-settings').toggle(soldout === 'waitlist');
		if (cfg.waitlist_email)   $row.find('input[name*="[waitlist_email]"]').val(cfg.waitlist_email);
		if (cfg.waitlist_subject) $row.find('input[name*="[waitlist_subject]"]').val(cfg.waitlist_subject);
		if (cfg.waitlist_message) $row.find('textarea[name*="[waitlist_message]"]').val(cfg.waitlist_message);
	}

	/* ==========================================
	   LIVE PREVIEW
	========================================== */

	var previewFormat = 'list'; // 'grid' | 'list'

	var MOCK = {
		special: {
			label:        '10 × 20 Storage Unit',
			streetRate:   189,
			displayPrice: 122.85,
			specialLabel: '35% off your first 4 months when you sign up for Autopay',
			availTotal:   5,
			features:     ['Drive-up access', 'Roll-up door', 'Access controlled', '24/7 Video surveillance'],
			hasDiscount:  true,
		},
		plain: {
			label:        '5 × 10 Storage Unit',
			streetRate:   95,
			displayPrice: 95,
			specialLabel: '',
			availTotal:   2,
			features:     ['Climate controlled', 'Ground floor', 'Access controlled'],
			hasDiscount:  false,
		},
		soldout: {
			label:        '10 × 25 Parking',
			streetRate:   192,
			displayPrice: 192,
			specialLabel: '',
			availTotal:   0,
			features:     ['Drive-up access', 'Extra wide', 'Outdoor'],
			hasDiscount:  false,
		},
	};

function renderFeatureTiles( features ) {
		return features.map(function (feat) {
			// Detect emoji prefix (simple heuristic: first char is > 1 byte wide)
			var emojiMatch = feat.match(/^([\uD800-\uDBFF][\uDC00-\uDFFF]|©|®|[ -㌀]|\uD83C[\uDF00-\uDFFF]|\uD83D[\uDC00-\uDE4F])\s+(.+)/);
			if ( emojiMatch ) {
				return '<div class="dsu-feat-tile dsu-feat-tile--emoji">' +
					'<span class="dsu-feat-emoji">' + emojiMatch[1] + '</span>' +
					'<span>' + escHtml(emojiMatch[2]) + '</span>' +
					'</div>';
			}
			return '<div class="dsu-feat-tile dsu-feat-tile--plain"><span>' + escHtml(feat) + '</span></div>';
		}).join('');
	}

	function renderNoCardSvg() {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/><line x1="17" y1="15" x2="21" y2="15" opacity="0.4"/></svg>';
	}

	function renderListCard( unit, isSoldout, soldoutHandling, featTagSize ) {
		var rateHtml = '';
		if ( isSoldout ) {
			rateHtml = '<div class="dsu-pricing-soldout">Sold Out</div>';
		} else if ( unit.hasDiscount ) {
			rateHtml =
				'<div class="dsu-pricing-special">' +
				'<div class="dsu-online-rate-label">Online Only Rate</div>' +
				'<div class="dsu-street-rate-strike">$' + unit.streetRate.toFixed(2) + '<span class="dsu-per-month-sm">/mo</span></div>' +
				'<div class="dsu-special-rate-main">$' + unit.displayPrice.toFixed(2) + '<span class="dsu-per-month-sm">/mo</span><sup class="dsu-asterisk">*</sup></div>' +
				'<p class="dsu-reserve-desc">Rent or reserve today and lock in this special.</p>' +
				'<p class="dsu-no-card-line">No credit card needed to reserve <span class="dsu-no-card-icon">' + renderNoCardSvg() + '</span></p>' +
				'</div>';
		} else {
			rateHtml =
				'<div class="dsu-pricing-plain">' +
				'<div class="dsu-price-plain-value">$' + unit.streetRate.toFixed(0) + '<span class="dsu-per-month-sm">/mo</span></div>' +
				( unit.availTotal > 0 && unit.availTotal <= 2 ? '<p class="dsu-scarcity-text">Only ' + unit.availTotal + ' left!</p>' : '' ) +
				'</div>';
		}

		var ctaHtml = '';
		if ( !isSoldout ) {
			ctaHtml = '<a href="#" class="dsu-btn dsu-btn-primary">Rent Online</a><a href="#" class="dsu-btn dsu-btn-secondary">Reserve</a>';
		} else if ( soldoutHandling === 'waitlist' ) {
			ctaHtml = '<button type="button" class="dsu-btn dsu-btn-waitlist">Join Waitlist</button>';
		}

		return '<div class="dsu-list-card">' +
			'<div class="dsu-list-image-col"><div class="dsu-list-image-wrap">' +
			'<img src="' + window.dsuAdmin.pluginUrl + 'assets/img/placeholder.svg" alt="" />' +
			'</div></div>' +
			'<div class="dsu-list-info-col">' +
			'<h3 class="dsu-unit-size">' + escHtml(unit.label) + '</h3>' +
			'<div class="dsu-feat-icons-row' + (featTagSize && featTagSize !== 'sm' ? ' dsu-feat-icons-row--' + featTagSize : '') + '">' + renderFeatureTiles(unit.features) + '</div>' +
			'</div>' +
			'<div class="dsu-list-rate-col">' + rateHtml + '</div>' +
			'<div class="dsu-list-cta-col">' + ctaHtml + '</div>' +
			'</div>';
	}

	function renderGridCard( unit, isSoldout, soldoutHandling, featTagSize ) {
		var specialHtml = '';
		if ( unit.hasDiscount && unit.specialLabel ) {
			specialHtml = '<div class="dsu-special-callout">&#11088; ' + escHtml(unit.specialLabel) + '</div>';
		}

		var availHtml = '';
		if ( isSoldout ) {
			availHtml = '<div class="dsu-availability dsu-soldout">Sold Out</div>';
		} else if ( unit.availTotal > 0 && unit.availTotal <= 2 ) {
			availHtml = '<div class="dsu-availability dsu-low-stock">Only ' + unit.availTotal + ' left</div>';
		}

		var priceHtml = '<div class="dsu-price">$' + Math.round(unit.displayPrice) + '<span class="dsu-per-month">/month</span>';
		if ( unit.hasDiscount ) {
			priceHtml += '<span class="dsu-original-price">$' + Math.round(unit.streetRate) + '</span>';
		}
		priceHtml += '</div>';

		var ctaHtml = '';
		if ( !isSoldout ) {
			ctaHtml =
				'<a href="#" class="dsu-btn dsu-btn-primary">Rent Now</a>' +
				'<a href="#" class="dsu-btn dsu-btn-secondary">Reserve</a>';
		} else if ( soldoutHandling === 'waitlist' ) {
			ctaHtml = '<button type="button" class="dsu-btn dsu-btn-waitlist">Join Waitlist</button>';
		}

		var featHtml = '';
		if ( unit.features.length ) {
			var featRowClass = 'dsu-feat-icons-row' + (featTagSize && featTagSize !== 'sm' ? ' dsu-feat-icons-row--' + featTagSize : '');
			featHtml = '<div class="' + featRowClass + '">' + renderFeatureTiles(unit.features) + '</div>';
		}

		return '<div class="dsu-unit-card">' +
			'<div class="dsu-card-image"><img src="' + window.dsuAdmin.pluginUrl + 'assets/img/placeholder.svg" alt="" /></div>' +
			'<div class="dsu-card-body">' +
			'<h3 class="dsu-unit-size">' + escHtml(unit.label) + '</h3>' +
			specialHtml + featHtml + availHtml + priceHtml +
			'<div class="dsu-cta-buttons">' + ctaHtml + '</div>' +
			'</div></div>';
	}

	/* ==========================================
	   PREVIEW (shared render logic)
	========================================== */

	function buildPreviewUnits( groups ) {
		var withSpecial = [], plainAvail = [], soldout = [];
		groups.forEach(function (g) {
			var avail = g.availableTotal || 0;
			var sp    = g.availableSpecial;
			var hasSp = !!(sp && typeof sp === 'object' && (sp.specialRate || sp.rate));
			if      (avail <= 0) soldout.push(g);
			else if (hasSp)      withSpecial.push(g);
			else                 plainAvail.push(g);
		});

		var picks = [];
		if (withSpecial.length) picks.push({ g: withSpecial[0], soldout: false });
		if (plainAvail.length)  picks.push({ g: plainAvail[0],  soldout: false });
		if (soldout.length)     picks.push({ g: soldout[0],     soldout: true  });

		// Pad to 3 using additional available groups
		var extra = withSpecial.concat(plainAvail);
		for (var i = 0; picks.length < 3 && i < extra.length; i++) {
			var already = picks.some(function (p) { return p.g.id === extra[i].id; });
			if (!already) picks.push({ g: extra[i], soldout: false });
		}

		return picks.slice(0, 3).map(function (p) {
			var g         = p.g;
			var sp        = g.availableSpecial;
			var hasSp     = !!(sp && typeof sp === 'object' && (sp.specialRate || sp.rate));
			var spRate    = hasSp ? parseFloat(sp.specialRate || sp.rate || 0) : 0;
			var street    = parseFloat(g.streetRate || 0);
			var hasDisc   = hasSp && spRate > 0 && spRate < street;
			return {
				label:        g.label || '',
				streetRate:   street,
				displayPrice: hasDisc ? spRate : street,
				specialLabel: hasSp ? (sp.label || sp.promotionName || 'Special offer') : '',
				availTotal:   g.availableTotal || 0,
				features:     [],
				hasDiscount:  hasDisc,
			};
		});
	}

	function getPreviewOpts( $scope ) {
		return {
			format:       $scope.find('input[name*="[display_format]"]:checked').val()           || 'list',
			soldout:      $scope.find('input[name*="[soldout_handling]"]:checked').val()         || 'hide',
			imgSize:      $scope.find('input[name*="[img_size]"]:checked').val()                 || 'md',
			titleSize:    $scope.find('input[name*="[title_size]"]:checked').val()               || 'md',
			specialSize:  $scope.find('input[name*="[special_size]"]:checked').val()             || 'md',
			priceSize:    $scope.find('input[name*="[price_size]"]:checked').val()               || 'md',
			scarcitySize: $scope.find('input[name*="[scarcity_size]"]:checked').val()            || 'md',
			featTagSize:  $scope.find('input[name*="[feature_tag_size]"]:checked').val()         || 'sm',
			utfSize:      $scope.find('input[name*="[unit_type_filter_size]"]:checked').val()    || 'md',
			allLabel:     $scope.find('input[name*="[unit_type_all_label]"]').val()              || '',
			hideAllTile:  $scope.find('input[name*="[hide_all_tile]"]').is(':checked'),
			showPromo:    $scope.find('input[name*="[show_promo_bar]"]').is(':checked'),
			showFilter:   $scope.find('input[name*="[show_unit_type_filter]"]').is(':checked'),
			showTiles:    $scope.find('input[name*="[show_size_tiles]"]').is(':checked'),
		};
	}

	function renderPreviewToTarget( $output, opts ) {
		if ( typeof opts === 'string' ) { opts = { format: opts }; }

		var format       = opts.format       || 'list';
		var soldoutH     = opts.soldout      || 'hide';
		var imgSize      = opts.imgSize      || 'md';
		var titleSize    = opts.titleSize    || 'md';
		var specialSize  = opts.specialSize  || 'md';
		var priceSize    = opts.priceSize    || 'md';
		var scarcitySize = opts.scarcitySize || 'md';
		var featTagSize  = opts.featTagSize  || 'sm';
		var utfSize      = opts.utfSize      || 'md';
		var allLabel     = opts.allLabel     || 'All';
		var hideAllTile  = !!opts.hideAllTile;
		var showPromo    = !!opts.showPromo;
		var showFilter   = !!opts.showFilter;
		var showTiles    = !!opts.showTiles;

		var units;
		if (previewGroups && previewGroups.length) {
			units = buildPreviewUnits(previewGroups).map(function (u) {
				return { data: u, soldout: u.availTotal <= 0, soldoutHandling: soldoutH };
			});
		} else {
			units = [
				{ data: MOCK.special, soldout: false, soldoutHandling: soldoutH },
				{ data: MOCK.plain,   soldout: false, soldoutHandling: soldoutH },
				{ data: MOCK.soldout, soldout: true,  soldoutHandling: soldoutH },
			];
		}

		var notice = previewGroups
			? '<p style="font-size:11px;color:#2e7d32;margin:0 0 8px;"><strong>&#10003; Live data</strong> &mdash; ' + previewGroups.length + ' groups from API</p>'
			: '<p style="font-size:11px;color:#888;margin:0 0 8px;">Preview data &mdash; configure API to see live units</p>';

		// Unit type filter
		var filterHtml = '';
		if ( showFilter ) {
			var unitTypes   = dsuAdmin.unitTypes || [];
			var utfClass    = utfSize !== 'md' ? ' dsu-utf-' + utfSize : '';
			filterHtml = '<div class="dsu-unit-type-wrap' + utfClass + '"><div class="dsu-unit-type-filter">' +
				'<button type="button" class="dsu-unit-type-btn dsu-unit-type-btn--active">' + escHtml(allLabel || 'All') + '</button>';
			unitTypes.forEach(function(ut) {
				filterHtml += '<button type="button" class="dsu-unit-type-btn">' + escHtml(ut.label) + '</button>';
			});
			filterHtml += '</div></div>';
		}

		// Promo bar
		var promoHtml = '';
		if ( showPromo ) {
			promoHtml =
				'<div class="dsu-promo-bar-wrap">' +
				'<button type="button" class="dsu-promo-bar">' +
				'<span class="dsu-promo-bar-eyebrow">Limited time offer</span>' +
				'<div class="dsu-promo-bar-main"><strong>35% OFF your first 4 months</strong> when you sign up for Autopay</div>' +
				'</button></div>';
		}

		// Category tiles
		var tilesHtml = '';
		if ( showTiles ) {
			var cats = dsuAdmin.categories || [];
			var allTileHtml = hideAllTile ? '' :
				'<button type="button" class="dsu-cat-tile dsu-cat-tile--all dsu-cat-tile--active">' +
				'<span class="dsu-cat-tile-label">All Units</span>' +
				'<span class="dsu-cat-tile-desc">Browse everything</span>' +
				'</button>';
			var tileItems = cats.slice(0, 5).map(function(cat) {
				return '<button type="button" class="dsu-cat-tile">' +
					'<span class="dsu-cat-tile-label">' + escHtml(cat.label) + '</span>' +
					'<span class="dsu-cat-tile-price">from $xx/mo</span>' +
					'</button>';
			}).join('');
			tilesHtml = '<div class="dsu-cat-tiles-wrap"><div class="dsu-cat-tiles">' + allTileHtml + tileItems + '</div></div>';
		}

		// Cards
		var gridClass = 'dsu-grid' + ( format === 'list' ? ' dsu-grid--list' : '' );
		var cardsHtml = '';
		units.forEach(function (u) {
			cardsHtml += format === 'list'
				? renderListCard( u.data, u.soldout, u.soldoutHandling, featTagSize )
				: renderGridCard( u.data, u.soldout, u.soldoutHandling, featTagSize );
		});

		// Wrapper size modifier classes
		var wrapClasses = 'dsu-wrap';
		if ( imgSize      !== 'md' ) { wrapClasses += ' dsu-img-'      + imgSize; }
		if ( titleSize    !== 'md' ) { wrapClasses += ' dsu-title-'    + titleSize; }
		if ( specialSize  !== 'md' ) { wrapClasses += ' dsu-special-'  + specialSize; }
		if ( priceSize    !== 'md' ) { wrapClasses += ' dsu-price-'    + priceSize; }
		if ( scarcitySize !== 'md' ) { wrapClasses += ' dsu-scarcity-' + scarcitySize; }

		// Apply configured brand colors as CSS custom properties
		var colors  = dsuAdmin.colors || {};
		var cssVars = '';
		if ( colors.primary )       { cssVars += '--dsu-primary:'        + colors.primary       + ';'; }
		if ( colors.primaryText )   { cssVars += '--dsu-primary-text:'   + colors.primaryText   + ';'; }
		if ( colors.secondary )     { cssVars += '--dsu-secondary:'      + colors.secondary     + ';'; }
		if ( colors.secondaryText ) { cssVars += '--dsu-secondary-text:' + colors.secondaryText + ';'; }
		if ( colors.promoBar )      { cssVars += '--dsu-promo-bg:'       + colors.promoBar      + ';'; }
		var styleAttr = cssVars ? ' style="' + cssVars + '"' : '';

		$output.html(
			notice +
			'<div class="' + wrapClasses + '"' + styleAttr + '>' +
			filterHtml + promoHtml + tilesHtml +
			'<div class="' + gridClass + '">' + cardsHtml + '</div>' +
			'</div>'
		);
	}

	function fetchPreviewGroups() {
		if (!facilityCode || !$('#dsu-preview-output').length) return;
		$.post(dsuAdmin.ajaxUrl, {
			action:        'dsu_fetch_unit_groups',
			nonce:         dsuAdmin.nonce,
			facility_code: facilityCode,
		}, function (res) {
			if (!res.success || !res.data || !res.data.groups || !res.data.groups.length) return;
			previewGroups = res.data.groups;
			renderPreview();
		});
	}

	function renderPreview() {
		var $output = $('#dsu-preview-output');
		if ( !$output.length ) return;
		var opts = getPreviewOpts( $('#dsu-configs-list') );
		previewFormat = opts.format;
		renderPreviewToTarget( $output, opts );
	}

	function renderSavedPreview() {
		var $output = $('#dsu-preview-output-saved');
		if ( !$output.length ) return;
		var opts = getPreviewOpts( $('.dsu-edit-panel:visible') );
		renderPreviewToTarget( $output, opts );
	}

	/* Layout change → re-render appropriate preview */
	$(document).on('change', 'input[name*="[display_format]"]', function () {
		if ( $('#dsu-preview-output').length ) renderPreview();
		if ( $('#dsu-preview-output-saved').length ) renderSavedPreview();
	});

	/* Initial builder preview */
	if ( $('#dsu-preview-output').length ) {
		renderPreview();
		fetchPreviewGroups();
	}

	/* ==========================================
	   SAVED DISPLAYS — list table + edit panel
	========================================== */

	/* Edit button → show builder-like edit view */
	$(document).on('click', '.dsu-edit-config-btn', function () {
		var idx = $(this).data('index');
		$('#dsu-saved-list-wrap').hide();
		$('.dsu-edit-panel').hide();
		var $panel = $('.dsu-edit-panel[data-index="' + idx + '"]');
		$panel.show();
		var name = $panel.find('.dsu-config-name-input').val() || 'Configuration';
		$('#dsu-saved-edit-title').text('Editing: ' + name);
		$('#dsu-saved-edit-view').show();
		renderSavedPreview();
		var top = $('#dsu-saved-edit-view').offset();
		if ( top ) { $('html, body').animate({ scrollTop: top.top - 80 }, 200); }
	});

	/* Back button → return to list */
	$('#dsu-saved-back-btn').on('click', function () {
		$('#dsu-saved-edit-view').hide();
		$('#dsu-saved-list-wrap').show();
	});

	/* Edit form submit → AJAX update */
	$(document).on('submit', '.dsu-edit-config-form', function (e) {
		e.preventDefault();
		var $form        = $(this);
		var $panel       = $form.closest('.dsu-edit-panel');
		var $btn         = $form.find('.dsu-save-config-btn');
		var $status      = $form.find('.dsu-edit-status');
		var originalName = $form.data('original-name');
		var idx          = $panel.data('index');
		var data         = $form.serializeArray();

		var postData = {
			action:        'dsu_update_config',
			nonce:         dsuAdmin.nonce,
			original_name: originalName,
		};
		data.forEach(function (f) { postData[f.name] = f.value; });

		$btn.prop('disabled', true).text('Saving…');
		$status.text('').removeClass('dsu-success dsu-error');

		$.post(dsuAdmin.ajaxUrl, postData, function (res) {
			$btn.prop('disabled', false).text('Save Changes');
			if (!res.success) {
				$status.text(res.data.message || 'Error.').addClass('dsu-error');
				return;
			}
			var newName   = res.data.new_name || originalName;
			var newFormat = $form.find('input[name*="[display_format]"]:checked').val() || 'grid';
			$status.text(res.data.message || 'Saved.').addClass('dsu-success');

			// Update form's original name reference
			$form.data('original-name', newName);

			// Update edit view title + shortcode display in panel
			$('#dsu-saved-edit-title').text('Editing: ' + newName);
			$panel.find('.dsu-shortcode-input').first().val('[storage_units config="' + newName + '"]');

			// Update table row
			var $row = $('#dsu-saved-list-wrap tbody tr[data-index="' + idx + '"]');
			$row.find('td:nth-child(1) strong').text(newName);
			$row.find('td:nth-child(2)').text(newFormat.charAt(0).toUpperCase() + newFormat.slice(1));
			$row.find('.dsu-shortcode-input').val('[storage_units config="' + newName + '"]');
			$row.find('.dsu-copy-shortcode').attr('aria-label', 'Copy shortcode for ' + newName);
			$row.find('.dsu-edit-config-btn').attr('aria-label', 'Edit ' + newName);
			$row.find('.dsu-delete-config-btn').data('config-name', newName).attr('aria-label', 'Delete ' + newName);
		}).fail(function () {
			$btn.prop('disabled', false).text('Save Changes');
			$status.text('Request failed.').addClass('dsu-error');
		});
	});

	/* Set a config as the builder default */
	$(document).on('click', '.dsu-set-default-btn', function () {
		var $btn = $(this);
		var name = $btn.data('config-name');
		$btn.prop('disabled', true);

		$.post(dsuAdmin.ajaxUrl, {
			action:      'dsu_set_default_config',
			nonce:       dsuAdmin.nonce,
			config_name: name,
		}, function (res) {
			$btn.prop('disabled', false);
			if (!res.success) { return; }

			// Reset any existing default badge to a button
			$('.dsu-default-badge').each(function () {
				var rowName = $(this).closest('tr').find('td:first strong').text();
				$(this).replaceWith(
					'<button type="button" class="button button-small dsu-set-default-btn" data-config-name="' +
					escHtml(rowName) + '">' + escHtml('Set as Default') + '</button>'
				);
			});

			// Replace clicked button with badge
			$btn.replaceWith(
				'<span class="dsu-default-badge" aria-label="Default config">⭐ Default</span>'
			);

			// Update in-memory reference so new configs pre-populate from this one
			dsuAdmin.defaultConfig = name;
		}).fail(function () { $btn.prop('disabled', false); });
	});

	/* Delete from table → AJAX delete */
	$(document).on('click', '.dsu-delete-config-btn', function () {
		if (!window.confirm(strings.confirmDelete)) return;
		var name = $(this).data('config-name');
		var $row = $(this).closest('tr');

		$.post(dsuAdmin.ajaxUrl, {
			action:      'dsu_delete_config',
			nonce:       dsuAdmin.nonce,
			config_name: name,
		}, function (res) {
			if (!res.success) { return; }
			$row.fadeOut(200, function () {
				$(this).remove();
				if ($('#dsu-saved-list-wrap tbody tr').length === 0) {
					var builderUrl = dsuAdmin.ajaxUrl.replace('admin-ajax.php', '') +
						'options-general.php?page=dynamic-storage-units&tab=configs';
					$('#dsu-saved-list-wrap').append(
						'<div class="notice notice-info inline" style="margin-top:16px;"><p>' +
						'No configurations saved yet. ' +
						'<a href="' + builderUrl + '">Go to Display Configuration Builder</a> to create one.' +
						'</p></div>'
					);
				}
			});
		});
	});

	/* ---- Color pickers ---- */
	if ( $.fn.wpColorPicker ) {
		$('.dsu-color-picker').wpColorPicker();
	}

	/* ---- Completion chime (builder tab only, one-time) ---- */
	if ( $('#dsu-preview-output').length && window.AudioContext ) {
		try {
			var ctx = new AudioContext();
			var notes = [523.25, 659.25, 783.99, 1046.5];
			notes.forEach(function(freq, i) {
				var osc  = ctx.createOscillator();
				var gain = ctx.createGain();
				osc.connect(gain);
				gain.connect(ctx.destination);
				osc.type = 'sine';
				osc.frequency.value = freq;
				gain.gain.setValueAtTime(0, ctx.currentTime + i * 0.12);
				gain.gain.linearRampToValueAtTime(0.18, ctx.currentTime + i * 0.12 + 0.01);
				gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.12 + 0.22);
				osc.start(ctx.currentTime + i * 0.12);
				osc.stop(ctx.currentTime + i * 0.12 + 0.25);
			});
		} catch(e) { /* audio not available */ }
	}

	/* ---- Fetch facility info (Schema Variables tab) ---- */
	$(document).on('click', '#dsu-fetch-facility-info', function () {
		var $btn    = $(this);
		var $status = $('#dsu-fetch-facility-info-status');
		$btn.prop('disabled', true).text('Fetching…');
		$status.text('').removeClass('dsu-success dsu-error');

		$.post(dsuAdmin.ajaxUrl, {
			action: 'dsu_fetch_facility_info',
			nonce:  dsuAdmin.nonce,
		}, function (res) {
			$btn.prop('disabled', false).text('Fetch from API');
			if (!res.success) {
				$status.text(res.data.message || 'Fetch failed.').addClass('dsu-error');
				return;
			}
			var d = res.data;
			if (d.name)             $('#dsu_schema_name').val(d.name);
			if (d.telephone)        $('#dsu_schema_telephone').val(d.telephone);
			if (d.street_address)   $('#dsu_schema_street_address').val(d.street_address);
			if (d.address_locality) $('#dsu_schema_address_locality').val(d.address_locality);
			if (d.address_region)   $('#dsu_schema_address_region').val(d.address_region);
			if (d.postal_code)      $('#dsu_schema_postal_code').val(d.postal_code);
			if (d.address_country)  $('#dsu_schema_address_country').val(d.address_country);
			if (d.latitude)         $('#dsu_schema_latitude').val(d.latitude);
			if (d.longitude)        $('#dsu_schema_longitude').val(d.longitude);

			// Auto-check amenity checkboxes returned by the API
			if (d.amenities && d.amenities.length) {
				$('input[name="dsu_schema_settings[amenities][]"]').each(function () {
					if (d.amenities.indexOf($(this).val()) !== -1) {
						$(this).prop('checked', true);
					}
				});
			}

			// Auto-check payment checkboxes returned by the API
			if (d.payment_accepted && d.payment_accepted.length) {
				$('input[name="dsu_schema_settings[payment_accepted][]"]').each(function () {
					if (d.payment_accepted.indexOf($(this).val()) !== -1) {
						$(this).prop('checked', true);
					}
				});
			}

			var filled = ['Name', 'Telephone', 'Address'];
			if (d.latitude && d.longitude) { filled.push('Coordinates'); }
			if (d.amenities && d.amenities.length) { filled.push(d.amenities.length + ' amenities'); }
			if (d.payment_accepted && d.payment_accepted.length) { filled.push(d.payment_accepted.length + ' payment types'); }
			$status.text('Populated from API: ' + filled.join(', ') + '. Save to apply.').addClass('dsu-success');

			// Show raw API dump
			if (d.raw) {
				var pretty = JSON.stringify(d.raw, null, 2);
				$('#dsu-facility-raw-dump').show()
					.find('pre').text(pretty);
			}
		}).fail(function () {
			$btn.prop('disabled', false).text('Fetch from API');
			$status.text('Request failed.').addClass('dsu-error');
		});
	});

	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

}(jQuery));
