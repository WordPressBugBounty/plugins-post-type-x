<?php
/**
 * Catalog menu element helper.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles custom nav menu items for catalog sections.
 */
class IC_Catalog_Menu_Element {
	/**
	 * Menu section title.
	 *
	 * @var string
	 */
	private $section_name;

	/**
	 * Menu section slug.
	 *
	 * @var string
	 */
	private $section_id;

	/**
	 * Menu field definitions.
	 *
	 * @var array<int, array<string, mixed>>|false
	 */
	private $fields;

	/**
	 * Menu item description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * Frontend item callback.
	 *
	 * @var string
	 */
	private $front;

	/**
	 * Frontend submenu callback.
	 *
	 * @var string
	 */
	private $front_submenu;

	/**
	 * Main rendered menu item.
	 *
	 * @var object|null
	 */
	public $main_item;

	/**
	 * Registers the menu element hooks.
	 *
	 * @param string $section_name       Section title.
	 * @param array  $fields             Section field definitions.
	 * @param string $description        Section description.
	 * @param string $front_func         Frontend item callback.
	 * @param string $front_submenu_func Frontend submenu callback.
	 */
	public function __construct( $section_name, $fields = array(), $description = '', $front_func = '', $front_submenu_func = '' ) {
		$this->section_name  = $section_name;
		$this->section_id    = sanitize_title( $section_name );
		$this->fields        = $this->sanitize_fields( $fields );
		$this->description   = $description;
		$this->front         = $front_func;
		$this->front_submenu = $front_submenu_func;

		if ( false === $this->fields ) {
			return;
		}

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Hooks menu element callbacks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'add_section' ) );
		add_filter( 'wp_setup_nav_menu_item', array( $this, 'item_label' ) );
		add_action( 'wp_update_nav_menu_item', array( $this, 'update_menu_item' ), 10, 2 );
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'fields' ), 10, 2 );
		add_filter( 'walker_nav_menu_start_el', array( $this, 'start_el' ), 10, 4 );
		add_filter( 'wp_nav_menu_objects', array( $this, 'submenu' ), 10, 2 );
		add_filter( 'nav_menu_css_class', array( $this, 'css_class' ), 10, 2 );
	}

	/**
	 * Adds field-based classes to the rendered menu item.
	 *
	 * @param array  $classes   Existing menu item classes.
	 * @param object $menu_item Current menu item object.
	 *
	 * @return array
	 */
	public function css_class( $classes, $menu_item ) {
		if ( ! empty( $menu_item->ic_fields ) ) {
			foreach ( $menu_item->ic_fields as $name => $value ) {
				$classes[] = sanitize_title( $name . '-' . $value );
			}
		}

		return $classes;
	}

	/**
	 * Injects a synthetic submenu item when needed.
	 *
	 * @param array  $items Menu items.
	 * @param object $args  Menu arguments.
	 *
	 * @return array
	 */
	public function submenu( $items, $args ) {
		unset( $args );

		if ( empty( $this->front_submenu ) ) {
			return $items;
		}

		foreach ( $items as $menu_item ) {
			if ( empty( $menu_item->ic_type ) ) {
				continue;
			}

			if ( $menu_item->ic_type === $this->section_id ) {
				$item = array(
					'title'            => 'label',
					'menu_item_parent' => $menu_item->db_id,
					'ID'               => $this->section_id . '_submenu',
					'db_id'            => 'ic_fake' . $menu_item->db_id . 'ic_fake',
					'url'              => '',
					'type'             => $this->section_id . '_submenu',
					'xfn'              => '',
					'current'          => false,
					'target'           => '',
					'classes'          => $menu_item->classes,
				);

				$items[] = (object) $item;
			}
		}

		return $items;
	}

	/**
	 * Registers the admin meta box for the menu element.
	 *
	 * @return void
	 */
	public function add_section() {
		add_meta_box(
			$this->section_id . '-meta-box',
			$this->section_name,
			array(
				$this,
				'section',
			),
			'nav-menus',
			'side',
			'default'
		);
	}

	/**
	 * Renders the admin meta box contents.
	 *
	 * @return void
	 */
	public function section() {
		global $_nav_menu_placeholder, $nav_menu_selected_id;

		$nav_menu_placeholder = ( 0 > $_nav_menu_placeholder ) ? $_nav_menu_placeholder - 1 : -1;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core nav menu handling expects this placeholder global to be decremented here.
		$_nav_menu_placeholder = $nav_menu_placeholder;
		$section_name          = 'ic-menu-section-' . $this->section_id;
		?>
		<div class="posttypediv" id="<?php echo esc_attr( $section_name ); ?>">
			<div id="tabs-panel-lang-switch" class="tabs-panel tabs-panel-active">
				<ul id="lang-switch-checklist" class="categorychecklist form-no-clear">
					<li>
						<label class="menu-item-title">
							<input type="checkbox" class="menu-item-checkbox"
									name="menu-item[<?php echo esc_attr( (string) $nav_menu_placeholder ); ?>][menu-item-object-id]"
									value="-1"> <?php echo esc_html( $this->description ); ?>
						</label>
						<input type="hidden" value="custom"
								name="menu-item[<?php echo esc_attr( (string) $nav_menu_placeholder ); ?>][menu-item-type]"/>
						<input name="menu-item[<?php echo esc_attr( (string) $nav_menu_placeholder ); ?>][menu-item-url]"
								type="hidden" value="#<?php echo esc_attr( $this->section_id ); ?>"/>
						<input name="menu-item[<?php echo esc_attr( (string) $nav_menu_placeholder ); ?>][menu-item-title]"
								type="hidden" value="<?php echo esc_attr( $this->section_name ); ?>"/>
					</li>
				</ul>
			</div>
			<p class="button-controls wp-clearfix">
			<span class="add-to-menu">
				<input type="submit" <?php disabled( $nav_menu_selected_id, 0 ); ?> class="button-secondary submit-add-to-menu right"
						value="<?php esc_attr_e( 'Add to Menu', 'post-type-x' ); ?>"
						name="add-post-type-menu-item" id="submit-<?php echo esc_attr( $section_name ); ?>">

				<span class="spinner"></span>
			</span>
			</p>

		</div>
		<?php
	}

	/**
	 * Renders the custom fields for the menu item.
	 *
	 * @param int    $menu_item_id Menu item ID.
	 * @param object $item         Menu item object.
	 *
	 * @return void
	 */
	public function fields( $menu_item_id, $item ) {
		if ( 'custom' !== $item->type ) {
			return;
		}

		if ( empty( $item->ic_type ) || $item->ic_type !== $this->section_id ) {
			return;
		}

		$current        = ob_get_clean();
		$menu_item_id   = absint( $menu_item_id );
		$menu_item_name = (string) $menu_item_id;

		ob_start();

		$settings_start = '<div class="menu-item-settings wp-clearfix" id="menu-item-settings-' . $menu_item_id . '">';

		if ( ic_string_contains( $current, $settings_start ) ) {
			$current_modified = substr( $current, 0, strpos( $current, $settings_start ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo $current_modified;
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo $settings_start;
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo $current;
		}

		?>
		<input type="hidden" name="menu-item-url[<?php echo esc_attr( $menu_item_name ); ?>]" value="">
		<input type="hidden" name="menu-item-title[<?php echo esc_attr( $menu_item_name ); ?>]"
				value="<?php echo esc_attr( $this->section_name ); ?>">


		<?php
		foreach ( $this->fields as $field ) {
			$id            = 'edit-menu-item-' . $field['name'] . '-' . $menu_item_id;
			$type          = isset( $field['type'] ) ? $field['type'] : 'text';
			$current_value = isset( $item->ic_fields[ $field['name'] ] ) ? $item->ic_fields[ $field['name'] ] : '';
			$value         = ( 'checkbox' === $type ) ? $field['value'] : $current_value;
			?>
			<p class="field-title description description-wide">
				<label for="<?php echo esc_attr( $id ); ?>">
					<?php
					if ( 'checkbox' !== $type ) {
						echo esc_html( $field['label'] );
						echo '<br/>';
					}
					?>
					<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>"
							class="widefat edit-menu-item-<?php echo esc_attr( $field['name'] ); ?>"
							name="<?php echo esc_attr( $field['name'] . '[' . $menu_item_id . ']' ); ?>"
							value="<?php echo esc_attr( $value ); ?>"<?php checked( $current_value, $value ); ?>/>
					<?php
					if ( 'checkbox' === $type ) {
						echo ' ' . esc_html( $field['label'] );
					}
					?>
				</label>
			</p>
			<?php
		}
	}

	/**
	 * Filters the menu item output on the frontend.
	 *
	 * @param string $item_output Existing menu item output.
	 * @param object $item        Menu item object.
	 * @param int    $depth       Menu depth.
	 * @param object $args        Menu arguments.
	 *
	 * @return string
	 */
	public function start_el( $item_output, $item, $depth, $args ) {
		unset( $depth, $args );

		if ( 'custom' !== $item->type ) {
			if ( $item->type === $this->section_id . '_submenu' ) {
				if ( ! empty( $this->front_submenu ) && function_exists( $this->front_submenu ) ) {
					return call_user_func( $this->front_submenu, $item, $this );
				}
			}

			return $item_output;
		}

		if ( empty( $item->ic_type ) || $item->ic_type !== $this->section_id ) {
			return $item_output;
		}

		if ( ! empty( $this->front ) && function_exists( $this->front ) ) {
			$this->main_item = $item;

			return call_user_func( $this->front, $item, $this );
		}

		return $item_output;
	}

	/**
	 * Persists the custom menu item metadata.
	 *
	 * @param int $menu_id         Menu ID.
	 * @param int $menu_item_db_id Menu item ID.
	 *
	 * @return void
	 */
	public function update_menu_item( $menu_id = 0, $menu_item_db_id = 0 ) {
		unset( $menu_id );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		// Add new menu item via ajax.
		$menu_settings_column_nonce = isset( $_REQUEST['menu-settings-column-nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['menu-settings-column-nonce'] ) ) : '';
		if ( wp_verify_nonce( $menu_settings_column_nonce, 'add-menu_item' ) ) {
			$posted_menu_items = isset( $_POST['menu-item'] ) && is_array( $_POST['menu-item'] ) ? map_deep( wp_unslash( $_POST['menu-item'] ), 'sanitize_text_field' ) : array();
			foreach ( $posted_menu_items as $item ) {
				$menu_item_object_id = isset( $item['menu-item-object-id'] ) ? (int) $item['menu-item-object-id'] : 0;
				$menu_item_url       = isset( $item['menu-item-url'] ) ? $item['menu-item-url'] : '';

				if ( empty( $menu_item_object_id ) || -1 !== $menu_item_object_id ) {
					continue;
				}

				if ( ! empty( $menu_item_url ) && '#' . $this->section_id === $menu_item_url ) {
					update_post_meta( $menu_item_db_id, '_menu_item_ic_type', $this->section_id );
					update_post_meta( $menu_item_db_id, '_menu_item_url', '' );
				}
			}
		}

		// Update settings for existing menu items.
		$update_nav_menu_nonce = isset( $_REQUEST['update-nav-menu-nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['update-nav-menu-nonce'] ) ) : '';
		if ( wp_verify_nonce( $update_nav_menu_nonce, 'update-nav_menu' ) ) {
			foreach ( $this->fields as $field ) {
				$value = isset( $_POST[ $field['name'] ][ $menu_item_db_id ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field['name'] ][ $menu_item_db_id ] ) ) : '';
				if ( ! empty( $value ) ) {
					update_post_meta( $menu_item_db_id, $this->meta_name( $field['name'] ), ic_sanitize( $value ) );
				} else {
					delete_post_meta( $menu_item_db_id, $this->meta_name( $field['name'] ) );
				}
			}
		}
	}

	/**
	 * Populates the admin label data for the custom item.
	 *
	 * @param object $menu_item Menu item object.
	 *
	 * @return object
	 */
	public function item_label( $menu_item ) {
		if ( 'custom' !== $menu_item->type ) {
			return $menu_item;
		}

		$menu_item_type = $this->get_value( $menu_item->ID, 'type' );
		if ( $menu_item_type !== $this->section_id ) {
			return $menu_item;
		}

		$menu_item->ic_type    = $menu_item_type;
		$menu_item->type_label = $this->section_name;

		foreach ( $this->fields as $field ) {
			$meta_value = $this->get_value( $menu_item->ID, $field['name'] );
			if ( ! empty( $field['is-button-label'] ) ) {
				$menu_item->post_title = $meta_value;
				$menu_item->title      = $meta_value;
			}
			if ( empty( $menu_item->ic_fields ) ) {
				$menu_item->ic_fields = array();
			}
			$menu_item->ic_fields[ $field['name'] ] = $meta_value;
		}

		return $menu_item;
	}

	/**
	 * Gets a stored or default field value.
	 *
	 * @param int    $menu_item_id Menu item ID.
	 * @param string $name         Field name.
	 *
	 * @return string
	 */
	public function get_value( $menu_item_id, $name ) {
		$meta_value = get_post_meta( $menu_item_id, $this->meta_name( $name ), true );
		if ( empty( $meta_value ) ) {
			$meta_value = $this->default_value( $name );
		}

		return $meta_value;
	}

	/**
	 * Builds the post meta key for a field.
	 *
	 * @param string $name Field name.
	 *
	 * @return string
	 */
	public function meta_name( $name ) {
		return '_menu_item_ic_' . $name;
	}

	/**
	 * Gets the default value for a field.
	 *
	 * @param string $name Field name.
	 *
	 * @return string
	 */
	public function default_value( $name ) {
		foreach ( $this->fields as $field ) {
			if ( $field['name'] === $name ) {
				$field['type'] = isset( $field['type'] ) ? $field['type'] : 'text';

				if ( 'checkbox' === $field['type'] ) {
					return '';
				}

				return $field['value'];
			}
		}

		return '';
	}

	/**
	 * Sanitizes the field definitions.
	 *
	 * @param array $fields Raw field definitions.
	 *
	 * @return array|false
	 */
	public function sanitize_fields( $fields ) {
		foreach ( $fields as $key => $field ) {
			$fields[ $key ]['name'] = empty( $field['name'] ) ? '' : sanitize_title( $field['name'] );
			if ( empty( $fields[ $key ]['name'] ) ) {
				return false;
			}
			$fields[ $key ]['value']           = isset( $fields[ $key ]['value'] ) ? ic_sanitize( $fields[ $key ]['value'] ) : '';
			$fields[ $key ]['label']           = isset( $fields[ $key ]['label'] ) ? ic_sanitize( $fields[ $key ]['label'] ) : '';
			$fields[ $key ]['is-button-label'] = isset( $fields[ $key ]['is-button-label'] ) ? intval( $fields[ $key ]['is-button-label'] ) : '';
		}

		return $fields;
	}
}

if ( ! class_exists( 'ic_catalog_menu_element', false ) ) {
	class_alias( 'IC_Catalog_Menu_Element', 'ic_catalog_menu_element' );
}
