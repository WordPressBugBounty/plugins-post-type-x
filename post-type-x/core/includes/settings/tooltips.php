<?php
/**
 * Admin tooltip helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'implecode_enable_wp_tooltips' ) ) {
	add_action( 'admin_enqueue_scripts', 'implecode_enable_wp_tooltips' );

	/**
	 * Enqueues WordPress pointer assets on catalog admin screens.
	 *
	 * @return void
	 */
	function implecode_enable_wp_tooltips() {
		if ( ! is_ic_admin_page() ) {
			return;
		}
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
		// Hook the pointer.
		add_action( 'admin_print_footer_scripts', 'implecode_show_wp_tooltips' );
	}

}

if ( ! function_exists( 'implecode_show_wp_tooltips' ) ) {

	/**
	 * Prints the tooltip bootstrap script in the admin footer.
	 *
	 * @return void
	 */
	function implecode_show_wp_tooltips() {
		$tooltips = implecode_prepare_wp_tooltips_for_js( apply_filters( 'implecode_wp_tooltips', implecode_wp_tooltip_get() ) );
		if ( empty( $tooltips ) ) {
			return;
		}
		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {
					if (jQuery(".ic_cat-activation-wizard").length > 0) {
						return false;
					}

					function ic_normalize_tooltips(rawTooltips) {
						if (Array.isArray(rawTooltips)) {
							return rawTooltips;
						}
						if (typeof rawTooltips !== 'string' || rawTooltips === '') {
							return [];
						}
						try {
							return JSON.parse(rawTooltips);
						} catch (error) {
							try {
								return JSON.parse(jQuery('<textarea />').html(rawTooltips).text());
							} catch (decodedError) {
								return [];
							}
						}
					}

					setTimeout(function () {
						var tooltips = ic_normalize_tooltips(<?php echo wp_json_encode( $tooltips ); ?>);
						ic_show_next_pointer(tooltips, 1);
					}, 5000);

					function ic_show_next_pointer(tooltips, do_not_scroll) {
						jQuery.each(tooltips, function (index, value) {
							var selector = value.selector.replace(/&quot;/g, '"');
							var last_selector = 0;
							if (selector === '') {
								if (jQuery(".ic-settings-search .button-secondary").length !== 0) {
									selector = 'implecode_settings .ic-settings-search .button-secondary';
								} else {
									selector = 'help';
								}
								last_selector = 1;
							}
							if (jQuery("#" + selector).length > 0) {
								var position = 'top';
								if (jQuery("#al_product_desc").length > 0) {
									position = 'bottom';
								}
								if (jQuery(".nav-tab-wrapper").find("#" + selector).length > 0) {
									position = {'edge': 'left', 'align': 'middle'};
								}
								if (selector.includes("implecode_settings")) {
									position = {'edge': 'right', 'align': 'left'};
								}
								var pointer_selector = jQuery('#' + selector);
								var original_selector = pointer_selector;
								pointer_selector = pointer_selector.first();

								var selector_label = pointer_selector.closest("tr");
								if (selector_label.length !== 0) {
									pointer_selector = selector_label;
								}
								var open_pointer = pointer_selector.first();
								open_pointer.addClass("ic-pointer-opened");
								if (do_not_scroll === 0) {
									ic_pointer_out_of_screen(open_pointer);
								}
								open_pointer.pointer({
									pointerClass: "ic_pointer_" + index + " wp-pointer",
									content: "<h3>" + value.title + "</h3>" + "<p>" + value.text + "</p>",
									/* position: { 'edge': 'top', 'align': 'middle' },*/
									position: position,
									close: function () {
										open_pointer.removeClass("ic-pointer-opened");
									},
									buttons: function (event, t) {
										if (last_selector === 1) {
											return ic_pointer_default_buttons(t);
										} else {
											return ic_pointer_buttons(t, selector);
										}
									},
								}).pointer('open');
								var change_action = 'change';
								original_selector.on(change_action, function () {
									var active_pointer = jQuery(".ic_pointer_" + index);
									if (active_pointer.is(":visible")) {
										active_pointer.find(".close").click();
										ic_hide_pointer(selector);
									}
								});
								return false;
							}
						});
					}

					function ic_hide_pointer(selector) {
						var data = {
							'action': 'implecode_wp_tooltip_hide',
							'selector': selector,
								'nonce': '<?php echo esc_js( wp_create_nonce( 'ic-ajax-nonce' ) ); ?>'
						};
						jQuery.post(ajaxurl, data, function (response) {
							if (response !== undefined) {
								var tooltips = ic_normalize_tooltips(response);
								ic_show_next_pointer(tooltips, 0);
							}
						});
					}

					function ic_dismiss_all_pointers() {
						var data = {
							'action': 'implecode_wp_tooltip_dismiss_all',
								'nonce': '<?php echo esc_js( wp_create_nonce( 'ic-ajax-nonce' ) ); ?>'
						};
						jQuery.post(ajaxurl, data);
					}

					function ic_pointer_out_of_screen(element) {
						var top_of_element = element.offset().top;
						var bottom_of_element = top_of_element + element.outerHeight();
						var bottom_of_screen = jQuery(window).scrollTop() + jQuery(window).innerHeight();
						var top_of_screen = jQuery(window).scrollTop();

						if ((bottom_of_screen > top_of_element) && (top_of_screen < bottom_of_element)) {
							return false;
						} else {
							jQuery([document.documentElement, document.body]).animate({
								scrollTop: top_of_element - 220
							}, 2000);
							return true;
						}
					}

					function ic_pointer_buttons(t, selector) {
						var button1 = jQuery('<a class="close ic-pointer-dismiss" href="#"></a>').text("<?php echo esc_js( __( 'Hide Forever', 'post-type-x' ) ); ?>");
						var button2 = jQuery('<a class="button-primary" href="#"></a>').text("<?php echo esc_js( __( 'Next', 'post-type-x' ) ); ?>");
						var wrapper = jQuery('<div class=\"wc-pointer-buttons\" />');
						button1.on('click.pointer', function (e) {
							e.preventDefault();
							t.element.pointer('close');
							ic_dismiss_all_pointers();
						});
						button2.on('click.pointer', function (e) {
							e.preventDefault();
							t.element.pointer('close');
							ic_hide_pointer(selector);
						});
						wrapper.append(button2);
						wrapper.append(button1);
						return wrapper;
					}

					function ic_pointer_default_buttons(t) {
						var button = jQuery('<a class="close" href="#"></a>').text(wp.i18n.__('Dismiss'));

						return button.on('click.pointer', function (e) {
							e.preventDefault();
							t.element.pointer('close');
						});
					}
				}
			);
		</script>
		<?php
	}

}
if ( ! function_exists( 'implecode_wp_tooltip_hide' ) ) {
	add_action( 'wp_ajax_implecode_wp_tooltip_hide', 'implecode_ajax_wp_tooltip_hide' );

	/**
	 * Removes a single tooltip from the queue through AJAX.
	 *
	 * @return void
	 */
	function implecode_ajax_wp_tooltip_hide() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic-ajax-nonce' ) && current_user_can( 'manage_product_settings' ) ) {
			$selector = isset( $_POST['selector'] ) ? sanitize_text_field( wp_unslash( $_POST['selector'] ) ) : '';
			implecode_wp_tooltip_hide( $selector );
			$tooltips = implecode_wp_tooltip_get();
			if ( is_array( $tooltips ) ) {
				$tooltips[] = implecode_wp_tooltip_default();
			} else {
				$tooltips = array();
			}
			wp_send_json( implecode_prepare_wp_tooltips_for_js( $tooltips ) );
		}
		wp_die();
	}

}

if ( ! function_exists( 'implecode_wp_tooltip_dismiss_all' ) ) {
	add_action( 'wp_ajax_implecode_wp_tooltip_dismiss_all', 'implecode_wp_tooltip_dismiss_all' );

	/**
	 * Dismisses all tooltips through AJAX.
	 *
	 * @return void
	 */
	function implecode_wp_tooltip_dismiss_all() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic-ajax-nonce' ) && current_user_can( 'manage_product_settings' ) ) {
			update_option( 'implecode_wp_tooltips', 'disabled', false );
		}
		wp_die();
	}

}
if ( ! function_exists( 'implecode_wp_tooltip_hide' ) ) {

	/**
	 * Removes a tooltip identified by selector.
	 *
	 * @param string $selector Tooltip selector.
	 *
	 * @return void
	 */
	function implecode_wp_tooltip_hide( $selector ) {
		if ( empty( $selector ) ) {
			return;
		}
		$tooltips = implecode_wp_tooltip_get();
		if ( ! is_array( $tooltips ) ) {
			return;
		}
		foreach ( $tooltips as $key => $tooltip ) {
			if ( $tooltip['selector'] === $selector ) {
				unset( $tooltips[ $key ] );
			}
		}
		update_option( 'implecode_wp_tooltips', $tooltips, false );
		implecode_wp_tooltip_hidden_update( $selector );
	}

}

if ( ! function_exists( 'implecode_prepare_wp_tooltips_for_js' ) ) {

	/**
	 * Normalizes tooltip records for safe inline JS output.
	 *
	 * @param mixed $tooltips Raw tooltip records.
	 *
	 * @return array
	 */
	function implecode_prepare_wp_tooltips_for_js( $tooltips ) {
		if ( empty( $tooltips ) || ! is_array( $tooltips ) ) {
			return array();
		}

		$prepared_tooltips = array();

		foreach ( $tooltips as $tooltip ) {
			if ( ! is_array( $tooltip ) ) {
				continue;
			}

			$prepared_tooltips[] = array(
				'title'    => isset( $tooltip['title'] ) ? esc_html( (string) $tooltip['title'] ) : '',
				'text'     => isset( $tooltip['text'] ) ? esc_html( (string) $tooltip['text'] ) : '',
				'selector' => isset( $tooltip['selector'] ) ? (string) $tooltip['selector'] : '',
			);
		}

		return $prepared_tooltips;
	}
}

if ( ! function_exists( 'implecode_wp_tooltip_get' ) ) {

	/**
	 * Gets the queued tooltips.
	 *
	 * @return array|string
	 */
	function implecode_wp_tooltip_get() {
		$tooltips = get_option( 'implecode_wp_tooltips', array() );
		if ( ! is_array( $tooltips ) && 'disabled' !== $tooltips ) {
			$tooltips = array();
		}

		return $tooltips;
	}

}

if ( ! function_exists( 'implecode_wp_tooltip_hidden_get' ) ) {

	/**
	 * Gets selectors hidden by the user.
	 *
	 * @return array
	 */
	function implecode_wp_tooltip_hidden_get() {
		$tooltips = get_option( 'implecode_wp_hidden_tooltips', array() );

		return $tooltips;
	}

}

if ( ! function_exists( 'implecode_wp_tooltip_hidden_update' ) ) {

	/**
	 * Stores a hidden tooltip selector.
	 *
	 * @param string $selector Tooltip selector.
	 *
	 * @return void
	 */
	function implecode_wp_tooltip_hidden_update( $selector ) {
		$hidden_tooltips   = implecode_wp_tooltip_hidden_get();
		$hidden_tooltips[] = $selector;
		update_option( 'implecode_wp_hidden_tooltips', $hidden_tooltips, false );
	}

}

if ( ! function_exists( 'implecode_is_wp_tooltip_hidden' ) ) {

	/**
	 * Checks whether a tooltip selector is hidden.
	 *
	 * @param string $selector Tooltip selector.
	 *
	 * @return bool
	 */
	function implecode_is_wp_tooltip_hidden( $selector ) {
		$tooltips = implecode_wp_tooltip_get();
		if ( 'disabled' === $tooltips ) {
			return true;
		}
		$hidden_tooltips = implecode_wp_tooltip_hidden_get();
		if ( in_array( $selector, $hidden_tooltips, true ) ) {
			return true;
		}

		return false;
	}

}

if ( ! function_exists( 'implecode_wp_tooltip_exists' ) ) {

	/**
	 * Checks whether a tooltip already exists.
	 *
	 * @param string $selector Tooltip selector.
	 *
	 * @return bool
	 */
	function implecode_wp_tooltip_exists( $selector ) {
		$tooltips = implecode_wp_tooltip_get();
		if ( ! is_array( $tooltips ) ) {
			return false;
		}
		foreach ( $tooltips as $tooltip ) {
			if ( $tooltip['selector'] === $selector ) {
				return true;
			}
		}

		return false;
	}

}

if ( ! function_exists( 'implecode_wp_tooltip_add' ) ) {

	/**
	 * Adds a tooltip to the queue.
	 *
	 * @param string $title    Tooltip title.
	 * @param string $text     Tooltip text.
	 * @param string $selector Tooltip selector.
	 * @param bool   $on_top   Whether the tooltip should be prepended.
	 *
	 * @return bool
	 */
	function implecode_wp_tooltip_add( $title, $text, $selector, $on_top = false ) {
		if ( empty( $title ) || empty( $text ) || empty( $selector ) ) {
			return false;
		}
		if ( implecode_wp_tooltip_exists( $selector ) ) {
			return false;
		}

		if ( implecode_is_wp_tooltip_hidden( $selector ) ) {
			return false;
		}

		$tooltips = implecode_wp_tooltip_get();
		if ( ! is_array( $tooltips ) ) {
			$tooltips = array();
		}
		$tooltip = array(
			'title'    => $title,
			'text'     => $text,
			'selector' => $selector,
		);
		if ( $on_top ) {
			$tooltips = array_merge( array( $tooltip ), $tooltips );
		} else {
			$tooltips[] = $tooltip;
		}
		update_option( 'implecode_wp_tooltips', $tooltips, false );

		return true;
	}

}

if ( ! function_exists( 'implecode_wp_tooltip_default' ) ) {

	/**
	 * Returns the default completion tooltip.
	 *
	 * @return array
	 */
	function implecode_wp_tooltip_default() {
		$tooltip = array(
			'title'    => __( 'Screen Tutorial Complete', 'post-type-x' ),
			/* translators: 1: opening support forum link, 2: closing support forum link. */
			'text'     => __( 'Congratulations! You finished the tutorial on this screen. Check all the options here and go to another screen to continue.', 'post-type-x' ) . '<br><br>' . sprintf( __( 'If you have any questions or issues, you can reach the developers on the %1$ssupport forum%2$s.', 'post-type-x' ), '<a href="https://wordpress.org/support/plugin/ecommerce-product-catalog/">', '</a>' ),
			'selector' => '',
		);

		return $tooltip;
	}

}
