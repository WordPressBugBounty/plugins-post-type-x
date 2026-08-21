<?php
/**
 * Activation and upgrade helpers.
 *
 * @package ecommerce-product-catalog/functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages functions necessary on plugin activation.
 *
 * @version        1.1.3
 * @package        ecommerce-product-catalog/functions
 * @author        impleCode
 */
/**
 * Runs plugin activation setup.
 *
 * @return void
 */
function epc_activation_function() {
	if ( is_ic_activation_hook() && current_user_can( 'activate_plugins' ) ) {
		$first_activation = get_option( 'ic_epc_first_activation' );
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested, Generic.Formatting.MultipleStatementAlignment.IncorrectWarning -- Legacy local timestamp storage is preserved for backward compatibility.
		$current_time     = current_time( 'timestamp' );
		if ( empty( $first_activation ) || $current_time - $first_activation < MONTH_IN_SECONDS ) {
			if ( ! function_exists( 'start_ic_woocat' ) ) {
				set_transient( '_ic_welcome_screen_activation_redirect', true, 30 );
			}
			update_option( 'IC_EPC_activation_message', 1, false );
			delete_option( 'implecode_wp_hidden_tooltips' );
			delete_option( 'implecode_wp_tooltips' );
			delete_option( 'ic_cat_wizard_woo_choice' );
			delete_option( 'ic_hidden_notices' );
			delete_option( 'ic_hidden_boxes' );
			delete_option( 'ic_epc_tracking_notice' );
			delete_option( 'ic_cat_recommended_extensions' );
			delete_option( 'ic_block_woo_template_file' );
			delete_option( 'ic_allow_woo_template_file' );
			IC_Catalog_Notices::review_notice_hide();
			save_default_multiple_settings();
			ic_save_default_labels();
			create_sample_product();
			create_products_page();
			ic_set_filter_bar_default_widgets();
			if ( empty( $first_activation ) ) {
				update_option( 'ic_epc_first_activation', $current_time, false );
				// phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase -- Legacy hook name kept for backward compatibility.
				do_action( 'ic_EPC_first_activation' );
			}
		}
		add_product_caps();
		permalink_options_update();
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase -- Legacy hook name kept for backward compatibility.
		do_action( 'ic_EPC_activation' );
		delete_option( 'IC_EPC_install' );
	}
}

/**
 * Saves default values for multiple settings for compatibility with multilanguage plugins
 */
function save_default_multiple_settings() {
	$check = get_option( 'archive_multiple_settings' );
	if ( empty( $check ) ) {
		$archive_multiple_settings = get_option( 'archive_multiple_settings', get_default_multiple_settings() );
		if ( ! is_array( $archive_multiple_settings ) ) {
			$archive_multiple_settings = array();
		}
		$archive_multiple_settings['catalog_plural']                    = isset( $archive_multiple_settings['catalog_plural'] ) ? $archive_multiple_settings['catalog_plural'] : DEF_CATALOG_PLURAL;
		$archive_multiple_settings['catalog_singular']                  = isset( $archive_multiple_settings['catalog_singular'] ) ? $archive_multiple_settings['catalog_singular'] : DEF_CATALOG_SINGULAR;
		$archive_multiple_settings['shortcode_mode']['show_everywhere'] = isset( $archive_multiple_settings['shortcode_mode']['show_everywhere'] ) ? $archive_multiple_settings['shortcode_mode']['show_everywhere'] : 1;
		update_option( 'archive_multiple_settings', $archive_multiple_settings );
	}
}

/**
 * Saves default catalog labels.
 *
 * @return void
 */
function ic_save_default_labels() {
	$single_names = get_option( 'single_names' );
	if ( empty( $single_names ) ) {
		$default_single_names = default_single_names();
		update_option( 'single_names', $default_single_names );
	}
	$archive_names = get_option( 'archive_names' );
	if ( empty( $archive_names ) ) {
		$default_archive_names = default_archive_names();
		update_option( 'archive_names', $default_archive_names );
	}
}

/**
 * Creates the main products page if needed.
 *
 * @param string $status Post status for the created page.
 * @return int|void
 */
function create_products_page( $status = 'publish' ) {
	if ( current_user_can( 'publish_pages' ) ) {
		$content      = ic_catalog_shortcode();
		$product_page = array(
			'post_title'     => DEF_CATALOG_PLURAL,
			'post_type'      => 'page',
			'post_content'   => $content,
			'post_status'    => $status,
			'comment_status' => 'closed',
		);

		$plugin_version = IC_EPC_VERSION;
		$first_version  = get_option( 'first_activation_version', '1.0' );

		if ( '1.0' === $first_version ) {
			add_option( 'first_activation_version', $plugin_version );
			add_option( 'ecommerce_product_catalog_ver', $plugin_version );
		}
		$listing_id = get_product_listing_id();
		if ( empty( $listing_id ) || 'noid' === $listing_id ) {
			$listing_id = wp_insert_post( $product_page );
			if ( ! is_wp_error( $listing_id ) ) {
				update_option( 'product_archive_page_id', $listing_id );
				update_option( 'product_archive', $listing_id );
			}
		}

		return $listing_id;
	}
}

/**
 * Creates the sample product used for onboarding.
 *
 * @return int|void
 */
function create_sample_product() {
	$sample_id = sample_product_id();

	/*
	 * `activate_plugins` replaces the former `current_user_can( 'administrator' )` role-name check.
	 * It must not be a plugin capability here: on first activation this runs before
	 * add_product_caps(), so `publish_products` is not registered yet.
	 */
	// phpcs:ignore WordPress.WP.Capabilities.Unknown, WordPress.Security.NonceVerification.Recommended -- publish_products is registered by the plugin; the GET trigger is nonce-verified by IC_Catalog_Theme_Integration::create_sample_product_with_redirect() before this runs.
	if ( ( current_user_can( 'publish_products' ) || current_user_can( 'activate_plugins' ) ) && ( ( ( ! is_advanced_mode_forced() || is_ic_shortcode_integration() ) && empty( $sample_id ) ) || isset( $_GET['create_sample_product_page'] ) ) ) {
		$short_desc                         = '<p>' . __( 'Welcome on a product test page. This is a short description. It should show up on the left side of the product image and below the product name.', 'post-type-x' ) . '</p>';
		$short_desc                        .= '<p>' . __( 'You can change the product page template in catalog settings.', 'post-type-x' ) . '</p>';
		$short_desc                        .= '<p><strong>' . __( 'Please read this page carefully to fully understand all product page elements.', 'post-type-x' ) . '</strong></p>';
		$product_sample                     = array(
			'post_title'     => __( 'Sample Product Page', 'post-type-x' ),
			'post_type'      => 'al_product',
			'post_content'   => '[sample_long_desc]',
			'post_excerpt'   => $short_desc,
			'post_status'    => 'publish',
			'comment_status' => 'closed',
		);
		$product_id                         = wp_insert_post( $product_sample );
		$product_field['_price']            = 30;
		$product_field['_sku']              = 'INT102';
		$product_field['_attribute-label1'] = __( 'Color', 'post-type-x' );
		$product_field['_attribute-label2'] = __( 'Size', 'post-type-x' );
		$product_field['_attribute-label3'] = __( 'Weight', 'post-type-x' );
		$product_field['_attribute1']       = __( 'White', 'post-type-x' );
		$product_field['_attribute2']       = __( 'Big', 'post-type-x' );
		$product_field['_attribute3']       = 130;
		$product_field['_attribute-unit1']  = '';
		$product_field['_attribute-unit2']  = '';
		$product_field['_attribute-unit3']  = __( 'lbs', 'post-type-x' );
		$product_field['_shipping-label1']  = 'UPS';
		$product_field['_shipping1']        = 15;
		foreach ( $product_field as $key => $value ) {
			add_post_meta( $product_id, $key, $value, true );
		}
		update_option( 'sample_product_id', $product_id );

		return $product_id;
	}
}

add_shortcode( 'sample_long_desc', 'ic_sample_long_desc' );

/**
 * Returns the sample product long description.
 *
 * @return string
 */
function ic_sample_long_desc() {
	$long_desc  = '<p>' . __( 'This section is a product long description. It should appear under the attributes table or in the description tab. Before that, you should see the price, SKU and shipping options (all can be disabled). The attributes also can be disabled.', 'post-type-x' ) . '</p>';
	$long_desc .= '<h2>' . __( 'Product Page Layout', 'post-type-x' ) . '</h2>';
	$long_desc .= '<p>' . __( 'You can modify the product page and product listing layout by clicking on the admin options links located under the image.', 'post-type-x' ) . '</p>';
	$long_desc .= '<h2>' . __( 'Advanced Theme Integration Mode', 'post-type-x' ) . '</h2>';
	if ( ! is_ic_shortcode_integration() ) {
		/* translators: %s: Current integration mode name. */
		$long_desc .= '<p><strong>' . sprintf( __( 'You are currently using %s mode.', 'post-type-x' ), get_integration_type() ) . '</strong></p>';
		/* translators: 1: Catalog plugin name, 2: Percentage string, 3: Percentage string, 4: Theme integration guide URL. */
		$long_desc .= '<p>' . sprintf( __( 'With Advanced Mode, you will be able to use %1$s in %2$s. The product listing page, category pages, product search and category widget will be enabled in advanced mode. You can enable the Advanced Mode %3$s free. To see how please see <a target="_blank" href="%4$s">Theme Integration Guide</a>', 'post-type-x' ), IC_CATALOG_PLUGIN_NAME, '100%', '100%', 'https://implecode.com/wordpress/product-catalog/theme-integration-guide/#cam=sample-product-page&key=integration-mode-test' ) . '</p>';
		$long_desc .= '<p>' . __( 'The Advanced Mode works out of the box on all default WordPress themes and all themes with the integration done correctly.', 'post-type-x' ) . '</p>';
		$long_desc .= '<h2>' . __( 'Simple Theme Integration Mode', 'post-type-x' ) . '</h2>';
		/* translators: 1: Catalog plugin name, 2: Shortcode name. */
		$long_desc .= '<p>' . sprintf( __( 'The simple mode allows using %1$s most features. You can build the product listing pages and category pages by using a %2$s shortcode. Simple mode uses your theme page layout so it can show unwanted elements on a product page. If it does please switch to Advanced Mode and see if it works out of the box.', 'post-type-x' ), IC_CATALOG_PLUGIN_NAME, '[show_products]' ) . '</p>';
		$long_desc .= '<h2>' . __( 'How to switch to Advanced Mode?', 'post-type-x' ) . '</h2>';
		/* translators: 1: Automatic advanced mode test URL, 2: Catalog plugin name, 3: Theme integration guide URL. */
		$long_desc .= '<p>' . sprintf( __( 'Click <a href="%1$s">here</a> to test the Automatic Advanced Mode. If the test goes well, you can keep it enabled and enjoy full %2$s functionality. If the page layout during the test will not be satisfying, please see <a target="_blank" href="%3$s">Theme Integration Guide</a>.', 'post-type-x' ), '?test_advanced=1', IC_CATALOG_PLUGIN_NAME, 'https://implecode.com/wordpress/product-catalog/theme-integration-guide/#cam=sample-product-page&key=integration-mode-test' ) . '</p>';
		$long_desc .= '<p>' . __( 'The theme integration guide will show you a step by step process. If you finish it successfully, the integration will be done. It is recommended to use theme integration guide even if the page looks good in simple mode or advanced mode because it reassures 100% theme integrity.', 'post-type-x' ) . '</p>';
	} else {
		/* translators: %s: Current catalog shortcode. */
		$long_desc .= '<p>' . sprintf( __( 'Currently, %s is being used on the main product listing.', 'post-type-x' ), '[' . ic_catalog_shortcode_name() . ']' ) . '</p>';
		$long_desc .= '<p>' . __( 'If the catalog pages are not displayed correctly within your theme layout you can test a different integration method.', 'post-type-x' ) . '</p>';
		if ( ! is_advanced_mode_forced( false ) ) {
			/* translators: 1: Opening link tag, 2: Closing link tag. */
			$long_desc .= '<p>' . sprintf( __( 'Click %1$shere%2$s to proceed.', 'post-type-x' ), '<a href="?test_advanced=1">', '</a>' ) . '</p>';
		} else {
			/* translators: %s: Current catalog shortcode. */
			$long_desc .= '<p>' . sprintf( __( 'To proceed with such test, remove %s from your main product listing page and see how the catalog pages look like without it.', 'post-type-x' ), '[' . ic_catalog_shortcode_name() . ']' ) . '</p>';
		}
	}

	return $long_desc;
}

/**
 * Returns the saved sample product ID.
 *
 * @return int|false
 */
function sample_product_id() {
	$product_id = get_option( 'sample_product_id' );
	if ( ! empty( $product_id ) && ic_product_exists( $product_id ) ) {
		return $product_id;
	} elseif ( ! empty( $product_id ) ) {
		delete_option( 'sample_product_id' );
	}

	return false;
}

/**
 * Returns the sample product URL.
 *
 * @return string
 */
function sample_product_url() {
	$product_id = sample_product_id();
	if ( $product_id ) {
		$sample_product_url = get_permalink( $product_id );
		$sample_product_url = esc_url( add_query_arg( 'test_advanced', 1, $sample_product_url ) );
	}
	if ( empty( $sample_product_url ) || ( ! empty( $product_id ) && 'publish' !== get_post_status( $product_id ) ) ) {
		$sample_product_url = esc_url( wp_nonce_url( add_query_arg( 'create_sample_product_page', 'true' ), 'ic_create_sample_product_page' ) );
	}

	return $sample_product_url;
}

/**
 * Returns the sample product CTA markup.
 *
 * @param bool|null   $p           Whether to wrap the button in a paragraph.
 * @param string|null $text        Button label.
 * @param string      $button_type Button CSS class suffix.
 * @return string|void
 */
function sample_product_button( $p = null, $text = null, $button_type = 'button-primary' ) {
	$sample_url = sample_product_url();
	if ( ! empty( $sample_url ) ) {
		$text = isset( $text ) ? $text : __( 'Start Automatic Theme Integration', 'post-type-x' );
		if ( ! isset( $p ) ) {
			return '<a href="' . $sample_url . '" class="ic-advanced-mode-wizard-button ' . $button_type . '">' . $text . '</a>';
		} else {
			return '<p><a href="' . $sample_url . '" class="ic-advanced-mode-wizard-button ' . $button_type . '">' . $text . '</a></p>';
		}
	}
}

add_action( 'admin_init', 'ecommerce_product_catalog_upgrade' );

/**
 * Runs plugin upgrade routines.
 *
 * @return void
 */
function ecommerce_product_catalog_upgrade() {
	if ( is_admin() ) {
		ic_epc_schedule_catalog_transient_cleanup();

		$plugin_version          = IC_EPC_VERSION;
		$database_plugin_version = get_option( 'ecommerce_product_catalog_ver', $plugin_version );
		if ( $database_plugin_version !== $plugin_version ) {
			update_option( 'ecommerce_product_catalog_ver', $plugin_version, false );
			$first_version = (string) get_option( 'first_activation_version', $plugin_version );
			if ( version_compare( $first_version, '1.9.0' ) < 0 && version_compare( $database_plugin_version, '2.2.4' ) < 0 ) {
				$hide_info = 0;
				IC_Catalog_Theme_Integration::enable_advanced_mode( $hide_info );
			}
			if ( version_compare( $first_version, '2.0.0' ) < 0 && version_compare( $database_plugin_version, '2.2.4' ) < 0 ) {
				$archive_multiple_settings                         = get_multiple_settings();
				$archive_multiple_settings['product_listing_cats'] = 'off';
				$archive_multiple_settings['cat_template']         = 'link';
				update_option( 'archive_multiple_settings', $archive_multiple_settings );
			}
			if ( version_compare( $first_version, '2.0.1' ) < 0 && version_compare( $database_plugin_version, '2.2.4' ) < 0 ) {
				add_product_caps();
			}
			if ( version_compare( $first_version, '2.0.4' ) < 0 && version_compare( $database_plugin_version, '2.2.4' ) < 0 ) {
				delete_transient( 'implecode_extensions_data' );
			}
			if ( version_compare( $first_version, '2.2.5' ) < 0 && version_compare( $database_plugin_version, '2.2.5' ) < 0 ) {
				$archive_names = get_option( 'archive_names' );
				if ( ! is_array( $archive_names ) ) {
					$archive_names = array();
				}
				$archive_names['all_main_categories'] = '';
				$archive_names['all_products']        = '';
				$archive_names['all_subcategories']   = '';
				update_option( 'archive_names', $archive_names );
			}
			if ( version_compare( $first_version, '2.3.6' ) < 0 && version_compare( $database_plugin_version, '2.3.6' ) < 0 ) {
				$archive_multiple_settings                    = get_multiple_settings();
				$archive_multiple_settings['default_sidebar'] = 1;
				update_option( 'archive_multiple_settings', $archive_multiple_settings );
			}
			if ( version_compare( $first_version, '2.4.0' ) < 0 && version_compare( $database_plugin_version, '2.4.0' ) < 0 ) {
				$archive_multiple_settings            = get_multiple_settings();
				$archive_multiple_settings['related'] = 'categories';
				update_option( 'archive_multiple_settings', $archive_multiple_settings );
				update_option( 'old_sort_bar', 1 );
			}
			if ( version_compare( $first_version, '2.4.15' ) < 0 && version_compare( $database_plugin_version, '2.4.15' ) < 0 ) {
				save_default_multiple_settings();
			}
			if ( version_compare( $first_version, '2.4.16' ) < 0 && version_compare( $database_plugin_version, '2.4.16' ) < 0 ) {
				$single_names         = get_single_names();
				$single_names['free'] = '';
				update_option( 'single_names', $single_names );
				ic_save_global( 'single_names', $single_names );
			}
			if ( version_compare( $first_version, '2.4.21' ) < 0 && version_compare( $database_plugin_version, '2.4.21' ) < 0 ) {
				if ( false !== get_transient( 'implecode_hide_plugin_review_info' ) ) {
					set_site_transient( 'implecode_hide_plugin_review_info', 1 );
				}
				if ( false !== get_transient( 'implecode_hide_plugin_translation_info' ) ) {
					set_site_transient( 'implecode_hide_plugin_translation_info', 1 );
				}
			}
			if ( version_compare( $first_version, '2.4.25' ) < 0 && version_compare( $database_plugin_version, '2.4.25' ) < 0 ) {
				if ( function_exists( 'ic_reassign_all_products_attributes' ) ) {
					ic_reassign_all_products_attributes();
				}
			}
			if ( version_compare( $first_version, '2.5.0' ) < 0 && version_compare( $database_plugin_version, '2.5.0' ) < 0 ) {
				$single_options                           = get_product_page_settings();
				$single_options['template']               = 'plain';
				$single_options['enable_product_gallery'] = 1;
				update_option( 'multi_single_options', $single_options );
			}
			if ( version_compare( $first_version, '2.6.0' ) < 0 && version_compare( $database_plugin_version, '2.6.0' ) < 0 ) {
				ic_add_catalog_manager_role();
			}
			if ( version_compare( $first_version, '2.7.18' ) < 0 && version_compare( $database_plugin_version, '2.7.18' ) < 0 ) {
				permalink_options_update();
			}
			if ( version_compare( $first_version, '3.0.1' ) < 0 && version_compare( $database_plugin_version, '3.0.1' ) < 0 ) {
				permalink_options_update();
			}
			if ( version_compare( $first_version, '3.0.45' ) < 0 && version_compare( $database_plugin_version, '3.0.45' ) < 0 ) {
				wp_schedule_single_event( time(), 'ic_scheduled_hidden_data_processing' );
			}
			if ( version_compare( $first_version, '3.3.27' ) < 0 && version_compare( $database_plugin_version, '3.3.27' ) < 0 ) {
				$csv_temp   = wp_upload_dir( null, false );
				$csv_folder = $csv_temp['basedir'];
				if ( file_exists( $csv_folder . '/simple-products.csv' ) ) {
					wp_delete_file( $csv_folder . '/simple-products.csv' );
				}
				if ( file_exists( $csv_folder . '/products_product.csv' ) ) {
					wp_delete_file( $csv_folder . '/products_product.csv' );
				}
				$product_catalogs = get_post_types( array( 'capability_type' => 'product' ) );
				foreach ( $product_catalogs as $product_catalog ) {
					if ( file_exists( $csv_folder . '/products_' . $product_catalog . '.csv' ) ) {
						wp_delete_file( $csv_folder . '/products_' . $product_catalog . '.csv' );
					}
				}
			}
		} elseif ( ! get_option( 'ecommerce_product_catalog_ver' ) ) {
			update_option( 'ecommerce_product_catalog_ver', $plugin_version, false );
		}
	}
}

add_action( 'ic_epc_cleanup_catalog_transient_options', 'ic_epc_cleanup_catalog_transient_options' );

/**
 * Schedules obsolete catalog transient cleanup outside the admin request.
 *
 * @param int $delay Delay in seconds before the cleanup worker should run.
 * @return void
 */
function ic_epc_schedule_catalog_transient_cleanup( $delay = MINUTE_IN_SECONDS ) {
	if ( get_option( 'ic_epc_catalog_transient_cleanup_20260618' ) ) {
		return;
	}

	if ( wp_next_scheduled( 'ic_epc_cleanup_catalog_transient_options' ) ) {
		return;
	}

	wp_schedule_single_event( time() + absint( $delay ), 'ic_epc_cleanup_catalog_transient_options' );
}

/**
 * Removes high-cardinality catalog transients replaced by request-local caches.
 *
 * @return void
 */
function ic_epc_cleanup_catalog_transient_options() {
	$cleanup_option = 'ic_epc_catalog_transient_cleanup_20260618';
	if ( get_option( $cleanup_option ) ) {
		return;
	}

	if ( ! ic_epc_catalog_transient_cleanup_acquire_lock() ) {
		return;
	}

	$option_rows = ic_epc_catalog_transient_cleanup_get_batch();
	if ( empty( $option_rows ) ) {
		update_option( $cleanup_option, 1, false );
		ic_epc_catalog_transient_cleanup_release_lock();

		return;
	}

	ic_epc_catalog_transient_cleanup_delete_batch( $option_rows );

	if ( ic_epc_catalog_transient_cleanup_has_rows() ) {
		ic_epc_schedule_catalog_transient_cleanup(
			(int) apply_filters( 'ic_epc_catalog_transient_cleanup_delay', MINUTE_IN_SECONDS )
		);
	} else {
		update_option( $cleanup_option, 1, false );
	}

	ic_epc_catalog_transient_cleanup_release_lock();
}

/**
 * Returns obsolete catalog transient option prefixes.
 *
 * @return array
 */
function ic_epc_catalog_transient_cleanup_prefixes() {
	return array(
		'_transient_ic_current_products_query_',
		'_transient_timeout_ic_current_products_query_',
		'_transient_ic_catalog_category_filter_',
		'_transient_timeout_ic_catalog_category_filter_',
	);
}

/**
 * Acquires the catalog transient cleanup lock.
 *
 * @return bool
 */
function ic_epc_catalog_transient_cleanup_acquire_lock() {
	$lock_option = 'ic_epc_catalog_transient_cleanup_lock';
	$now         = time();
	$locked_at   = absint( get_option( $lock_option, 0 ) );

	if ( ! empty( $locked_at ) && ( $now - $locked_at ) < ( 10 * MINUTE_IN_SECONDS ) ) {
		return false;
	}

	if ( ! empty( $locked_at ) ) {
		update_option( $lock_option, $now, false );

		return true;
	}

	return add_option( $lock_option, $now, '', false );
}

/**
 * Releases the catalog transient cleanup lock.
 *
 * @return void
 */
function ic_epc_catalog_transient_cleanup_release_lock() {
	delete_option( 'ic_epc_catalog_transient_cleanup_lock' );
}

/**
 * Gets one cleanup batch.
 *
 * @return array
 */
function ic_epc_catalog_transient_cleanup_get_batch() {
	global $wpdb;

	$limit = absint( apply_filters( 'ic_epc_catalog_transient_cleanup_batch_size', 500 ) );
	if ( $limit < 1 ) {
		$limit = 1;
	}

	$where  = array();
	$values = array();
	foreach ( ic_epc_catalog_transient_cleanup_prefixes() as $prefix ) {
		$where[]  = 'option_name LIKE %s';
		$values[] = $wpdb->esc_like( $prefix ) . '%';
	}
	$values[] = $limit;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded cleanup uses fixed LIKE fragments and prepares every value below.
	return $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- LIKE clauses are generated from fixed prefixes and still use prepared values.
			"SELECT option_id, option_name FROM {$wpdb->options} WHERE " . implode( ' OR ', $where ) . ' ORDER BY option_id ASC LIMIT %d',
			$values
		)
	);
}

/**
 * Deletes one cleanup batch.
 *
 * @param array $option_rows Option rows with option_id and option_name.
 * @return void
 */
function ic_epc_catalog_transient_cleanup_delete_batch( $option_rows ) {
	global $wpdb;

	$option_ids = array_map( 'absint', wp_list_pluck( $option_rows, 'option_id' ) );
	$option_ids = array_filter( $option_ids );
	if ( empty( $option_ids ) ) {
		return;
	}

	$placeholders = implode( ', ', array_fill( 0, count( $option_ids ), '%d' ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded one-time cleanup for selected obsolete transient rows.
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN placeholders are generated for absint option IDs.
			"DELETE FROM {$wpdb->options} WHERE option_id IN ($placeholders)",
			$option_ids
		)
	);

	foreach ( $option_rows as $option_row ) {
		if ( ! empty( $option_row->option_name ) ) {
			wp_cache_delete( $option_row->option_name, 'options' );
		}
	}
}

/**
 * Checks whether cleanup rows remain.
 *
 * @return bool
 */
function ic_epc_catalog_transient_cleanup_has_rows() {
	global $wpdb;

	$where  = array();
	$values = array();
	foreach ( ic_epc_catalog_transient_cleanup_prefixes() as $prefix ) {
		$where[]  = 'option_name LIKE %s';
		$values[] = $wpdb->esc_like( $prefix ) . '%';
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded existence check uses fixed LIKE fragments and prepares every value below.
	$option_id = $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- LIKE clauses are generated from fixed prefixes and still use prepared values.
			"SELECT option_id FROM {$wpdb->options} WHERE " . implode( ' OR ', $where ) . ' LIMIT 1',
			$values
		)
	);

	return ! empty( $option_id );
}

add_action( 'ic_update_product_data', 'ic_update_product_data' );
add_action( 'ic_update_product_data_frozen', 'ic_update_product_data_watchdog' );
add_action( 'ic_product_data_reassignment_done', 'ic_update_product_data_clear_scope' );
add_action( 'ic_product_data_reassignment_done', 'ic_update_product_data_flush_object_cache', 999 );

/**
 * Flushes the WordPress object cache after product data reassignment terminates.
 *
 * @return void
 */
function ic_update_product_data_flush_object_cache() {
	$completion_cooldown = get_transient( 'ic_update_product_data_done' );
	wp_cache_flush();
	if ( false !== $completion_cooldown ) {
		set_transient( 'ic_update_product_data_done', $completion_cooldown, MINUTE_IN_SECONDS * 10 );
	}
}

/**
 * Starts or resumes the product data reassignment worker.
 *
 * @param string $scope Reassignment status scope.
 * @return string
 */
function ic_update_product_data_start( $scope = 'publish' ) {
	$option_name  = 'ic_update_product_data_done';
	$hook_name    = 'ic_update_product_data';
	$scope_option = ic_update_product_data_scope_option_name();
	$done         = get_option( $option_name, false );

	ic_update_product_data_clear_stale_lock();
	if ( false === $done ) {
		if ( get_transient( $option_name ) ) {
			return __( 'Just Finished! Wait 10 minutes before restarting.', 'post-type-x' );
		}
		update_option( $option_name, - 1 );
		ic_update_product_data_save_scope( $scope );
		ic_update_product_data_schedule();

		return '';
	}

	if ( false === get_option( $scope_option, false ) ) {
		ic_update_product_data_save_scope( $scope );
	}
	if ( ic_update_product_data_has_lock() ) {
		return __( 'Product data reassignment is already running.', 'post-type-x' );
	}
	if ( wp_next_scheduled( $hook_name ) ) {
		return __( 'Product data reassignment is already scheduled.', 'post-type-x' );
	}
	ic_update_product_data_schedule();

	return '';
}

/**
 * Schedules or runs the product meta update process.
 *
 * @return string|void
 */
function ic_update_product_data() {
	if ( ! wp_doing_cron() ) {
		return ic_update_product_data_start( ic_update_product_data_requested_scope() );
	}

	$start_time       = microtime( true );
	$option_name      = 'ic_update_product_data_done';
	$hook_name        = 'ic_update_product_data';
	$frozen_hook_name = $hook_name . '_frozen';
	$done             = get_option( $option_name, 0 );
	$scope_option     = ic_update_product_data_scope_option_name();

	if ( empty( $done ) ) {
		if ( ! get_transient( $option_name ) ) {
			return __( 'Just Finished! Wait 10 minutes before restarting.', 'post-type-x' );
		}

		return;
	}
	if ( ! wp_next_scheduled( $frozen_hook_name ) ) {
		wp_schedule_event( time() + ( 3 * HOUR_IN_SECONDS ), 'hourly', $frozen_hook_name );
	}
	if ( ! function_exists( 'get_all_catalog_products' ) ) {
		wp_clear_scheduled_hook( $hook_name );
		wp_clear_scheduled_hook( $frozen_hook_name );
		ic_update_product_data_release_lock();

		return '';
	}
	if ( $done < 0 ) {
		$done = 0;
	}
	if ( false === get_option( $scope_option, false ) ) {
		ic_update_product_data_save_scope( 'publish' );
	}
	$lock_token = ic_update_product_data_acquire_lock( $done );
	if ( empty( $lock_token ) ) {
		if ( ! wp_next_scheduled( $hook_name ) ) {
			ic_update_product_data_schedule( MINUTE_IN_SECONDS );
		}

		return;
	}
	if ( empty( $done ) ) {
		do_action( 'ic_product_data_reassignment_start' );
	}
	set_transient( 'ic_doing_update_product_data_loop', $done, MINUTE_IN_SECONDS * 15 );
	ic_update_product_data_refresh_lock( $lock_token, $done );
	wp_defer_term_counting( true );
	$done = ic_update_product_data_loop( $done, $start_time, $option_name, $lock_token );
	wp_defer_term_counting( false );
	delete_transient( 'ic_doing_update_product_data_loop' );
	if ( 'stopped' === $done ) {
		ic_update_product_data_release_lock( $lock_token );

		return;
	}
	if ( 'done' !== $done ) {
		if ( ic_update_product_data_is_lock_owner( $lock_token ) ) {
			update_option( $option_name, $done );
			ic_update_product_data_refresh_lock( $lock_token, $done );
			ic_update_product_data_schedule();
		}
		ic_update_product_data_release_lock( $lock_token );
	} elseif ( ic_update_product_data_is_lock_owner( $lock_token ) ) {
		delete_option( $option_name );
		wp_clear_scheduled_hook( $hook_name );
		wp_clear_scheduled_hook( $frozen_hook_name );
		set_transient( $option_name, 1, MINUTE_IN_SECONDS * 10 );
		ic_update_product_data_release_lock( $lock_token );
		do_action( 'ic_product_data_reassignment_done' );
	} else {
		ic_update_product_data_release_lock( $lock_token );
	}
}

/**
 * Reschedules the reassignment worker when a batch appears stalled.
 *
 * @return void
 */
function ic_update_product_data_watchdog() {
	$hook_name = 'ic_update_product_data';

	if ( ! ic_update_product_data_is_active() ) {
		wp_clear_scheduled_hook( $hook_name . '_frozen' );

		return;
	}
	ic_update_product_data_clear_stale_lock();
	if ( ! ic_update_product_data_has_lock() && ! wp_next_scheduled( $hook_name ) ) {
		ic_update_product_data_schedule();
	}
}

/**
 * Schedules the reassignment worker if it is not already queued.
 *
 * @param int $delay Delay in seconds.
 * @return void
 */
function ic_update_product_data_schedule( $delay = 0 ) {
	if ( ! wp_next_scheduled( 'ic_update_product_data' ) ) {
		wp_schedule_single_event( time() + absint( $delay ), 'ic_update_product_data' );
	}
}

/**
 * Returns the reassignment lock option name.
 *
 * @return string
 */
function ic_update_product_data_lock_option_name() {
	return 'ic_update_product_data_lock';
}

/**
 * Returns the reassignment lock expiration in seconds.
 *
 * @return int
 */
function ic_update_product_data_lock_ttl() {
	return MINUTE_IN_SECONDS * 20;
}

/**
 * Returns the normalized reassignment worker lock payload.
 *
 * @param mixed $lock Raw lock value.
 * @return array
 */
function ic_update_product_data_normalize_lock( $lock ) {
	if ( ! is_array( $lock ) || empty( $lock['token'] ) || empty( $lock['timestamp'] ) ) {
		return array();
	}

	return array(
		'token'     => sanitize_text_field( (string) $lock['token'] ),
		'timestamp' => absint( $lock['timestamp'] ),
		'done'      => isset( $lock['done'] ) ? intval( $lock['done'] ) : 0,
	);
}

/**
 * Returns the current reassignment worker lock.
 *
 * @return array
 */
function ic_update_product_data_get_lock() {
	return ic_update_product_data_normalize_lock( get_option( ic_update_product_data_lock_option_name(), array() ) );
}

/**
 * Returns true when the given lock has expired.
 *
 * @param array $lock Lock payload.
 * @return bool
 */
function ic_update_product_data_is_stale_lock( $lock ) {
	return ! empty( $lock ) && ( time() - absint( $lock['timestamp'] ) ) > ic_update_product_data_lock_ttl();
}

/**
 * Clears the reassignment lock if it is stale.
 *
 * @return bool
 */
function ic_update_product_data_clear_stale_lock() {
	$lock = ic_update_product_data_get_lock();
	if ( ic_update_product_data_is_stale_lock( $lock ) ) {
		delete_option( ic_update_product_data_lock_option_name() );

		return true;
	}

	return false;
}

/**
 * Returns true when the reassignment worker lock exists.
 *
 * @return bool
 */
function ic_update_product_data_has_lock() {
	$lock = ic_update_product_data_get_lock();
	if ( ic_update_product_data_is_stale_lock( $lock ) ) {
		delete_option( ic_update_product_data_lock_option_name() );

		return false;
	}

	return ! empty( $lock );
}

/**
 * Acquires the reassignment worker lock.
 *
 * @param int $done Current progress counter.
 * @return string
 */
function ic_update_product_data_acquire_lock( $done = 0 ) {
	$token       = wp_generate_uuid4();
	$option_name = ic_update_product_data_lock_option_name();
	$lock        = array(
		'token'     => $token,
		'timestamp' => time(),
		'done'      => intval( $done ),
	);

	if ( add_option( $option_name, $lock, '', 'no' ) ) {
		return $token;
	}
	if ( ic_update_product_data_clear_stale_lock() && add_option( $option_name, $lock, '', 'no' ) ) {
		return $token;
	}

	return '';
}

/**
 * Returns true when the current worker owns the reassignment lock.
 *
 * @param string $token Worker token.
 * @return bool
 */
function ic_update_product_data_is_lock_owner( $token ) {
	$lock = ic_update_product_data_get_lock();

	return ! empty( $token ) && ! empty( $lock ) && ! ic_update_product_data_is_stale_lock( $lock ) && $lock['token'] === $token;
}

/**
 * Refreshes the reassignment worker lock heartbeat.
 *
 * @param string $token Worker token.
 * @param int    $done  Current progress counter.
 * @return bool
 */
function ic_update_product_data_refresh_lock( $token, $done = 0 ) {
	if ( ! ic_update_product_data_is_lock_owner( $token ) ) {
		return false;
	}
	update_option(
		ic_update_product_data_lock_option_name(),
		array(
			'token'     => $token,
			'timestamp' => time(),
			'done'      => intval( $done ),
		),
		false
	);

	return true;
}

/**
 * Releases the reassignment worker lock.
 *
 * @param string $token Worker token.
 * @return bool
 */
function ic_update_product_data_release_lock( $token = '' ) {
	if ( empty( $token ) ) {
		delete_option( ic_update_product_data_lock_option_name() );

		return true;
	}
	if ( ! ic_update_product_data_is_lock_owner( $token ) ) {
		return false;
	}
	delete_option( ic_update_product_data_lock_option_name() );

	return true;
}

/**
 * Stops the product data reassignment job and clears its state.
 *
 * @return string
 */
function ic_stop_update_product_data() {
	$option_name      = 'ic_update_product_data_done';
	$hook_name        = 'ic_update_product_data';
	$frozen_hook_name = $hook_name . '_frozen';

	wp_clear_scheduled_hook( $hook_name );
	wp_clear_scheduled_hook( $frozen_hook_name );
	delete_option( $option_name );
	ic_update_product_data_release_lock();
	delete_transient( 'ic_doing_update_product_data_loop' );
	delete_transient( $option_name );
	do_action( 'ic_product_data_reassignment_done' );

	return __( 'Product data reassignment stopped.', 'post-type-x' );
}

/**
 * Returns true when the generic product-data updater is active.
 *
 * This checks for both an active loop transient and the persisted progress
 * option, because the option may temporarily be set to `0` while the updater
 * is still running.
 *
 * @return bool
 */
function ic_update_product_data_is_active() {
	return false !== get_option( 'ic_update_product_data_done', false ) || (bool) get_transient( 'ic_doing_update_product_data_loop' );
}

/**
 * Returns the option name storing the reassignment status scope.
 *
 * @return string
 */
function ic_update_product_data_scope_option_name() {
	return 'ic_update_product_data_scope';
}

/**
 * Returns statuses that can be selected for product data reassignment.
 *
 * @return array
 */
function ic_update_product_data_status_objects() {
	$status_objects  = get_post_stati( array( 'internal' => false ), 'objects' );
	$excluded_status = array( 'trash', 'auto-draft', 'inherit' );

	foreach ( $status_objects as $status => $status_object ) {
		if ( in_array( $status, $excluded_status, true ) ) {
			unset( $status_objects[ $status ] );
		}
	}

	if ( empty( $status_objects['publish'] ) ) {
		$status_objects['publish'] = get_post_status_object( 'publish' );
	}

	return array_filter( $status_objects );
}

/**
 * Returns reassignment status scope options.
 *
 * @return array
 */
function ic_update_product_data_status_options() {
	$options = array(
		'all-user-facing' => __( 'All Post Statuses', 'post-type-x' ),
	);

	foreach ( ic_update_product_data_status_objects() as $status => $status_object ) {
		$options[ $status ] = $status_object->label;
	}

	return $options;
}

/**
 * Normalizes a reassignment status scope.
 *
 * @param string $scope Requested scope.
 * @return string
 */
function ic_update_product_data_normalize_scope( $scope ) {
	$scope   = sanitize_key( (string) $scope );
	$options = ic_update_product_data_status_options();

	if ( isset( $options[ $scope ] ) ) {
		return $scope;
	}

	return 'publish';
}

/**
 * Returns the status scope requested from the system tools UI.
 *
 * @return string
 */
function ic_update_product_data_requested_scope() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only scope selector; no state changes occur here.
	if ( isset( $_GET['reassign_product_data_status'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Scope is sanitized and allow-listed by ic_update_product_data_normalize_scope().
		return ic_update_product_data_normalize_scope( wp_unslash( $_GET['reassign_product_data_status'] ) );
	}

	return 'publish';
}

/**
 * Persists the reassignment status scope.
 *
 * @param string $scope Selected scope.
 * @return void
 */
function ic_update_product_data_save_scope( $scope ) {
	update_option( ic_update_product_data_scope_option_name(), ic_update_product_data_normalize_scope( $scope ), false );
}

/**
 * Returns the active reassignment status scope.
 *
 * @return string
 */
function ic_update_product_data_scope() {
	return ic_update_product_data_normalize_scope( get_option( ic_update_product_data_scope_option_name(), 'publish' ) );
}

/**
 * Clears the reassignment status scope.
 *
 * @return void
 */
function ic_update_product_data_clear_scope() {
	delete_option( ic_update_product_data_scope_option_name() );
}

/**
 * Resolves the reassignment status scope into post statuses.
 *
 * @param string $scope Selected scope.
 * @return array
 */
function ic_update_product_data_resolve_statuses( $scope = '' ) {
	$scope = empty( $scope ) ? ic_update_product_data_scope() : ic_update_product_data_normalize_scope( $scope );
	if ( 'all-user-facing' === $scope ) {
		return array_keys( ic_update_product_data_status_objects() );
	}

	return array( $scope );
}

/**
 * Returns a readable label for the reassignment status scope.
 *
 * @param string $scope Selected scope.
 * @return string
 */
function ic_update_product_data_scope_label( $scope = '' ) {
	$scope   = empty( $scope ) ? ic_update_product_data_scope() : ic_update_product_data_normalize_scope( $scope );
	$options = ic_update_product_data_status_options();

	if ( isset( $options[ $scope ] ) ) {
		return $options[ $scope ];
	}

	return $options['publish'];
}

/**
 * Returns true when the selected reassignment scope includes the given status.
 *
 * @param string $post_status Post status.
 * @param string $scope       Optional reassignment scope.
 * @return bool
 */
function ic_update_product_data_scope_includes_status( $post_status, $scope = '' ) {
	return in_array( $post_status, ic_update_product_data_resolve_statuses( $scope ), true );
}

/**
 * Returns true when a queued default value should overwrite product meta.
 *
 * Empty strings are treated as empty, but `'0'` remains a valid overwrite value.
 *
 * @param mixed $value Candidate default value.
 *
 * @return bool
 */
function ic_epc_sync_default_has_value( $value ) {
	if ( is_array( $value ) ) {
		return ! empty( $value );
	}

	return '' !== $value && null !== $value;
}

/**
 * Normalizes selected sync actions against the allowed action list.
 *
 * @param array|string $actions Requested action or actions.
 * @param array        $allowed_actions Allowed action keys.
 *
 * @return array
 */
function ic_epc_normalize_sync_actions( $actions, $allowed_actions ) {
	if ( ! is_array( $actions ) ) {
		$actions = array( $actions );
	}

	$normalized = array();
	foreach ( $actions as $action ) {
		$action = sanitize_key( $action );
		if ( in_array( $action, $allowed_actions, true ) && ! in_array( $action, $normalized, true ) ) {
			$normalized[] = $action;
		}
	}

	return $normalized;
}

/**
 * Updates product meta in batches.
 *
 * @param int|string $done             Number of processed products or completion marker.
 * @param float      $start_time       Batch start timestamp.
 * @param string     $done_option_name Option name storing progress.
 * @param string     $lock_token       Active worker lock token.
 * @return int|string
 */
function ic_update_product_data_loop( $done, $start_time = 0, $done_option_name = '', $lock_token = '' ) {
	if ( empty( $start_time ) ) {
		$start_time = microtime( true );
	}
	if ( ! empty( $lock_token ) && ! ic_update_product_data_is_lock_owner( $lock_token ) ) {
		return 'stopped';
	}
	$safe_max_time = ic_get_safe_time();
	$scope         = ic_update_product_data_scope();
	$product_args  = apply_filters(
		'ic_update_product_data_product_args',
		array(
			'post_status' => ic_update_product_data_resolve_statuses( $scope ),
		),
		$scope,
		$done
	);
	$products      = get_all_catalog_products( 'date', 'ASC', 300, $done, $product_args );
	foreach ( $products as $post ) {
		if ( ! empty( $lock_token ) && ! ic_update_product_data_is_lock_owner( $lock_token ) ) {
			return 'stopped';
		}
		ic_update_product_data_post( $post );
		++$done;
		clean_post_cache( $post );
		if ( ! empty( $lock_token ) ) {
			ic_update_product_data_refresh_lock( $lock_token, $done );
		}
		$time_elapsed_secs = microtime( true ) - $start_time;
		if ( $time_elapsed_secs > $safe_max_time ) {
			break;
		}
	}
	if ( empty( $products ) ) {

		return 'done';
	}
	$time_elapsed_secs = microtime( true ) - $start_time;

	if ( $time_elapsed_secs < $safe_max_time && ! ic_is_reaching_memory_limit() ) {
		if ( ! empty( $done_option_name ) && ( empty( $lock_token ) || ic_update_product_data_is_lock_owner( $lock_token ) ) ) {
			update_option( $done_option_name, $done );
		}
		set_transient( 'ic_doing_update_product_data_loop', $done, MINUTE_IN_SECONDS * 15 );

		return ic_update_product_data_loop( $done, $start_time, $done_option_name, $lock_token );
	}

	return $done;
}

/**
 * Checks whether a product meta key is safe for the batch updater.
 *
 * @param mixed $meta_key Candidate meta key.
 * @return bool
 */
function ic_update_product_data_is_valid_meta_key( $meta_key ) {
	return is_string( $meta_key ) && '' !== $meta_key && preg_match( '/^[A-Za-z0-9_-]+$/', $meta_key );
}

/**
 * Checks whether a product meta value can be safely written by the batch updater.
 *
 * @param mixed $value Candidate meta value.
 * @return bool
 */
function ic_update_product_data_is_valid_meta_value( $value ) {
	if ( is_scalar( $value ) || null === $value ) {
		return true;
	}

	if ( ! is_array( $value ) ) {
		return false;
	}

	foreach ( $value as $nested_value ) {
		if ( ! ic_update_product_data_is_valid_meta_value( $nested_value ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Keeps only allowed product meta changes returned by maintenance filters.
 *
 * @param mixed $filtered_meta Filtered meta candidate.
 * @param array $base_meta     Meta array before the maintenance filter.
 * @param array $allowed_keys  Meta keys allowed to be changed or added.
 * @return array
 */
function ic_update_product_data_validate_filtered_meta( $filtered_meta, $base_meta, $allowed_keys ) {
	if ( ! is_array( $filtered_meta ) ) {
		return $base_meta;
	}

	if ( ! is_array( $allowed_keys ) ) {
		$allowed_keys = array();
	}
	$allowed_keys = array_filter( array_unique( array_map( 'strval', $allowed_keys ) ), 'ic_update_product_data_is_valid_meta_key' );
	$validated    = $base_meta;
	foreach ( $filtered_meta as $key => $value ) {
		if ( ! in_array( $key, $allowed_keys, true ) || ! ic_update_product_data_is_valid_meta_value( $value ) ) {
			continue;
		}

		$validated[ $key ] = $value;
	}

	return $validated;
}

/**
 * Restores missing public SKU from compressed hidden product data when available.
 *
 * @param array $product_meta Public product meta collected for reassignment.
 * @param int   $product_id   Product ID.
 *
 * @return string
 */
function ic_update_product_data_restore_hidden_sku( $product_meta, $product_id ) {
	if ( ! empty( $product_meta['_sku'] ) || empty( $product_id ) ) {
		return '';
	}

	$hidden_product_data = get_post_meta( $product_id, '_ic_hidden_product_data', true );
	if ( ! is_array( $hidden_product_data ) || empty( $hidden_product_data['_sku'] ) ) {
		return '';
	}

	return $hidden_product_data['_sku'];
}

/**
 * Builds the hidden meta payload that should be saved for a private product.
 *
 * Hidden product data remains the source of truth. Existing public meta values
 * are only copied over when they are new to the hidden payload or when the
 * batch updater explicitly changed them.
 *
 * @param array $product_meta     Raw public product meta collected for reassignment.
 * @param array $new_product_meta Validated meta array after reassignment filters.
 * @return array
 */
function ic_update_product_data_hidden_meta_to_save( $product_meta, $new_product_meta ) {
	$hidden_product_meta = array();
	$restricted_meta     = array(
		'_wp_old_slug',
		'_edit_lock',
		'_edit_last',
	);
	if ( ! empty( $product_meta['_ic_hidden_product_data'] ) && is_array( $product_meta['_ic_hidden_product_data'] ) ) {
		$hidden_product_meta = $product_meta['_ic_hidden_product_data'];
	}
	foreach ( $restricted_meta as $restricted_key ) {
		if ( isset( $hidden_product_meta[ $restricted_key ] ) ) {
			unset( $hidden_product_meta[ $restricted_key ] );
		}
	}

	foreach ( $new_product_meta as $key => $value ) {
		if ( '_ic_hidden_product_data' === $key || in_array( $key, $restricted_meta, true ) ) {
			continue;
		}
		if ( '_sku' === $key ) {
			$hidden_product_meta['_sku'] = $value;
			continue;
		}
		if ( ! isset( $product_meta[ $key ] ) || $value !== $product_meta[ $key ] || ! array_key_exists( $key, $hidden_product_meta ) ) {
			$hidden_product_meta[ $key ] = $value;
		}
	}

	return $hidden_product_meta;
}

/**
 * Ensures the public SKU row matches the reassigned private product SKU.
 *
 * @param int    $post_id           Product ID.
 * @param array  $current_post_keys Raw post meta keys stored for the product.
 * @param string $sku               SKU value that should remain public.
 * @return void
 */
function ic_update_product_data_save_public_sku( $post_id, $current_post_keys, $sku ) {
	if ( '' === $sku || null === $sku ) {
		return;
	}

	if ( is_array( $current_post_keys ) && in_array( '_sku', $current_post_keys, true ) ) {
		$current_sku = get_post_meta( $post_id, '_sku', true );
		if ( $current_sku !== $sku ) {
			update_post_meta( $post_id, '_sku', $sku );
		}

		return;
	}

	add_post_meta( $post_id, '_sku', $sku, true );
}

/**
 * Returns the product register instance used for compression-aware saves.
 *
 * @return IC_Register_Product|null
 */
function ic_update_product_data_register_product() {
	global $ic_register_product;

	if ( is_object( $ic_register_product ) && method_exists( $ic_register_product, 'save_meta' ) ) {
		return $ic_register_product;
	}

	if ( class_exists( 'IC_Register_Product' ) ) {
		$reflection = new ReflectionClass( 'IC_Register_Product' );

		return $reflection->newInstanceWithoutConstructor();
	}

	return null;
}

/**
 * Removes restricted keys from the hidden product data payload.
 *
 * @param int $post_id Product ID.
 * @return void
 */
function ic_update_product_data_cleanup_hidden_meta( $post_id ) {
	$hidden_product_meta = get_post_meta( $post_id, '_ic_hidden_product_data', true );
	if ( ! is_array( $hidden_product_meta ) ) {
		return;
	}

	$restricted_meta = array(
		'_wp_old_slug',
		'_edit_lock',
		'_edit_last',
	);
	$changed         = false;
	foreach ( $restricted_meta as $restricted_key ) {
		if ( isset( $hidden_product_meta[ $restricted_key ] ) ) {
			unset( $hidden_product_meta[ $restricted_key ] );
			$changed = true;
		}
	}

	if ( $changed ) {
		update_post_meta( $post_id, '_ic_hidden_product_data', $hidden_product_meta );
	}
}

/**
 * Normalizes stored product meta for a single post.
 *
 * @param WP_Post $post Product post object.
 * @return void
 */
function ic_update_product_data_post( $post ) {
	$current_post_keys = get_post_custom_keys( $post->ID );
	if ( empty( $current_post_keys ) ) {
		return;
	}
	$product_meta = array();
	foreach ( $current_post_keys as $meta_key ) {
		$product_meta[ $meta_key ] = get_post_meta( $post->ID, $meta_key, true );
	}
	$restored_hidden_sku = ic_update_product_data_restore_hidden_sku( $product_meta, $post->ID );
	$new_product_meta    = apply_filters( 'ic_product_meta_save_update_data', $product_meta );
	if ( ! is_array( $new_product_meta ) ) {
		$new_product_meta = $product_meta;
	}
	$allowed_meta_keys = apply_filters( 'ic_update_product_data_post_meta_allowed_keys', array_keys( $new_product_meta ), $post, $product_meta, $new_product_meta );
	// Allow maintenance tasks to rewrite product meta during the batch updater only.
	$filtered_product_meta = apply_filters( 'ic_update_product_data_post_meta', $new_product_meta, $post, $product_meta );
	$new_product_meta      = ic_update_product_data_validate_filtered_meta( $filtered_product_meta, $new_product_meta, $allowed_meta_keys );
	$register_product      = ic_update_product_data_register_product();
	if ( ic_data_should_be_hidden( $post->post_status ) && is_object( $register_product ) && method_exists( $register_product, 'save_meta' ) ) {
		$hidden_product_meta = ic_update_product_data_hidden_meta_to_save( $product_meta, $new_product_meta );
		$register_product->save_meta( $post, $hidden_product_meta );
		ic_update_product_data_cleanup_hidden_meta( $post->ID );
		$new_product_meta = $hidden_product_meta;
	} elseif ( $product_meta !== $new_product_meta ) {
		foreach ( $new_product_meta as $key => $value ) {
			if ( ! isset( $product_meta[ $key ] ) || $value !== $product_meta[ $key ] ) {
				update_post_meta( $post->ID, $key, $value );
			}
		}
	}
	if ( ic_data_should_be_hidden( $post->post_status ) ) {
		$public_sku = ! empty( $new_product_meta['_sku'] ) ? $new_product_meta['_sku'] : $restored_hidden_sku;
		ic_update_product_data_save_public_sku( $post->ID, $current_post_keys, $public_sku );
	} elseif ( ! empty( $restored_hidden_sku ) ) {
		add_post_meta( $post->ID, '_sku', $restored_hidden_sku, true );
	}
	do_action( 'product_meta_save_update', $new_product_meta, $post );
}

/**
 * Returns a safe maximum runtime for long tasks.
 *
 * @param int $time_limit Requested execution time limit.
 * @return int
 */
function ic_get_safe_time( $time_limit = 300 ) {
	$safe_max_time = ic_get_global( 'safe_max_time' );
	if ( false !== $safe_max_time ) {
		return $safe_max_time;
	}
	$system_max_time = ini_get( 'max_execution_time' );
	if ( $system_max_time < $time_limit ) {
		ic_set_time_limit( $time_limit );
	}
	$max_time      = ini_get( 'max_execution_time' );
	$safe_max_time = 25;

	if ( $max_time ) {
		$safe_max_time = $max_time > 45 ? $max_time - 15 : $safe_max_time;
	}
	ic_save_global( 'safe_max_time', $safe_max_time );

	return $safe_max_time;
}

/**
 * Checks whether the current process is nearing the memory limit.
 *
 * @return bool
 */
function ic_is_reaching_memory_limit() {
	$current_usage = round( memory_get_usage( true ) * 1.15 );
	$current_limit = ini_get( 'memory_limit' );
	if ( false === $current_limit || '' === $current_limit ) {
		return false;
	}
	$current_limit_int = wp_convert_hr_to_bytes( $current_limit );
	if ( $current_limit_int <= 0 ) {
		return false;
	}
	if ( $current_usage > $current_limit_int ) {
		return true;
	}

	return false;
}

/**
 * Raises the PHP memory limit once per request.
 *
 * @return void
 */
function ic_raise_memory_limit() {
	if ( ic_get_global( 'raised_memory_limit' ) ) {
		return;
	}
	$current_limit = ini_get( 'memory_limit' );
	if ( false !== $current_limit && -1 === wp_convert_hr_to_bytes( $current_limit ) ) {
		ic_save_global( 'raised_memory_limit', 1 );

		return;
	}
	wp_raise_memory_limit();
	ic_save_global( 'raised_memory_limit', 1 );
}
