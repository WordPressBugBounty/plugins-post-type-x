<?php
/**
 * System status screen.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the system status submenu.
 */
function register_product_system() {
	add_submenu_page( 'edit.php?post_type=al_product', __( 'System Status', 'post-type-x' ), __( 'System Status', 'post-type-x' ), apply_filters( 'ic_system_status_cap', 'manage_product_settings' ), 'system.php', 'ic_system_status' );
}

add_action( 'product_settings_menu', 'register_product_system' );

/**
 * Renders the system status page.
 */
function ic_system_status() {
	if ( current_user_can( 'manage_product_settings' ) ) {
		if ( isset( $_GET['reset_product_settings'] ) ) {
			if ( isset( $_GET['reset_product_settings_confirm'] ) && check_admin_referer( 'ic_reset_product_settings_confirm' ) ) {
				foreach ( all_ic_options( 'options' ) as $option ) {
					delete_option( $option );
				}
				permalink_options_update();
				implecode_success( __( 'Catalog Settings successfully reset to default!', 'post-type-x' ) );
			} else {
					echo '<h3>' . esc_html__( 'All catalog settings will be reset to defaults. Would you like to proceed?', 'post-type-x' ) . '</h3>';
					$confirm_reset_url = wp_nonce_url( add_query_arg( 'reset_product_settings_confirm', 1 ), 'ic_reset_product_settings_confirm' );
					echo '<a class="button" href="' . esc_url( $confirm_reset_url ) . '">' . esc_html__( 'Yes', 'post-type-x' ) . '</a> <a class="button" href="' . esc_url( remove_query_arg( 'reset_product_settings' ) ) . '">' . esc_html__( 'No', 'post-type-x' ) . '</a>';
			}
		} elseif ( isset( $_GET['delete_all_products'] ) ) {
			if ( isset( $_GET['delete_all_products_confirm'] ) && check_admin_referer( 'ic_delete_all_products_confirm' ) ) {
					$product_ids = get_posts(
						array(
							'post_type'      => 'al_product',
							'post_status'    => get_post_stati( array(), 'names' ),
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'no_found_rows'  => true,
						)
					);
				foreach ( $product_ids as $product_id ) {
					wp_delete_post( (int) $product_id, true );
				}
				if ( function_exists( 'ic_delete_all_attribute_terms' ) ) {
					ic_delete_all_attribute_terms();
				}
				if ( function_exists( 'ic_update_category_count' ) ) {
					ic_update_category_count();
				}
				implecode_success( __( 'All Catalog Products successfully deleted!', 'post-type-x' ) );
			} else {
					echo '<h3>' . esc_html__( 'All items will be permanently deleted. Would you like to proceed?', 'post-type-x' ) . '</h3>';
					$delete_products_confirm_url = wp_nonce_url( add_query_arg( 'delete_all_products_confirm', 1 ), 'ic_delete_all_products_confirm' );
					echo '<a class="button" href="' . esc_url( $delete_products_confirm_url ) . '">' . esc_html__( 'Yes', 'post-type-x' ) . '</a> <a class="button" href="' . esc_url( remove_query_arg( 'delete_all_products' ) ) . '">' . esc_html__( 'No', 'post-type-x' ) . '</a>';
			}
		} elseif ( isset( $_GET['delete_all_product_categories'] ) ) {
			if ( isset( $_GET['delete_all_product_categories_confirm'] ) && check_admin_referer( 'ic_delete_all_product_categories_confirm' ) ) {
					$taxonomy = 'al_product-cat';
					$terms    = get_terms(
						array(
							'taxonomy'   => $taxonomy,
							'hide_empty' => false,
						)
					);
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						wp_delete_term( (int) $term->term_id, $taxonomy );
						delete_option( 'al_product_cat_image_' . $term->term_id );
					}
				}
					implecode_success( __( 'All Catalog Categories successfully deleted!', 'post-type-x' ) );
			} else {
				echo '<h3>' . esc_html__( 'All catalog categories will be permanently deleted. Would you like to proceed?', 'post-type-x' ) . '</h3>';
				$delete_categories_confirm_url = wp_nonce_url( add_query_arg( 'delete_all_product_categories_confirm', 1 ), 'ic_delete_all_product_categories_confirm' );
				echo '<a class="button" href="' . esc_url( $delete_categories_confirm_url ) . '">' . esc_html__( 'Yes', 'post-type-x' ) . '</a> <a class="button" href="' . esc_url( remove_query_arg( 'delete_all_product_categories' ) ) . '">' . esc_html__( 'No', 'post-type-x' ) . '</a>';
			}
		} elseif ( isset( $_GET['delete_old_filters_bar'] ) ) {
			if ( isset( $_GET['delete_old_filters_bar_confirm'] ) && check_admin_referer( 'ic_delete_old_filters_bar_confirm' ) ) {
				delete_option( 'old_sort_bar' );
				implecode_success( __( 'Filters bar is now empty by default!', 'post-type-x' ) );
			} else {
					echo '<h3>' . esc_html__( 'Default filters bar will become empty.', 'post-type-x' ) . '</h3>';
					$delete_old_filters_bar_confirm = wp_nonce_url( add_query_arg( 'delete_old_filters_bar_confirm', 1 ), 'ic_delete_old_filters_bar_confirm' );
					echo '<a class="button" href="' . esc_url( $delete_old_filters_bar_confirm ) . '">' . esc_html__( 'OK', 'post-type-x' ) . '</a> <a class="button" href="' . esc_url( remove_query_arg( 'delete_old_filters_bar' ) ) . '">' . esc_html__( 'Cancel', 'post-type-x' ) . '</a>';
			}
		} else {
			?>
			<style>table.widefat {
					width: 95%
				}

				table tbody tr td:first-child {
					width: 350px
				}

				table tbody tr:nth-child(even) {
					background: #fafafa;
				}</style>
			<p></p>
			<table class="widefat" cellspacing="0" id="ic_tools">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'impleCode Tools', 'post-type-x' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
						<td><?php esc_html_e( 'Reset product settings', 'post-type-x' ); ?>:</td>
						<td><a class="button"
								href="<?php echo esc_url( add_query_arg( 'reset_product_settings', 1 ) ); ?>"><?php esc_html_e( 'Reset Catalog Settings', 'post-type-x' ); ?></a>
					</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Delete all items', 'post-type-x' ); ?>:</td>
						<td><a class="button"
								href="<?php echo esc_url( add_query_arg( 'delete_all_products', 1 ) ); ?>"><?php esc_html_e( 'Delete all Catalog Items', 'post-type-x' ); ?></a>
					</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Delete all categories', 'post-type-x' ); ?>:</td>
						<td><a class="button"
								href="<?php echo esc_url( add_query_arg( 'delete_all_product_categories', 1 ) ); ?>"><?php esc_html_e( 'Delete all Catalog Categories', 'post-type-x' ); ?></a>
					</td>
				</tr>
				<?php
				if ( 1 === (int) get_option( 'old_sort_bar' ) ) {
					?>
					<tr>
							<td><?php esc_html_e( 'Make default filters bar empty.', 'post-type-x' ); ?>:</td>
							<td><a class="button"
									href="<?php echo esc_url( add_query_arg( 'delete_old_filters_bar', 1 ) ); ?>"><?php esc_html_e( 'Empty Default Filters Bar', 'post-type-x' ); ?></a>
						</td>
					</tr>
				<?php } ?>
				<tr>
						<td><?php esc_html_e( 'Delete all items and categories on uninstall', 'post-type-x' ); ?>:
					</td>
					<?php $checked = get_option( 'ic_delete_products_uninstall', 0 ); ?>
					<td><input type="checkbox" name="delete_products_uninstall" <?php checked( 1, $checked ); ?> /></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Reassign product data', 'post-type-x' ); ?>:</td>
					<?php
					$info = '';
					if ( ! empty( $_GET['stop_reassign_product_data'] ) && check_admin_referer( 'ic_stop_reassign_product_data' ) ) {
						$info = ic_stop_update_product_data();
					}
					if ( ! empty( $_GET['reassign_product_data'] ) && check_admin_referer( 'ic_reassign_product_data' ) ) {
						$info = ic_update_product_data();
					}
					$button_label   = esc_html__( 'Reassign data', 'post-type-x' );
					$done           = get_option( 'ic_update_product_data_done', 0 );
					$scope          = ic_update_product_data_scope();
					$scope_options  = ic_update_product_data_status_options();
					$scope_disabled = ! empty( $done ) ? ' disabled="disabled"' : '';
					if ( ! empty( $done ) ) {
						$button_label = esc_html__( 'Speed up', 'post-type-x' );
					}
						$reassign_data = '<form method="get" action="' . esc_url( admin_url( 'edit.php' ) ) . '" style="display:inline-block; margin-right:8px;">';
					$reassign_data    .= '<input type="hidden" name="post_type" value="al_product" />';
					$reassign_data    .= '<input type="hidden" name="page" value="system.php" />';
					$reassign_data    .= '<input type="hidden" name="reassign_product_data" value="1" />';
					$reassign_data    .= wp_nonce_field( 'ic_reassign_product_data', '_wpnonce', true, false );
					$reassign_data    .= '<select name="reassign_product_data_status"' . $scope_disabled . '>';
					foreach ( $scope_options as $scope_key => $scope_label ) {
						$reassign_data .= '<option value="' . esc_attr( $scope_key ) . '"' . selected( $scope, $scope_key, false ) . '>' . esc_html( $scope_label ) . '</option>';
					}
					$reassign_data .= '</select> ';
					$reassign_data .= '<button type="submit" class="button">' . $button_label . '</button>';
					$reassign_data .= '</form>';
					if ( ! empty( $done ) ) {
							$reassign_data .= '<form method="get" action="' . esc_url( admin_url( 'edit.php' ) ) . '" style="display:inline-block;vertical-align:top;">';
						$reassign_data     .= '<input type="hidden" name="post_type" value="al_product" />';
						$reassign_data     .= '<input type="hidden" name="page" value="system.php" />';
						$reassign_data     .= '<input type="hidden" name="stop_reassign_product_data" value="1" />';
						$reassign_data     .= wp_nonce_field( 'ic_stop_reassign_product_data', '_wpnonce', true, false );
						$reassign_data     .= '<button type="submit" class="button">' . esc_html__( 'Stop', 'post-type-x' ) . '</button>';
						$reassign_data     .= '</form>';
					}
					$reassign_data .= '<p>' . esc_html__( 'Status scope:', 'post-type-x' ) . ' ' . esc_html( ic_update_product_data_scope_label( $scope ) ) . '</p>';
					if ( ! empty( $done ) ) {
						if ( $done < 0 ) {
							$done = 0;
						}
						$reassign_data .= '<p>' . absint( $done ) . ' ' . esc_html__( 'Items Done! Still processing.', 'post-type-x' ) . '</p>';
					}
					if ( ! empty( $info ) ) {
						$reassign_data .= '<p>' . esc_html( $info ) . '</p>';
					}
					?>
						<td>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
							echo $reassign_data;
							?>
						</td>
				</tr>
				<?php do_action( 'ic_system_tools' ); ?>
				</tbody>
			</table>
			<p></p>
			<table class="widefat" cellspacing="0" id="ic-wordpress-environment-status">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'WordPress Environment', 'post-type-x' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
						<td><?php esc_html_e( 'Home URL', 'post-type-x' ); ?>:</td>
						<td><?php echo esc_url( home_url() ); ?></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Site URL', 'post-type-x' ); ?>:</td>
						<td><?php echo esc_url( site_url() ); ?></td>
				</tr>
				<tr>
					<td>
					<?php
							/* translators: %s: plugin name. */
							printf( esc_html__( '%s Version', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
					?>
						:
					</td>
					<td>
						<?php
						$plugin_data    = get_plugin_data( AL_PLUGIN_MAIN_FILE );
						$plugin_version = $plugin_data['Version'];
							echo esc_html( $plugin_version );
						?>
					</td>
				</tr>
				<tr>
						<td><?php /* translators: %s: plugin name. */ printf( esc_html__( '%s Database Version', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ); ?>
						:
					</td>
						<td><?php echo esc_html( get_option( 'ecommerce_product_catalog_ver', $plugin_version ) ); ?></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'WP Version', 'post-type-x' ); ?>:</td>
						<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'WP Multisite', 'post-type-x' ); ?>:</td>
					<td>
					<?php
					if ( is_multisite() ) {
						echo '&#10004;';
					} else {
						echo '&ndash;';
					}
					?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'WP Memory Limit', 'post-type-x' ); ?>:</td>
					<td>
					<?php
						$memory = WP_MEMORY_LIMIT;
					if ( is_numeric( $memory ) ) {
							echo esc_html( size_format( $memory ) );
					} else {
						echo esc_html( $memory );
					}
					?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'WP Debug Mode', 'post-type-x' ); ?>:</td>
					<td>
					<?php
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						echo '&#10004;';
					} else {
						echo '&ndash;';
					}
					?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Language', 'post-type-x' ); ?>:</td>
						<td><?php echo esc_html( get_locale() ); ?></td>
				</tr>
				</tbody>
			</table>
			<p></p>
			<table class="widefat" cellspacing="0" id="ic-server-environment-status">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'Server Environment', 'post-type-x' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
						<td><?php esc_html_e( 'Server Info', 'post-type-x' ); ?>:</td>
						<td>
							<?php
							$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
							echo esc_html( $server_software );
							?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'PHP Version', 'post-type-x' ); ?>:</td>
					<td>
					<?php
					if ( function_exists( 'phpversion' ) ) {
							echo esc_html( phpversion() );
					}
					?>
						</td>
				</tr>
				<?php if ( function_exists( 'ini_get' ) ) : ?>
					<tr>
							<td><?php esc_html_e( 'PHP Post Max Size', 'post-type-x' ); ?>:</td>
						<td>
						<?php
							$max_size = ini_get( 'post_max_size' );
						if ( is_numeric( $max_size ) ) {
								echo esc_html( size_format( $max_size ) );
						} else {
							echo esc_html( $max_size );
						}
						?>
							</td>
					</tr>
					<tr>
							<td><?php esc_html_e( 'PHP Time Limit', 'post-type-x' ); ?>:</td>
							<td><?php echo esc_html( ini_get( 'max_execution_time' ) ); ?></td>
					</tr>
					<tr>
							<td><?php esc_html_e( 'PHP Max Input Vars', 'post-type-x' ); ?>:</td>
							<td><?php echo esc_html( ini_get( 'max_input_vars' ) ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
						<td><?php esc_html_e( 'MySQL Version', 'post-type-x' ); ?>:</td>
						<td>
							<?php
							/**
							 * WordPress database abstraction object.
							 *
							 * @var wpdb $wpdb
							 */
							global $wpdb;
							echo esc_html( $wpdb->db_version() );
							?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Max Upload Size', 'post-type-x' ); ?>:</td>
						<td><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></td>
				</tr>
				</tbody>
			</table>
			<p></p>
			<table class="widefat" cellspacing="0" id="ic-server-locale-status">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'Server Locale', 'post-type-x' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php
				$locale = localeconv();
				foreach ( $locale as $key => $val ) {
					if ( in_array(
						$key,
						array(
							'decimal_point',
							'mon_decimal_point',
							'thousands_sep',
							'mon_thousands_sep',
						),
						true
					) ) {
							echo '<tr><td>' . esc_html( $key ) . ':</td><td>' . esc_html( $val ? $val : __( 'N/A', 'post-type-x' ) ) . '</td></tr>';
					}
				}
				?>
				</tbody>
			</table>
			<p></p>
			<table class="widefat" cellspacing="0" id="ic-active-plugins-status">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'Active Plugins', 'post-type-x' ); ?>
							(<?php echo absint( count( (array) get_option( 'active_plugins' ) ) ); ?>)
					</th>
				</tr>
				</thead>
				<tbody>
				<?php
				$active_plugins = (array) get_option( 'active_plugins', array() );

				if ( is_multisite() ) {
					$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
				}

				foreach ( $active_plugins as $plugin ) {

					$plugin_data    = @get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
					$dirname        = dirname( $plugin );
					$version_string = '';
					$network_string = '';

					if ( ! empty( $plugin_data['Name'] ) ) {

							// Link the plugin name to the plugin URL if available.
							$plugin_name = esc_html( $plugin_data['Name'] );

						if ( ! empty( $plugin_data['PluginURI'] ) ) {
							$plugin_name = '<a href="' . esc_url( $plugin_data['PluginURI'] ) . '" title="' . esc_attr__( 'Visit plugin homepage', 'post-type-x' ) . '">' . $plugin_name . '</a>';
						}
						?>
						<tr>
								<td>
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
									echo $plugin_name;
									?>
								</td>
								<td>
									<?php
									/* translators: %s: plugin author. */
									echo sprintf( esc_html_x( 'by %s', 'by author', 'post-type-x' ), esc_html( wp_strip_all_tags( $plugin_data['Author'] ) ) ) . ' &ndash; ' . esc_html( $plugin_data['Version'] ) . esc_html( $version_string ) . esc_html( $network_string );
									?>
								</td>
						</tr>
						<?php
					}
				}
				?>
				</tbody>
			</table>
			<p></p>
			<table class="widefat" cellspacing="0">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'Theme', 'post-type-x' ); ?></th>
				</tr>
				</thead>
				<?php
				$active_theme = wp_get_theme();
				if ( $active_theme->exists() ) {
					?>
				<tbody>
				<tr>
						<td><?php esc_html_e( 'Name', 'post-type-x' ); ?>:</td>
						<td><?php echo esc_html( $active_theme->display( 'Name' ) ); ?></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Version', 'post-type-x' ); ?>:</td>
					<td>
					<?php
							echo esc_html( $active_theme->display( 'Version' ) );
					?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Author URL', 'post-type-x' ); ?>:</td>
						<td><?php echo esc_url( $active_theme->display( 'AuthorURI' ) ); ?></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Child Theme', 'post-type-x' ); ?>:</td>
						<td>
						<?php
						if ( is_child_theme() ) {
							echo '<mark class="yes">&#10004;</mark>';
						} else {
							echo '&#10005; &ndash; ';
							/* translators: %s: plugin name. */
							printf( esc_html__( 'If you\'re modifying %s or a parent theme you didn\'t build personally we recommend using a child theme. See:', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
							echo ' <a href="' . esc_url( 'http://codex.wordpress.org/Child_Themes' ) . '" target="_blank">' . esc_html__( 'How to create a child theme', 'post-type-x' ) . '</a>';
						}
						?>
						</td>
				</tr>
					<?php
					if ( is_child_theme() && $active_theme->get( 'Template' ) ) {
						$parent_theme = wp_get_theme( $active_theme->get_template() );
						?>
					<tr>
							<td><?php esc_html_e( 'Parent Theme Name', 'post-type-x' ); ?>:</td>
							<td><?php echo esc_html( $parent_theme->display( 'Name' ) ); ?></td>
					</tr>
					<tr>
							<td><?php esc_html_e( 'Parent Theme Version', 'post-type-x' ); ?>:</td>
							<td><?php echo esc_html( $parent_theme->display( 'Version' ) ); ?></td>
					</tr>
					<tr>
							<td><?php esc_html_e( 'Parent Theme Author URL', 'post-type-x' ); ?>:</td>
							<td><?php echo esc_url( $parent_theme->display( 'AuthorURI' ) ); ?></td>
					</tr>
						<?php
					}
				}
				?>
				<tr>
						<td><?php /* translators: %s: plugin name. */ printf( esc_html__( '%s Support', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ); ?>
						:
					</td>
					<td>
					<?php
					if ( ! is_theme_implecode_supported() ) {
							esc_html_e( 'Not Declared', 'post-type-x' );
					} else {
						echo '&#10004;';
					}
					?>
						</td>
				</tr>
				</tbody>
			</table>
			<script>
				jQuery(document).ready(function () {
					jQuery("input[type='checkbox']").change(function () {
						checkbox = jQuery(this);
						if (checkbox.is(":checked")) {
							checked = 1;
						} else {
							checked = 0;
						}
							data = {
								action: "save_implecode_tools",
								field: checkbox.attr('name') + "|" + checked,
								nonce: "<?php echo esc_js( wp_create_nonce( 'ic-ajax-nonce' ) ); ?>"
							};
							jQuery.post("<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>", data, function (response) {
							checkbox.after("<span class='saved'>Saved!</span>");
							jQuery(".saved").delay(2000).fadeOut(300, function () {
								jQuery(this).remove();
							});
						});
					});
				});
			</script>
			<?php
		}
	}
}

add_action( 'wp_ajax_save_implecode_tools', 'ajax_save_implecode_tools' );

/**
 * Saves impleCode tools settings via AJAX.
 */
function ajax_save_implecode_tools() {
	if ( ! empty( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ic-ajax-nonce' ) && current_user_can( 'manage_product_settings' ) ) {
		if ( isset( $_POST['field'] ) ) {
			$checked = sanitize_text_field( wp_unslash( $_POST['field'] ) );
			if ( false !== strpos( $checked, '|' ) ) {
				$checked = explode( '|', $checked );
				if ( isset( $checked[0], $checked[1] ) ) {
					update_option( 'ic_' . sanitize_key( $checked[0] ), sanitize_text_field( $checked[1] ), false );
				}
			}
		}
	}
	echo 'done';

	wp_die(); // This is required to terminate immediately and return a proper response.
}
