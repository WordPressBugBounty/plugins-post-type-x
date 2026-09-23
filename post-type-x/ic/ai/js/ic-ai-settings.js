(function ($) {
	'use strict';

	var originalHtml = '';
	var isBillingView = false;
	var billingRenderId = 0;
	var waitingTimer = null;
	var waitingStartedAt = 0;
	var pendingHeadAssets = {};
	var originalCheckoutAjaxGlobals = null;
	var checkoutAjaxNonces = {};

	function container() {
		var $secondary = $('#ic-ai-settings-secondary').first();

		return $secondary.length ? $secondary : $('#implecode_settings').first();
	}

	function escapeHtml(value) {
		return $('<div>').text(value == null ? '' : String(value)).html();
	}

	function fallbackRedirect(url) {
		if (url) {
			window.location.href = url;
		}
	}

	function licenseField() {
		return $('input[name="ic_ai_settings[license_key]"]').first();
	}

	function settingsForm() {
		return $('.ic-ai-settings-form').first();
	}

	function setBillingFormHidden(hidden) {
		var $form = settingsForm();

		if (!$form.length) {
			return;
		}
		$form.toggleClass('ic-ai-billing-form-hidden', !!hidden);
	}

	function revealManualSettings() {
		$('.ic-ai-settings-form').removeClass('ic-ai-manual-settings-hidden');
	}

	function showManualKeyNotice() {
		var $field = licenseField();
		var $note = $('.ic-ai-manual-key-note').first();

		revealManualSettings();

		if ($note.length) {
			$note.show();
		}
		if ($field.length) {
			$field.trigger('focus');
			if ($field[0] && typeof $field[0].scrollIntoView === 'function') {
				$field[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		}
	}

	function backButtonHtml() {
		return '<p class="ic-ai-billing-back-wrap"><button type="button" class="button button-secondary ic-ai-billing-back">' +
			escapeHtml(icAISettings.messages.back) + '</button></p>';
	}

	function selectionFromForm($form) {
		var selection = {};

		$.each($form.serializeArray(), function (_, field) {
			if (field && field.name) {
				selection[field.name] = field.value;
			}
		});

		return selection;
	}

	function setLoading(message) {
		container().html('<div class="notice notice-info inline"><p>' + escapeHtml(message) + '</p></div>');
	}

	function restoreOriginalView() {
		var $container = container();

		if (originalHtml) {
			$container.html(originalHtml);
		}
		setBillingFormHidden(false);
		$container
			.removeAttr('data-ic-ai-billing-mode')
			.removeAttr('data-ic-ai-billing-fallback')
			.removeAttr('data-ic-ai-billing-public');
		isBillingView = false;
		restoreCheckoutAjaxGlobals();
		initializeBillingView($container, false);
		initializeFieldSelectionSync();
	}

	function showInlineNotice(message, type) {
		var $container = container();
		var noticeType = type || 'warning';

		$container.find('.ic-ai-inline-notice').remove();
		$container.prepend('<div class="notice notice-' + escapeHtml(noticeType) + ' inline ic-ai-inline-notice"><p>' + escapeHtml(message) + '</p></div>');
	}

	var resendCountdownTimer = null;

	function resendCountdownLabel(seconds) {
		var template = icAISettings.messages && icAISettings.messages.resendCountdown
			? icAISettings.messages.resendCountdown
			: 'You can request another email in %d seconds.';

		return template.replace('%d', String(seconds));
	}

	function startResendCountdown($button, seconds) {
		var remaining = Math.max(0, parseInt(seconds || 0, 10));
		var $status;

		if (!$button.length) {
			return;
		}
		$status = $button.siblings('.ic-ai-resend-status');
		if (resendCountdownTimer) {
			window.clearInterval(resendCountdownTimer);
			resendCountdownTimer = null;
		}
		if (!remaining) {
			$button.prop('disabled', false);
			return;
		}
		$button.prop('disabled', true);
		$status.text(resendCountdownLabel(remaining));
		resendCountdownTimer = window.setInterval(function () {
			remaining -= 1;
			if (remaining <= 0) {
				window.clearInterval(resendCountdownTimer);
				resendCountdownTimer = null;
				$button.prop('disabled', false);
				$status.text('');
				return;
			}
			$status.text(resendCountdownLabel(remaining));
		}, 1000);
	}

	function resendConfirmation($button) {
		var $status = $button.siblings('.ic-ai-resend-status');
		var busy = icAISettings.messages && icAISettings.messages.resendInFlight
			? icAISettings.messages.resendInFlight
			: 'Sending...';
		var failed = icAISettings.messages && icAISettings.messages.resendFailed
			? icAISettings.messages.resendFailed
			: 'Unable to resend the confirmation email.';

		if ($button.prop('disabled')) {
			return;
		}
		$button.prop('disabled', true);
		$status.text(busy);
		$.post(icAISettings.ajaxUrl, {
			action: 'ic_ai_resend_free_license_confirmation',
			nonce: icAISettings.nonce,
			post_type: icAISettings.postType
		}).done(function (response) {
			var data = response && response.data ? response.data : {};
			$status.text(data.message || failed);
			startResendCountdown($button, data.retry_after || 60);
		}).fail(function (xhr) {
			var data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
			$status.text(data.message || failed);
			startResendCountdown($button, data.retry_after || 60);
		});
	}

	function handleBillingUnavailable(message) {
		restoreOriginalView();
		showInlineNotice(message, 'warning');
		showManualKeyNotice();
	}

	function renderWaitingFallback(message) {
		var $panel = $('.ic-ai-waiting-panel').first();
		var fallback = message || icAISettings.messages.fallback;

		if (!$panel.length) {
			container().prepend('<div class="notice notice-warning inline"><p>' + escapeHtml(fallback) + '</p></div>');
			if (!icAISettings.isRegistered) {
				showManualKeyNotice();
			}
			return;
		}

		$panel.removeClass('notice-info').addClass('notice-warning');
		$panel.find('.ic-ai-waiting-message').text(fallback);
		$panel.find('.ic-ai-waiting-fallback').text(fallback).show();
		if (!icAISettings.isRegistered) {
			showManualKeyNotice();
		}
	}

	function headElement() {
		return document.head || document.getElementsByTagName('head')[0] || null;
	}

	function nodeText(node) {
		if (!node) {
			return '';
		}

		return node.text || node.textContent || '';
	}

	function scriptId(node) {
		return node && node.id ? String(node.id) : '';
	}

	function scriptSrc(node) {
		return node && node.getAttribute ? String(node.getAttribute('src') || '') : '';
	}

	function shouldReuseGlobalScript(node) {
		var id;
		var src;

		if (!node || !node.nodeName || node.nodeName.toLowerCase() !== 'script') {
			return false;
		}

		id = scriptId(node);
		src = scriptSrc(node);

		if ((id === 'jquery-core-js' || src.indexOf('/wp-includes/js/jquery/jquery') !== -1) &&
			window.jQuery && window.jQuery.fn && window.jQuery.fn.jquery) {
			return true;
		}

		if ((id === 'jquery-migrate-js' || src.indexOf('/wp-includes/js/jquery/jquery-migrate') !== -1) &&
			window.jQuery && window.jQuery.fn && window.jQuery.fn.jquery) {
			return true;
		}

		if (id === 'wp-hooks-js' && window.wp && window.wp.hooks && typeof window.wp.hooks.doAction === 'function') {
			return true;
		}

		if (id === 'wp-i18n-js' && window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function') {
			return true;
		}

		if (id === 'wp-url-js' && window.wp && window.wp.url && typeof window.wp.url.addQueryArgs === 'function') {
			return true;
		}

		if (id === 'wp-api-fetch-js' && window.wp && typeof window.wp.apiFetch === 'function') {
			return true;
		}

		return false;
	}

	function headHasAsset(node) {
		var name;
		var attributeName;
		var attributeValue;
		var existingNodes;
		var i;

		if (!node || !node.nodeName || !headElement()) {
			return false;
		}

		name = node.nodeName.toLowerCase();
		if (node.id && document.getElementById(node.id)) {
			return true;
		}

		attributeName = name === 'script' ? 'src' : (name === 'link' ? 'href' : '');
		attributeValue = attributeName ? node.getAttribute(attributeName) : '';
		existingNodes = headElement().getElementsByTagName(name);

		for (i = 0; i < existingNodes.length; i += 1) {
			if (attributeValue && existingNodes[i].getAttribute(attributeName) === attributeValue) {
				return true;
			}
			if (!attributeValue && !node.id && nodeText(existingNodes[i]) === nodeText(node)) {
				return true;
			}
		}

		return false;
	}

	function resolvedAssetPromise() {
		return $.Deferred().resolve().promise();
	}

	function checkoutProxyUrl() {
		return icAISettings && icAISettings.checkoutProxyUrl ? String(icAISettings.checkoutProxyUrl) : '';
	}

	function captureCheckoutAjaxGlobals() {
		if (originalCheckoutAjaxGlobals !== null) {
			return;
		}

		// The whole product_object/ic_cart_ajax_object is snapshotted, so restoring it
		// also drops every remote nonce merged in by ensureCheckoutAjaxGlobals().
		originalCheckoutAjaxGlobals = {
			hasProductObject: typeof window.product_object !== 'undefined',
			productObject: typeof window.product_object !== 'undefined' && window.product_object ? $.extend({}, window.product_object) : null,
			hasCartAjaxObject: typeof window.ic_cart_ajax_object !== 'undefined',
			cartAjaxObject: typeof window.ic_cart_ajax_object !== 'undefined' && window.ic_cart_ajax_object ? $.extend({}, window.ic_cart_ajax_object) : null
		};
	}

	/**
	 * Stores the checkout AJAX nonces minted by the AI service for this fragment.
	 *
	 * Only the service can mint them: every embedded checkout AJAX call is proxied to
	 * the service and verified there, so a nonce created locally could never verify,
	 * and sending none at all is what produced the empty-bodied 403 from the shared
	 * formbuilder nonce guard. Keys are whitelisted to nonce names so an unexpected
	 * payload cannot overwrite ajaxurl or any other product_object member.
	 */
	function setCheckoutAjaxNonces(nonces) {
		var key;

		checkoutAjaxNonces = {};

		if (!nonces || typeof nonces !== 'object') {
			return;
		}

		for (key in nonces) {
			if (Object.prototype.hasOwnProperty.call(nonces, key) &&
				(key === 'nonce' || /_nonce$/.test(key)) &&
				typeof nonces[key] === 'string' && nonces[key]) {
				checkoutAjaxNonces[key] = nonces[key];
			}
		}
	}

	function ensureCheckoutAjaxGlobals() {
		var proxyUrl = checkoutProxyUrl();
		var key;

		if (!proxyUrl) {
			return;
		}

		captureCheckoutAjaxGlobals();

		if (typeof window.product_object === 'undefined' || !window.product_object) {
			window.product_object = {};
		}
		window.product_object.ajaxurl = proxyUrl;

		// Adopt the service's own checkout context, not just its URL. ic-cart.js attaches
		// product_object[<action nonce key>] as ic_nonce to every request aimed at
		// product_object.ajaxurl, and the formbuilder scripts read their keys directly.
		for (key in checkoutAjaxNonces) {
			if (Object.prototype.hasOwnProperty.call(checkoutAjaxNonces, key)) {
				window.product_object[key] = checkoutAjaxNonces[key];
			}
		}

		if (typeof window.ic_cart_ajax_object !== 'undefined' && window.ic_cart_ajax_object) {
			window.ic_cart_ajax_object.ajax_url = proxyUrl;
		}

		// The page-global window.ajaxurl is deliberately NOT repointed at the proxy. Every
		// embedded checkout consumer prefers product_object.ajaxurl (or
		// ic_cart_ajax_object.ajax_url), while unrelated admin callers on this screen --
		// the EPC notice dismissals hide_translate_notice, ic_ajax_hide_message and
		// hide_ic_notice -- read window.ajaxurl and must keep reaching local admin-ajax.
		// The proxy only accepts an allowlist of checkout actions and would reject them.
	}

	function initializeBillingTooltips($scope) {
		var $tips;

		if (!$scope || !$scope.length || typeof $.fn.tooltip !== 'function') {
			return;
		}

		$tips = $scope.find('.ic-ai-billing-wrap span.ic_tip');
		if (!$tips.length) {
			return;
		}

		$tips.each(function () {
			var $tip = $(this);

			if ($tip.data('ui-tooltip-id') || $tip.attr('aria-describedby')) {
				try {
					$tip.tooltip('destroy');
				} catch (error) {
					// Ignore duplicate destroy attempts when the injected view rerenders.
				}
			}
		});

		$tips.tooltip({
			position: {
				my: 'left-48 top+37',
				at: 'right+48 bottom-37',
				collision: 'flip'
			},
			track: true,
			tooltipClass: 'ui-ic-tooltip'
		});
	}

	function restoreCheckoutAjaxGlobals() {
		if (originalCheckoutAjaxGlobals === null) {
			return;
		}

		if (originalCheckoutAjaxGlobals.hasProductObject) {
			window.product_object = $.extend({}, originalCheckoutAjaxGlobals.productObject || {});
		} else {
			// Deliberately {} and NOT undefined. ic-cart.js only checks
			// `typeof product_object !== 'undefined'` ONCE, at document.ready, and the
			// jQuery.ajaxPrefilter it then installs is permanent, global, and
			// dereferences product_object.ajaxurl on EVERY later AJAX call on the page.
			// Because this screen normally has no product_object until
			// ensureCheckoutAjaxGlobals() creates the stub, that registration is the
			// common path, so handing back undefined would throw
			// "TypeError: product_object is undefined" on heartbeat, autosave and every
			// notice dismissal after the Back button. An empty object makes
			// product_object.ajaxurl falsy, so the prefilter returns on its first check.
			// This is behaviourally identical for every consumer guard in the tree: each
			// one either pairs the typeof test with a truthiness check on the member it
			// wants, or is a document.ready block that has already run.
			window.product_object = {};
		}

		if (originalCheckoutAjaxGlobals.hasCartAjaxObject) {
			window.ic_cart_ajax_object = $.extend({}, originalCheckoutAjaxGlobals.cartAjaxObject || {});
		} else {
			// NOT given the {} treatment, on purpose. ensureCheckoutAjaxGlobals() never
			// creates this object when it is absent, so there is nothing to undo, and no
			// register-once-then-dereference-forever consumer exists for it. An empty
			// object would actively regress cart-page.js:26-27, whose
			// `typeof ic_cart_ajax_object !== 'undefined' ? ic_cart_ajax_object.dec_sep : '.'`
			// is an UNPAIRED ternary and would start returning undefined instead of its
			// separator defaults.
			window.ic_cart_ajax_object = undefined;
		}

		checkoutAjaxNonces = {};
		originalCheckoutAjaxGlobals = null;
	}

	function assetKey(node) {
		var name;
		var attributeName;
		var attributeValue;

		if (!node || !node.nodeName) {
			return '';
		}

		name = node.nodeName.toLowerCase();
		if (node.id) {
			return name + '#' + node.id;
		}

		attributeName = name === 'script' ? 'src' : (name === 'link' ? 'href' : '');
		attributeValue = attributeName ? node.getAttribute(attributeName) : '';

		if (attributeValue) {
			return name + ':' + attributeValue;
		}

		return name + ':' + nodeText(node);
	}

	function appendAssetToHead(node) {
		var name;
		var assetNode;
		var src;
		var deferred;

		if (!node || !node.nodeName || !headElement()) {
			return resolvedAssetPromise();
		}

		name = node.nodeName.toLowerCase();
		assetNode = document.createElement(name);
		src = name === 'script' ? node.getAttribute('src') : '';
		deferred = $.Deferred();

		$.each(node.attributes || [], function () {
			assetNode.setAttribute(this.name, this.value);
		});

		if (name === 'script' && src) {
			assetNode.async = false;
			assetNode.onload = function () {
				deferred.resolve();
			};
			assetNode.onerror = function () {
				deferred.resolve();
			};
		}
		if (nodeText(node)) {
			assetNode.text = nodeText(node);
		}

		headElement().appendChild(assetNode);

		if (!(name === 'script' && src)) {
			deferred.resolve();
		}

		return deferred.promise();
	}

	function ensureHeadAsset(node) {
		var key;
		var assetPromise;

		if (!node || !node.nodeName) {
			return resolvedAssetPromise();
		}
		if (shouldReuseGlobalScript(node)) {
			return resolvedAssetPromise();
		}

		key = assetKey(node);
		if (headHasAsset(node)) {
			return key && pendingHeadAssets[key] ? pendingHeadAssets[key] : resolvedAssetPromise();
		}

		assetPromise = appendAssetToHead(node);
		if (key) {
			pendingHeadAssets[key] = assetPromise;
			assetPromise.always(function () {
				delete pendingHeadAssets[key];
			});
		}

		return assetPromise;
	}

	function injectHtml($container, html) {
		var nodes = $.parseHTML(html, document, true) || [];
		var assetQueue = resolvedAssetPromise();

		$container.empty();
		$.each(nodes, function (_, node) {
			var $node;
			var name;

			if (!node) {
				return;
			}

			name = node.nodeName ? node.nodeName.toLowerCase() : '';
			if (name === 'link' || name === 'style' || name === 'script') {
				assetQueue = assetQueue.then(function () {
					return ensureHeadAsset(node);
				});
				return;
			}

			$node = $(node);
			$container.append($node);
		});

		return assetQueue;
	}

	function initializeBillingView($container, shouldPrepareCheckoutAjax) {
		if (!$container || !$container.length) {
			return;
		}

		if (shouldPrepareCheckoutAjax !== false) {
			ensureCheckoutAjaxGlobals();
		}

		if (window.implecode && implecode.ic && typeof implecode.ic.doAction === 'function') {
			implecode.ic.doAction('ic_screen_updated', $container);
		}

		initializeBillingTooltips($container);
	}

	function renderBilling(response) {
		var $container = container();
		var renderId;

		if (!$container.length || !response || !response.html) {
			return false;
		}

		renderId = billingRenderId + 1;
		billingRenderId = renderId;
		$container.attr('data-ic-ai-billing-mode', response.mode || '');
		$container.attr('data-ic-ai-billing-fallback', response.fallback_url || '');
		$container.attr('data-ic-ai-billing-public', response.public_billing ? '1' : '0');
		setBillingFormHidden(true);
		setCheckoutAjaxNonces(response.checkout_nonces);
		ensureCheckoutAjaxGlobals();
		injectHtml($container, backButtonHtml() + response.html).always(function () {
			if (renderId !== billingRenderId) {
				return;
			}
			initializeBillingView($container);
		});
		isBillingView = true;

		return true;
	}

	function renderInlineLoaderBilling(response) {
		var $container = container();
		var $loader = $container.find('.ic-ai-remote-plan-loader').first();

		if (!$container.length || !$loader.length || !response || !response.html) {
			return false;
		}

		$container.attr('data-ic-ai-billing-mode', response.mode || '');
		$container.attr('data-ic-ai-billing-fallback', response.fallback_url || '');
		$container.attr('data-ic-ai-billing-public', response.public_billing ? '1' : '0');
		$loader.removeClass('notice notice-info inline ic-ai-remote-plan-loader');
		setCheckoutAjaxNonces(response.checkout_nonces);
		ensureCheckoutAjaxGlobals();
		injectHtml($loader, response.html).always(function () {
			initializeBillingView($container);
		});

		return true;
	}

	function startCheckout(planSlug, source) {
		var $container = container();

		if (!$container.length) {
			return;
		}

		if (!isBillingView) {
			originalHtml = $container.html();
		}

		setBillingFormHidden(true);
		setLoading(icAISettings.messages.startingCheckout);

		$.post(icAISettings.ajaxUrl, {
			action: 'ic_ai_start_billing_session',
			nonce: icAISettings.nonce,
			post_type: icAISettings.postType,
			target_key: icAISettings.targetKey,
			plan_slug: planSlug || '',
			source: source || 'settings'
		}).done(function (response) {
			var payload = response && response.success && response.data ? response.data : null;

			if (!payload || !renderBilling(payload)) {
				setBillingFormHidden(false);
				if (payload && payload.fallback_url) {
					fallbackRedirect(payload.fallback_url);
					return;
				}

				$container.html('<div class="notice notice-error inline"><p>' + escapeHtml(icAISettings.messages.error) + '</p></div>');
				return;
			}
		}).fail(function (xhr) {
			var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
			var message = payload && payload.message ? payload.message : icAISettings.messages.error;

			if (payload && payload.reveal_manual_key) {
				handleBillingUnavailable(message);
				return;
			}
			setBillingFormHidden(false);
			if (payload && payload.fallback_url) {
				fallbackRedirect(payload.fallback_url);
				return;
			}

			$container.html('<div class="notice notice-error inline"><p>' + escapeHtml(message) + '</p></div>');
		});
	}

	function pollStatus() {
		$.post(icAISettings.ajaxUrl, {
			action: 'ic_ai_pending_status',
			nonce: icAISettings.nonce,
			post_type: icAISettings.postType
			,target_key: icAISettings.targetKey
		}).done(function (response) {
			var payload = response && response.success && response.data ? response.data : null;
			var now = Date.now();

			if (!payload) {
				renderWaitingFallback();
				return;
			}

			if (payload.status === 'ready' || payload.status === 'expired' || payload.status === 'superseded') {
				window.location.href = payload.settings_url || icAISettings.settingsUrl;
				return;
			}

			if (payload.status === 'error') {
				renderWaitingFallback(payload.message);
				return;
			}

			if (payload.message) {
				$('.ic-ai-waiting-message').text(payload.message);
			}

			if (payload.status === 'pending_confirmation' || payload.status === 'confirmed_pending_activation') {
				// Waiting for email proof can outlast the ordinary callback timeout,
				// so the fallback timer is not applied to this state.
				startResendCountdown($('.ic-ai-resend-confirmation'), payload.confirmation ? payload.confirmation.retry_after : 0);
				waitingTimer = window.setTimeout(pollStatus, icAISettings.pollEveryMs);
				return;
			}

			if ((now - waitingStartedAt) >= icAISettings.timeoutMs) {
				renderWaitingFallback();
				return;
			}

			waitingTimer = window.setTimeout(pollStatus, icAISettings.pollEveryMs);
		}).fail(function () {
			if ((Date.now() - waitingStartedAt) >= icAISettings.timeoutMs) {
				renderWaitingFallback();
				return;
			}

			waitingTimer = window.setTimeout(pollStatus, icAISettings.pollEveryMs);
		});
	}

	function beginWaiting() {
		if (waitingTimer) {
			window.clearTimeout(waitingTimer);
		}
		waitingStartedAt = Date.now();
		pollStatus();
	}

	function requestBilling(mode, selection, fallbackUrl, usePublicBilling) {
		var $container = container();

		if (!$container.length) {
			fallbackRedirect(fallbackUrl);
			return;
		}

		if (!isBillingView) {
			originalHtml = $container.html();
		}

		setBillingFormHidden(true);
		setLoading(icAISettings.messages.loading);

		$.post(icAISettings.ajaxUrl, {
			action: 'ic_ai_fetch_billing_fragment',
			nonce: icAISettings.nonce,
			post_type: icAISettings.postType,
			target_key: icAISettings.targetKey,
			mode: mode,
			selection: selection || {},
			public_billing: usePublicBilling ? '1' : ''
		}).done(function (response) {
			var payload = response && response.success && response.data ? response.data : null;

			if (!payload || !renderBilling(payload)) {
				setBillingFormHidden(false);
				fallbackRedirect(fallbackUrl || (payload && payload.fallback_url));
			}
		}).fail(function (xhr) {
			var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
			var message = payload && payload.message ? payload.message : icAISettings.messages.error;

			if (payload && payload.reveal_manual_key) {
				handleBillingUnavailable(message);
				return;
			}
			setBillingFormHidden(false);
			if (fallbackUrl || (payload && payload.fallback_url)) {
				fallbackRedirect(fallbackUrl || payload.fallback_url);
				return;
			}

			$container.html('<div class="notice notice-error inline"><p>' + escapeHtml(message) + '</p></div>');
		});
	}

	function requestInlineLoaderBilling(mode) {
		var $container = container();
		var $loader = $container.find('.ic-ai-remote-plan-loader').first();

		if (!$container.length || !$loader.length) {
			requestBilling(mode, {}, '', true);
			return;
		}

		$loader.html('<p>' + escapeHtml(icAISettings.messages.loading) + '</p>');
		$.post(icAISettings.ajaxUrl, {
			action: 'ic_ai_fetch_billing_fragment',
			nonce: icAISettings.nonce,
			post_type: icAISettings.postType,
			target_key: icAISettings.targetKey,
			mode: mode,
			selection: {},
			public_billing: '1'
		}).done(function (response) {
			var payload = response && response.success && response.data ? response.data : null;

			if (!payload || !renderInlineLoaderBilling(payload)) {
				$loader.html('<p>' + escapeHtml(icAISettings.messages.error) + '</p>');
			}
		}).fail(function (xhr) {
			var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
			var message = payload && payload.message ? payload.message : icAISettings.messages.error;

			if (payload && payload.reveal_manual_key) {
				$loader
					.removeClass('notice-info')
					.addClass('notice-warning')
					.html('<p>' + escapeHtml(message) + '</p>');
				showManualKeyNotice();
				return;
			}

			if (payload && payload.fallback_url) {
				fallbackRedirect(payload.fallback_url);
				return;
			}

			$loader
				.removeClass('notice-info')
				.addClass('notice-error')
				.html('<p>' + escapeHtml(message) + '</p>');
		});
	}

	function cancelSubscription($link) {
		var $cell = $link.closest('.ic-ai-subscription-cell');

		$link.prop('disabled', true).css('pointer-events', 'none');

		$.post(icAISettings.ajaxUrl, {
			action: 'ic_ai_cancel_subscription',
			nonce: icAISettings.nonce,
			post_type: icAISettings.postType
			,target_key: icAISettings.targetKey
		}).done(function (response) {
			var payload = response && response.success && response.data ? response.data : null;

			if (!payload) {
				$cell.append('<div class="notice notice-error inline"><p>' + escapeHtml(icAISettings.messages.cancelSubscriptionError) + '</p></div>');
				return;
			}

				$cell.html(payload.subscription_html || '');
				$('.ic-ai-license-expiration-cell').first().html(payload.license_html || '');
				if (Object.prototype.hasOwnProperty.call(payload, 'confirm_unregister_site')) {
					icAISettings.messages.confirmUnregisterSite = payload.confirm_unregister_site || '';
				}
			}).fail(function (xhr) {
				var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
				var message = payload && payload.message ? payload.message : icAISettings.messages.cancelSubscriptionError;

			$cell.append('<div class="notice notice-error inline"><p>' + escapeHtml(message) + '</p></div>');
		});
	}

	function retryConnection($button) {
		var $notice = $button.closest('.ic-ai-remote-error-notice');
		var originalLabel = $button.text();

		$notice.find('.ic-ai-retry-error').remove();
		$button.prop('disabled', true).text(icAISettings.messages.retrying);

		$.post(icAISettings.ajaxUrl, {
			action: 'ic_ai_retry_connection',
			nonce: icAISettings.nonce,
			post_type: $button.data('post-type') || icAISettings.postType
			,target_key: icAISettings.targetKey
		}).done(function () {
			window.location.reload();
		}).fail(function (xhr) {
			var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
			var message = payload && payload.message ? payload.message : icAISettings.messages.error;

			$button.prop('disabled', false).text(originalLabel);
			$notice.append('<p class="ic-ai-retry-error description">' + escapeHtml(message) + '</p>');
		});
	}

	function initializeFieldSelectionSync() {
		var $enhanceInputs = $('input[name="ic_ai_settings[fields][]"]');
		var $contextRow = $('input[name="ic_ai_settings[context_fields][]"]').first().closest('tr');
		var $contextCell;
		var enhanceTemplates = {};
		var enhanceOrder = [];

		if (!$enhanceInputs.length || !$contextRow.length) {
			return;
		}

		$contextCell = $contextRow.find('td').first();
		if (!$contextCell.length) {
			return;
		}

		$enhanceInputs.each(function () {
			var $input = $(this);
			var value = $input.val() ? String($input.val()) : '';

			if (!value) {
				return;
			}

			enhanceTemplates[value] = $input.closest('label').clone();
			enhanceOrder.push(value);
		});

		function contextInput(value) {
			return $contextCell.find('input[name="ic_ai_settings[context_fields][]"][value="' + value.replace(/"/g, '\\"') + '"]').first();
		}

		function createContextLabel(value) {
			var $label = enhanceTemplates[value].clone();
			var $input = $label.find('input').first();

			$input.attr('name', 'ic_ai_settings[context_fields][]');
			$input.attr('id', 'ic-ai-context-field-' + value);
			$input.prop('checked', false).removeAttr('checked');

			return $label;
		}

		function syncContextOptions() {
			var selectedContext = {};

			$contextCell.find('input[name="ic_ai_settings[context_fields][]"]').each(function () {
				var $input = $(this);
				var value = $input.val() ? String($input.val()) : '';

				if (!value || !Object.prototype.hasOwnProperty.call(enhanceTemplates, value)) {
					return;
				}
				if ($input.is(':checked')) {
					selectedContext[value] = true;
				}

				$input.closest('label').remove();
			});

			$.each(enhanceOrder, function (_, value) {
				var $enhanceInput = $enhanceInputs.filter('[value="' + value.replace(/"/g, '\\"') + '"]').first();
				var $input;

				if (!$enhanceInput.length || $enhanceInput.is(':checked') || contextInput(value).length) {
					return;
				}

				$contextCell.append(createContextLabel(value));
				$input = contextInput(value);
				if (selectedContext[value]) {
					$input.prop('checked', true);
				}
			});
		}

		syncContextOptions();
		$(document).off('change.icAIFieldSelectionSync', 'input[name="ic_ai_settings[fields][]"]').on('change.icAIFieldSelectionSync', 'input[name="ic_ai_settings[fields][]"]', syncContextOptions);
	}

	function toggleCurrentPlanDetails($button) {
		var detailsId = $button.attr('aria-controls');
		var $details;
		var expanded;

		if (!detailsId) {
			return;
		}

		$details = $('#' + detailsId);
		if (!$details.length) {
			return;
		}

		expanded = $button.attr('aria-expanded') === 'true';
		$button.attr('aria-expanded', expanded ? 'false' : 'true');
		$details.prop('hidden', expanded);
		$button.closest('.ic-ai-current-plan-wrap').toggleClass('is-open', !expanded);
	}

	$(function () {
		initializeFieldSelectionSync();

		$(document)
			.off('click.icAICurrentPlanToggle', '.ic-ai-current-plan-toggle')
			.on('click.icAICurrentPlanToggle', '.ic-ai-current-plan-toggle', function (event) {
				event.preventDefault();
				toggleCurrentPlanDetails($(this));
			});

		$(document).on('click', '.ic-ai-retry', function (event) {
			event.preventDefault();
			retryConnection($(this));
		});

		$(document).on('click', '.ic-ai-resend-confirmation', function (event) {
			event.preventDefault();
			resendConfirmation($(this));
		});

		// Restore the server-rendered remaining wait on first paint.
		startResendCountdown($('.ic-ai-resend-confirmation').first(), $('.ic-ai-resend-confirmation').first().data('retry-after'));

		$(document).on('click', '.ic-ai-plan-purchase', function () {
			var $button = $(this);

			startCheckout($button.data('plan-slug') || '', $button.data('source') || 'settings');
		});

		$(document).on('click', '.ic-ai-manual-key-toggle', function () {
			showManualKeyNotice();
		});

		$(document).on('click', '.ic-ai-billing-trigger', function (event) {
			var $button = $(this);

			event.preventDefault();
			requestBilling($button.data('billing-mode') || 'upgrade', {}, $button.attr('href'), !!$button.data('billing-public'));
		});

		$(document).on('submit', '.ic-ai-billing-select-form', function (event) {
			var $form = $(this);
			var $wrap = $form.closest('.ic-ai-billing-wrap');
			var usePublicBilling = $wrap.data('ic-ai-billing-public') === 1 || $wrap.data('ic-ai-billing-public') === '1' ||
				container().attr('data-ic-ai-billing-public') === '1';

			event.preventDefault();
			requestBilling(
				$wrap.data('ic-ai-billing-mode') || $form.data('ic-ai-mode') || 'upgrade',
				selectionFromForm($form),
				$form.attr('action'),
				usePublicBilling
			);
		});

		$(document).on('click', '.ic-ai-cancel-subscription', function (event) {
			var $link = $(this);
			var expires = $link.data('expires') || '';
			var message = (icAISettings.messages.confirmCancelSubscription || '').replace('%s', expires);

			event.preventDefault();

			if (!window.confirm(message)) {
				return;
			}

			cancelSubscription($link);
		});

		$(document).on('click', '.ic-ai-unregister-trigger', function (event) {
			if (!window.confirm(icAISettings.messages.confirmUnregisterSite || '')) {
				event.preventDefault();
			}
		});

		$(document).on('click', '.ic-ai-billing-back', function () {
			if (!originalHtml) {
				return;
			}

			restoreOriginalView();
		});

		if (!icAISettings.waiting && $('.ic-ai-waiting-panel').length) {
			beginWaiting();
		}

		if (icAISettings.waiting) {
			beginWaiting();
		} else if (
			icAISettings.autoBillingMode === 'purchase' &&
			icAISettings.autoBillingPublic &&
			!icAISettings.isRegistered &&
			container().find('.ic-ai-remote-plan-loader').length
		) {
			requestInlineLoaderBilling(icAISettings.autoBillingMode);
		} else if (icAISettings.autoBillingMode) {
			requestBilling(icAISettings.autoBillingMode, {}, '', !!icAISettings.autoBillingPublic);
		} else if (icAISettings.autoPlan && !icAISettings.isRegistered) {
			startCheckout(icAISettings.autoPlan, icAISettings.autoSource || 'settings');
		}
	});
}(jQuery));
