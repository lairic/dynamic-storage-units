/* global dsuData, jQuery */
(function ($) {
	'use strict';

	var $modal = $('#dsu-waitlist-modal');

	/* ---- Per-config filter state ---- */
	// Tracks active category slug and promo-bar toggle independently per config name.
	// Both dimensions combine as AND: a card is visible only if it matches both filters.
	var filterState = {};

	function getState(config) {
		if (!filterState[config]) {
			filterState[config] = { category: '', promo: false, unitType: '' };
		}
		return filterState[config];
	}

	function applyFilters(config) {
		var state = getState(config);

		// Step 1: filter tiles first — this may reset state.category if the
		// active tile becomes hidden, so cards must be filtered after.
		var $tilesWrap = $('.dsu-cat-tiles-wrap[data-config="' + config + '"]');
		if ($tilesWrap.length) {
			$tilesWrap.find('.dsu-cat-tile').each(function () {
				var $tile    = $(this);
				var tileType = ($tile.attr('data-unit-type') || '').toLowerCase();
				var show     = !state.unitType || !tileType || tileType === state.unitType;
				$tile.toggle(show);
				if (!show && $tile.hasClass('dsu-cat-tile--active')) {
					$tile.removeClass('dsu-cat-tile--active').attr('aria-pressed', 'false');
					$tilesWrap.find('.dsu-cat-tile[data-category=""]')
						.addClass('dsu-cat-tile--active').attr('aria-pressed', 'true');
					state.category = '';
				}
			});
		}

		// Step 2: filter cards using the (possibly updated) state.category
		var $grid  = $('.dsu-grid[data-config="' + config + '"]');
		var $cards = $grid.find('.dsu-list-card, .dsu-unit-card');

		$cards.each(function () {
			var $card      = $(this);
			var cardType   = ($card.attr('data-unit-type') || '').toLowerCase();
			var catMatch   = !state.category || $card.attr('data-category') === state.category;
			var promoMatch = !state.promo    || $card.attr('data-has-promo') === '1';
			var typeMatch  = !state.unitType || !cardType || cardType === state.unitType;
			$card.toggle(catMatch && promoMatch && typeMatch);
		});

		var $noResults = $grid.find('.dsu-no-results');
		if ($noResults.length) {
			$noResults.toggle($cards.filter(':visible').length === 0);
		}

		syncUnitTypeFilter(config);
	}

	function syncUnitTypeFilter(config) {
		var $wrap = $('.dsu-unit-type-wrap[data-config="' + config + '"]');
		if (!$wrap.length) return;

		var state  = getState(config);
		var $grid  = $('.dsu-grid[data-config="' + config + '"]');
		var $cards = $grid.find('.dsu-list-card, .dsu-unit-card');

		// Collect which unit types appear in cards that pass category + promo filters
		// (intentionally ignoring the unit type filter itself so we don't hide options
		// the user selected, and so we always show what's truly available)
		var presentTypes = {};
		$cards.each(function () {
			var $card      = $(this);
			var catMatch   = !state.category || $card.attr('data-category') === state.category;
			var promoMatch = !state.promo    || $card.attr('data-has-promo') === '1';
			if (catMatch && promoMatch) {
				var type = ($card.attr('data-unit-type') || '').toLowerCase();
				if (type) { presentTypes[type] = true; }
			}
		});

		// Show/hide individual type buttons; reset selection if active type vanished
		var visibleCount = 0;
		$wrap.find('.dsu-unit-type-btn').each(function () {
			var btnType = ($(this).attr('data-unit-type') || '').toLowerCase();
			if (!btnType) return; // skip the "All" button
			var show = !!presentTypes[btnType];
			$(this).toggle(show);
			if (show) visibleCount++;
			if (!show && state.unitType === btnType) {
				$wrap.find('.dsu-unit-type-btn[data-unit-type=""]')
					.addClass('dsu-unit-type-btn--active').attr('aria-pressed', 'true');
				$(this).removeClass('dsu-unit-type-btn--active').attr('aria-pressed', 'false');
				state.unitType = '';
			}
		});

		// Hide the entire filter if fewer than 2 type options are available
		$wrap.toggle(visibleCount >= 2);
	}

	/* ---- Category tile filtering ---- */
	$(document).on('click', '.dsu-cat-tile:not([disabled])', function () {
		var $tile  = $(this);
		var cat    = $tile.data('category') || '';
		var $wrap  = $tile.closest('.dsu-cat-tiles-wrap');
		var config = $wrap.data('config');

		$wrap.find('.dsu-cat-tile').removeClass('dsu-cat-tile--active').attr('aria-pressed', 'false');
		$tile.addClass('dsu-cat-tile--active').attr('aria-pressed', 'true');

		getState(config).category = cat;
		applyFilters(config);
	});

	/* ---- Promo bar filter ---- */
	$(document).on('click', '.dsu-promo-bar', function () {
		var $bar   = $(this);
		var config = $bar.data('config');
		var active = $bar.attr('aria-pressed') !== 'true';

		$bar.attr('aria-pressed', active ? 'true' : 'false');
		$bar.toggleClass('dsu-promo-bar--active', active);

		getState(config).promo = active;
		applyFilters(config);
	});

	/* ---- Unit type filter ---- */
	$(document).on('click', '.dsu-unit-type-btn', function () {
		var $btn   = $(this);
		var type   = $btn.attr('data-unit-type') || '';
		var $wrap  = $btn.closest('.dsu-unit-type-wrap');
		var config = $wrap.data('config');

		$wrap.find('.dsu-unit-type-btn').removeClass('dsu-unit-type-btn--active').attr('aria-pressed', 'false');
		$btn.addClass('dsu-unit-type-btn--active').attr('aria-pressed', 'true');

		getState(config).unitType = type;
		applyFilters(config);
	});

	/* ---- Open waitlist modal ---- */
	$(document).on('click', '.dsu-open-waitlist', function () {
		var $btn = $(this);

		$('#dsu-wl-group-label').val($btn.data('group-label') || '');
		$('#dsu-wl-group-id').val($btn.data('group-id') || '');
		$('#dsu-wl-facility-code').val($btn.data('facility-code') || '');
		$('#dsu-wl-config-name').val($btn.data('config-name') || '');

		$('#dsu-waitlist-form')[0].reset();
		// Re-apply hidden fields after reset
		$('#dsu-wl-group-label').val($btn.data('group-label') || '');
		$('#dsu-wl-group-id').val($btn.data('group-id') || '');
		$('#dsu-wl-facility-code').val($btn.data('facility-code') || '');
		$('#dsu-wl-config-name').val($btn.data('config-name') || '');

		clearMessages();
		$('.dsu-wl-submit').prop('disabled', false).show();
		openModal();
	});

	/* ---- Close modal ---- */
	$(document).on('click', '.dsu-close-modal', closeModal);

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' && !$modal.attr('hidden')) {
			closeModal();
		}
	});

	/* ---- Submit waitlist form ---- */
	$('#dsu-waitlist-form').on('submit', function (e) {
		e.preventDefault();
		clearMessages();

		var $submit  = $('.dsu-wl-submit');
		var formData = $(this).serializeArray();
		formData.push({ name: 'action', value: 'dsu_waitlist_submit' });
		formData.push({ name: 'nonce',  value: dsuData.nonce });

		$submit.prop('disabled', true);

		$.post(dsuData.ajaxUrl, $.param(formData), function (res) {
			if (res.success) {
				showMessage(res.data.message, 'dsu-success');
				$('#dsu-waitlist-form input[type=text], #dsu-waitlist-form input[type=email], #dsu-waitlist-form input[type=tel]').val('');
				$submit.hide();
			} else {
				showMessage(res.data.message || 'Something went wrong. Please try again.', 'dsu-error');
				$submit.prop('disabled', false);
			}
		}).fail(function () {
			showMessage('Request failed. Please try again.', 'dsu-error');
			$submit.prop('disabled', false);
		});
	});

	/* ---- Helpers ---- */
	function openModal() {
		$modal.removeAttr('hidden');
		$('body').css('overflow', 'hidden');
		$modal.find('.dsu-modal-close').focus();
	}

	function closeModal() {
		$modal.attr('hidden', true);
		$('body').css('overflow', '');
		clearMessages();
	}

	function showMessage(msg, cssClass) {
		$('#dsu-wl-messages')
			.removeClass('dsu-success dsu-error')
			.addClass(cssClass)
			.text(msg);
	}

	function clearMessages() {
		$('#dsu-wl-messages').removeClass('dsu-success dsu-error').text('');
	}

	/* ---- Tier modal: open ---- */
	$(document).on('click', '.dsu-open-tier-modal', function () {
		var id       = $(this).data('modal');
		var $overlay = $('#' + id);
		$overlay.removeAttr('hidden');
		$('body').css('overflow', 'hidden');
		$overlay.find('.dsu-tier-modal-close').focus();
	});

	/* ---- Tier modal: close via button ---- */
	$(document).on('click', '.dsu-close-tier-modal', function () {
		$(this).closest('.dsu-tier-modal-overlay').attr('hidden', true);
		$('body').css('overflow', '');
	});

	/* ---- Tier modal: close via backdrop click ---- */
	$(document).on('click', '.dsu-tier-modal-overlay', function (e) {
		if ($(e.target).is('.dsu-tier-modal-overlay')) {
			$(this).attr('hidden', true);
			$('body').css('overflow', '');
		}
	});

	/* ---- Init: sync unit type filter on page load for each config ---- */
	$(function () {
		$('.dsu-unit-type-wrap[data-config]').each(function () {
			syncUnitTypeFilter($(this).data('config'));
		});
	});

	/* ---- Tier modal: close on Escape ---- */
	$(document).on('keydown.dsutier', function (e) {
		if (e.key === 'Escape') {
			var $open = $('.dsu-tier-modal-overlay:not([hidden])');
			if ($open.length) {
				$open.first().attr('hidden', true);
				$('body').css('overflow', '');
			}
		}
	});

}(jQuery));
