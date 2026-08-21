<?php
/**
 * Activation wizard helpers.
 *
 * @package Ecommerce_Product_Catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'IC_Activation_Wizard', false ) ) {
	if ( class_exists( 'ic_activation_wizard', false ) ) {
		class_alias( 'ic_activation_wizard', 'IC_Activation_Wizard' );
	} else {

	/**
	 * Handles activation wizard notices and recommended extensions.
	 */
	class IC_Activation_Wizard {

		/**
		 * Buffered notice HTML.
		 *
		 * @var string
		 */
		public static $box_content = '';

		/**
		 * Outputs the activation wizard notice box.
		 *
		 * @param string $name Notice key.
		 * @param string $attr Additional HTML attributes.
		 *
		 * @return void
		 */
		public function wizard_box( $name = 'notice-ic-catalog-activation', $attr = '' ) {
			$content = self::$box_content;
			if ( ! empty( $content ) ) {
				if ( is_ic_welcome_page() ) {
					$class = '';
				} else {
					$class = 'notice notice-updated';
				}
				$class .= 'ic_cat-activation-wizard';
				if ( ! empty( $name ) ) {
					$class .= ' is-dismissible ic-notice';
					$attr  .= ' data-ic_dismissible="' . esc_attr( $name ) . '"';
				}
				?>
				<div class="<?php echo esc_attr( $class ); ?>"
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
				echo $attr;
				?>
				>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
					echo $content;
					do_action( 'ic_cat_activation_wizard_bottom' );
					?>
				</div>
				<?php
				self::$box_content = '';
			}
		}

		/**
		 * Adds a heading to the buffered notice output.
		 *
		 * @param string $content Heading content.
		 *
		 * @return void
		 */
		public function box_header( $content ) {
			if ( ! empty( $content ) ) {
				$h_open             = '<h3>';
				$h_close            = '</h3>';
				self::$box_content .= $h_open . $content . $h_close;
			}
		}

		/**
		 * Adds paragraph-style content to the buffered notice output.
		 *
		 * @param string $content Paragraph content.
		 * @param bool   $light   Whether to use paragraph tags instead of heading tags.
		 *
		 * @return void
		 */
		public function box_paragraph( $content, $light = false ) {
			if ( ! empty( $content ) ) {
				if ( $light ) {
					$p_open  = '<p>';
					$p_close = '</p>';
				} else {
					$p_open  = '<h4>';
					$p_close = '</h4>';
				}
				self::$box_content .= $p_open . $content . $p_close;
			}
		}

		/**
		 * Adds a list to the buffered notice output.
		 *
		 * @param array  $sentences List items.
		 * @param string $style     List alignment style.
		 *
		 * @return void
		 */
		public function box_list( $sentences, $style = 'left' ) {
			if ( ! empty( $sentences ) && is_array( $sentences ) ) {
				if ( 'left' === $style ) {
					$style = 'text-align: left;list-style: circle inside;margin:0 auto;';
				} else {
					$style = 'text-align:left; list-style:circle inside; margin: 10px auto; display: table;';
				}
				$return = '<ul style="' . esc_attr( $style ) . '">';
				foreach ( $sentences as $sentence ) {
					$return .= '<li>' . esc_html( $sentence ) . '</li>';
				}
				$return            .= '</ul>';
				self::$box_content .= $return;
			}
		}

		/**
		 * Adds choice links or a choice form to the buffered notice output.
		 *
		 * @param array       $questions Question labels keyed by target URL.
		 * @param bool|string $next_step Next step key when rendering a form.
		 *
		 * @return void
		 */
		public function box_choice( $questions, $next_step = false ) {
			if ( ! empty( $questions ) && is_array( $questions ) ) {
				$return = '<h4 class="ic_cat-activation-question">';
				if ( ! empty( $next_step ) ) {
					$submit_url = key( $questions );
					$return    .= '<form method="get" action="' . esc_url( $submit_url ) . '">';
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only preservation of current query vars.
					foreach ( wp_unslash( $_GET ) as $key => $value ) {
						$input_key   = sanitize_key( $key );
						$input_value = is_scalar( $value ) ? sanitize_text_field( $value ) : '';
						if ( 'ic_catalog_activation_choice' !== $input_key ) {
							$return .= '<input type="hidden" name="' . esc_attr( $input_key ) . '" value="' . esc_attr( $input_value ) . '">';
						}
					}
					$return .= '<input type="hidden" name="ic_catalog_activation_choice" value="' . esc_attr( $next_step ) . '">';

					$return .= '<input type="hidden" name="_wpnonce" value="' . wp_create_nonce( 'ic_catalog_activation_choice' ) . '">';

					$choice_one = reset( $questions );
					$return    .= $this->sanitize_choice_markup( $choice_one );

					$return .= '<input type="submit" value="' . esc_attr( __( 'Continue', 'post-type-x' ) ) . '" class="ic_cat-activation-choice">';
					$return .= '</form>';
				} else {
					foreach ( $questions as $url => $question ) {
						$return .= '<a class="ic_cat-activation-choice" href="' . esc_url( $url ) . '">' . esc_html( $question ) . '</a>';
					}
				}
				$return            .= '</h4>';
				self::$box_content .= $return;
			}
		}

		/**
		 * Sanitizes inline wizard form markup for single-choice steps.
		 *
		 * @param string $markup Choice markup.
		 *
		 * @return string
		 */
		public function sanitize_choice_markup( $markup ) {
			return wp_kses( $markup, $this->choice_allowed_html() );
		}

		/**
		 * Returns the allowed HTML for inline wizard form fragments.
		 *
		 * @return array
		 */
		public function choice_allowed_html() {
			$allowed_html = wp_kses_allowed_html( 'post' );

			$allowed_html['table']  = array(
				'class' => true,
				'id'    => true,
				'style' => true,
			);
			$allowed_html['tbody']  = array(
				'class' => true,
				'id'    => true,
				'style' => true,
			);
			$allowed_html['thead']  = array(
				'class' => true,
				'id'    => true,
				'style' => true,
			);
			$allowed_html['tfoot']  = array(
				'class' => true,
				'id'    => true,
				'style' => true,
			);
			$allowed_html['tr']     = array(
				'class' => true,
				'id'    => true,
				'style' => true,
			);
			$allowed_html['td']     = array(
				'class'   => true,
				'id'      => true,
				'style'   => true,
				'colspan' => true,
				'rowspan' => true,
			);
			$allowed_html['th']     = array(
				'class'   => true,
				'id'      => true,
				'style'   => true,
				'colspan' => true,
				'rowspan' => true,
				'scope'   => true,
			);
			$allowed_html['div']    = array(
				'class' => true,
				'id'    => true,
				'style' => true,
			);
			$allowed_html['span']   = array(
				'class' => true,
				'id'    => true,
				'style' => true,
				'title' => true,
			);
			$allowed_html['label']  = array(
				'class' => true,
				'for'   => true,
				'id'    => true,
				'style' => true,
			);
			$allowed_html['select'] = array(
				'class'              => true,
				'id'                 => true,
				'multiple'           => true,
				'name'               => true,
				'required'           => true,
				'style'              => true,
				'data-placeholder'   => true,
				'data-available_qty' => true,
			);
			$allowed_html['option'] = array(
				'class'    => true,
				'id'       => true,
				'label'    => true,
				'selected' => true,
				'value'    => true,
			);
			$allowed_html['input']  = array(
				'checked'     => true,
				'class'       => true,
				'id'          => true,
				'max'         => true,
				'maxlength'   => true,
				'min'         => true,
				'multiple'    => true,
				'name'        => true,
				'placeholder' => true,
				'readonly'    => true,
				'required'    => true,
				'size'        => true,
				'step'        => true,
				'style'       => true,
				'type'        => true,
				'value'       => true,
			);

			return $allowed_html;
		}

		/**
		 * Buffers or renders the recommended extensions notice.
		 *
		 * @param bool $container Whether to render the notice container immediately.
		 *
		 * @return void
		 */
		public function recommended_extensions_box( $container = true ) {
			$recommended = $this->get_recommended_extensions();
			if ( ! empty( $recommended ) ) {
				$this->box_header( __( 'You have some recommended extensions based on your initial setup answers!', 'post-type-x' ) );
				$this->box_paragraph( __( 'See them below:', 'post-type-x' ) );
				$available_extensions = $this->available_recommended_extensions();
				$styling              = '';
				$styling_name         = '';
				$sentences            = array();
				$free                 = '(' . __( 'free', 'post-type-x' ) . ')';
				foreach ( $recommended as $extension_slug ) {
					if ( ! empty( $available_extensions[ $extension_slug ] ) ) {
						$extension_class = sanitize_html_class( $extension_slug );
						if ( ! empty( $styling ) ) {
							$styling      .= ',';
							$styling_name .= ',';
						}
						$styling      .= '#implecode_settings .extension.' . $extension_class;
						$styling_name .= '#implecode_settings .extension.' . $extension_class . ' .extension-name h3 span:before';
						$sentences[]   = $available_extensions[ $extension_slug ]['name'] . ' ' . $free;
					}
				}
				$this->box_list( $sentences, 'center' );

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only read of the current admin page.
				$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
				if ( empty( $page ) || 'extensions.php' !== $page ) {
					$param = '';
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only read to preserve the current step in a generated URL.
					$activation_choice = isset( $_GET['ic_catalog_activation_choice'] ) ? sanitize_text_field( wp_unslash( $_GET['ic_catalog_activation_choice'] ) ) : '';
					if ( ! empty( $activation_choice ) ) {
						$param = '&ic_catalog_activation_choice=' . rawurlencode( $activation_choice );
					}
					$questions = array(
						admin_url( 'edit.php?post_type=al_product&page=extensions.php' . $param ) => __( 'Extensions Install Page', 'post-type-x' ),
					);
					$this->box_choice( $questions );
				} else {
					/* translators: %s: Dashicon markup highlighting recommended extensions. */
					$this->box_paragraph( sprintf( __( 'Recommended extensions on the list below are highlighted with %s and red border.', 'post-type-x' ), '<span class="dashicons dashicons-thumbs-up"></span>' ) );
					?>
					<style>
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS selector list built from hardcoded strings plus sanitize_html_class() extension slugs. ?>
						<?php echo $styling; ?>
						{
							border-color: red
						;
						}
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS selector list built from hardcoded strings plus sanitize_html_class() extension slugs. ?>
						<?php echo $styling_name; ?>
						{
							content: "\f529"
						;
							font-family: dashicons
						;
							vertical-align: bottom
						;
						}
					</style>
					<?php
				}
				if ( $container ) {
					$this->wizard_box( 'notice-ic-catalog-recommended' );
				}
			}
		}

		/**
		 * Stores a recommended extension slug.
		 *
		 * @param string $extension_slug Extension slug.
		 *
		 * @return array
		 */
		public function add_recommended_extension( $extension_slug ) {
			$recommended   = $this->get_recommended_extensions();
			$recommended[] = $extension_slug;
			update_option( 'ic_cat_recommended_extensions', $recommended );

			return $recommended;
		}

		/**
		 * Gets recommended extensions that are not already active.
		 *
		 * @return array
		 */
		public function get_recommended_extensions() {
			$recommended = array_filter( array_unique( get_option( 'ic_cat_recommended_extensions', array() ) ) );
			if ( ! empty( $recommended ) && function_exists( 'is_plugin_active' ) ) {
				$available_extensions = $this->available_recommended_extensions();
				foreach ( $recommended as $key => $slug ) {
					if ( 'catalog-booster-for-woocommerce' === $slug ) {
						$plugin = $slug . '/woocommerce-catalog-booster.php';
					} else {
						$plugin = $slug . '/' . $slug . '.php';
					}
					if ( is_plugin_active( $plugin ) || is_plugin_active_for_network( $plugin ) || empty( $available_extensions[ $slug ] ) ) {
						unset( $recommended[ $key ] );
					}
				}
			}

			return $recommended;
		}

		/**
		 * Checks whether any recommended extensions remain.
		 *
		 * @return bool
		 */
		public function any_recommended_extensions() {
			$recommended = $this->get_recommended_extensions();
			if ( ! empty( $recommended ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Gets the available recommended extensions list.
		 *
		 * @return array
		 */
		public function available_recommended_extensions() {
			$available_extensions = array();
			if ( function_exists( 'implecode_x_free_extensions' ) ) {
				$available_extensions = implecode_x_free_extensions();
			}
			if ( function_exists( 'implecode_free_extensions' ) ) {
				$available_extensions = array_merge( $available_extensions, implecode_free_extensions() );
			}

			return $available_extensions;
		}

		/**
		 * Gets notice dismissal status across user, transient, and global scopes.
		 *
		 * @param string|null $notice Notice key.
		 * @param string|null $type   Status scope.
		 *
		 * @return array|int
		 */
		public function get_notice_status( $notice = null, $type = null ) {
			if ( ! empty( $notice ) ) {
				$type = null;
			}
			$status = array();
			if ( get_current_user_id() ) {
				if ( empty( $type ) || 'user' === $type ) {
					$status = get_user_meta( get_current_user_id(), '_ic_hidden_notices', true );
					if ( empty( $status ) ) {
						$status = array();
					}
				}
				if ( ! empty( $notice ) && ( empty( $type ) || 'temp' === $type ) ) {
					$transient_name = 'ic_hidden_notices_' . $notice;
					if ( get_current_user_id() ) {
						$transient_name .= '_' . get_current_user_id();
					}
					$transient_status = get_transient( $transient_name );
					if ( ! empty( $transient_status ) ) {
						$status[ $notice ] = $transient_status;
					}
				}
			}
			if ( empty( $type ) || 'global' === $type ) {
				$global_status = get_option( 'ic_hidden_notices', array() );
				if ( empty( $global_status ) ) {
					$global_status = array();
				}
				if ( ! empty( $global_status ) ) {
					$status = array_merge( $status, array_filter( $global_status ) );
				}
			}
			if ( empty( $notice ) ) {
				return $status;
			} elseif ( empty( $status[ $notice ] ) ) {
				return 0;
			} else {
				return 1;
			}
		}

		/**
		 * Hides a notice through AJAX.
		 *
		 * @return void
		 */
		public function ajax_hide_ic_notice() {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic-ajax-nonce' ) ) {
				$element = isset( $_POST['element'] ) ? sanitize_text_field( wp_unslash( $_POST['element'] ) ) : '';
				$type    = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'global';
				if ( ! empty( $element ) ) {
					$status = $this->get_notice_status( null, $type );
					if ( is_array( $status ) && empty( $status[ $element ] ) ) {
						$status[ $element ] = 1;
					}
					// Only the last branch writes the site-wide option, so only it requires the
					// settings capability. The self-scoped 'user' and 'temp' branches stay
					// available to any logged-in user; a non-privileged user simply gets no
					// site-wide write.
					// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
					$can_hide_site_wide = current_user_can( 'manage_product_settings' );
					if ( 'user' === $type && get_current_user_id() ) {
						update_user_meta( get_current_user_id(), '_ic_hidden_notices', $status );
					} elseif ( 'temp' === $type ) {
						$transient_name = 'ic_hidden_notices_' . $element;
						if ( get_current_user_id() ) {
							$transient_name .= '_' . get_current_user_id();
						}
						set_transient( $transient_name, 1, MONTH_IN_SECONDS );
					} elseif ( $can_hide_site_wide ) {
						update_option( 'ic_hidden_notices', $status );
					}
				}
			}
			wp_die();
		}

		/**
		 * Checks whether the WooCommerce notice should be shown.
		 *
		 * @return bool
		 */
		public function show_woocommerce_notice() {
			if ( class_exists( 'WooCommerce' ) ) {
				$count_posts = wp_count_posts( 'product' );
				if ( ! empty( $count_posts->publish ) ) {
					return apply_filters( 'ic_cat_show_woocommerce_notice', true );
				}
			}

			return false;
		}

		/**
		 * Converts a response array into wizard question choices.
		 *
		 * @param array $response Response configuration.
		 *
		 * @return array
		 */
		public function response_to_question( $response ) {

			$questions = array();

			if ( ! empty( $response['one'] ) && ! empty( $response['next_one'] ) ) {
				$choice_one     = $response['one'];
				$choice_one_url = add_query_arg( 'ic_catalog_activation_choice', $response['next_one'] );

				$questions[ $choice_one_url ] = $choice_one;
			}

			if ( ! empty( $response['two'] ) && ! empty( $response['next_two'] ) ) {
				$choice_two     = $response['two'];
				$choice_two_url = add_query_arg( 'ic_catalog_activation_choice', $response['next_two'] );

				$questions[ $choice_two_url ] = $choice_two;
			}
			if ( ! empty( $response['three'] ) && ! empty( $response['three'] ) ) {
				$choice_three     = $response['three'];
				$choice_three_url = add_query_arg( 'ic_catalog_activation_choice', $response['next_three'] );

				$questions[ $choice_three_url ] = $choice_three;
			}

			return $questions;
		}
	}

	}
}

if ( ! class_exists( 'ic_activation_wizard', false ) ) {
	class_alias( 'IC_Activation_Wizard', 'ic_activation_wizard' );
}
