<?php
/**
 * Capability helpers for eCommerce Product Catalog.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages capabilities
 *
 * Here all capabilities are defined and managed.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog/includes
 * @author  impleCode
 *
 * @param string $role Role name.
 * @return string[]
 */
function ic_catalog_caps( $role = 'administrator' ) {
	$caps = array(
		'publish_products',
		'edit_products',
		'edit_others_products',
		'edit_private_products',
		'delete_products',
		'delete_others_products',
		'read_private_products',
		'delete_private_products',
		'delete_published_products',
		'edit_published_products',
		'manage_product_categories',
		'edit_product_categories',
		'delete_product_categories',
		'assign_product_categories',
	);
	if ( 'administrator' === $role ) {
		$caps[] = 'manage_product_settings';
	}

	return $caps;
}

/**
 * Adds catalog capabilities to a role object.
 *
 * @param WP_Role     $role Role object.
 * @param string|null $role_name Role name override.
 * @return void
 */
function ic_catalog_add_caps( $role, $role_name = null ) {
	if ( empty( $role->name ) ) {
		return;
	}
	if ( empty( $role_name ) ) {
		$role_name = $role->name;
	}
	$caps = ic_catalog_caps( $role_name );
	foreach ( $caps as $cap ) {
		$role->add_cap( $cap );
	}
}

/**
 * Checks whether the current user has all requested capabilities.
 *
 * @param string|string[] $caps Capability or list of capabilities.
 * @return bool
 */
function ic_current_user_can( $caps ) {
	if ( ! is_array( $caps ) ) {
		$caps = array( $caps );
	}
	foreach ( $caps as $cap ) {
		if ( ! current_user_can( $cap ) ) {
			return false;
		}
	}

	return true;
}

add_action( 'admin_init', 'ic_restore_product_caps' );

/**
 * Restores product capabilities if admin doesn't have proper rights.
 *
 * @return void
 */
function ic_restore_product_caps() {
	$current_user = wp_get_current_user();

	if ( in_array( 'administrator', (array) $current_user->roles, true ) && ! ic_current_user_can( ic_catalog_caps() ) ) {
		add_product_caps( false );
	}
}

/**
 * Adds product capabilities to the administrator and privileged roles.
 *
 * @param bool $additional Whether to add capabilities to additional roles.
 * @return void
 */
function add_product_caps( $additional = true ) {
	if ( is_user_logged_in() && current_user_can( 'activate_plugins' ) ) {
		$role = get_role( 'administrator' );

		ic_catalog_add_caps( $role );

		if ( $additional ) {
			$current_user = wp_get_current_user();
			if ( ! empty( $current_user->roles ) && is_array( $current_user->roles ) ) {
				foreach ( $current_user->roles as $current_role ) {
					if ( 'administrator' === $current_role ) {
						break;
					}
					$role         = get_role( $current_role );
					$capabilities = $role->capabilities;
					if ( ! empty( $capabilities['activate_plugins'] ) ) {
						ic_catalog_add_caps( $role, 'administrator' );
					}
				}
			}
			ic_add_catalog_manager_role();
		}
	}
}

/**
 * Registers the catalog manager role when it does not exist.
 *
 * @return void
 */
function ic_add_catalog_manager_role() {
	$manager_role = get_role( 'catalog_manager' );
	if ( ! empty( $manager_role ) ) {
		return;
	}
	$role = get_role( 'editor' );
	if ( ! empty( $role ) ) {
		$capabilities = $role->capabilities;
	} else {
		$capabilities = array();
	}
	$manager_role = add_role( 'catalog_manager', __( 'Catalog Manager', 'post-type-x' ), $capabilities );
	if ( is_object( $manager_role ) ) {
		if ( empty( $capabilities ) ) {
			$manager_role->add_cap( 'moderate_comments' );
			// Intentionally do not grant category-management capability here.
			$manager_role->add_cap( 'manage_links' );
			$manager_role->add_cap( 'upload_files' );
			$manager_role->add_cap( 'unfiltered_html' );
			$manager_role->add_cap( 'edit_posts' );
			// Intentionally do not grant the ability to edit others' posts.
			$manager_role->add_cap( 'edit_published_posts' );
			$manager_role->add_cap( 'publish_posts' );
			$manager_role->add_cap( 'edit_pages' );
			$manager_role->add_cap( 'read' );
			$manager_role->add_cap( 'level_7' );
			$manager_role->add_cap( 'level_6' );
			$manager_role->add_cap( 'level_5' );
			$manager_role->add_cap( 'level_4' );
			$manager_role->add_cap( 'level_3' );
			$manager_role->add_cap( 'level_2' );
			$manager_role->add_cap( 'level_1' );
			$manager_role->add_cap( 'level_0' );
		}

		ic_catalog_add_caps( $manager_role );
	}
}

add_filter( 'map_meta_cap', 'ic_products_map_meta_cap', 10, 4 );

/**
 * Maps product meta capabilities to primitive capabilities.
 *
 * @param string[] $caps Primitive capabilities for meta capability checks.
 * @param string   $cap Requested capability.
 * @param int      $user_id Current user ID.
 * @param array    $args Capability arguments.
 * @return string[]
 */
function ic_products_map_meta_cap( $caps, $cap, $user_id, $args ) {
	if ( empty( $args[0] ) || ! is_numeric( $args[0] ) ) {
		return $caps;
	}
	if ( 'edit_product' === $cap || 'delete_product' === $cap || 'read_product' === $cap ) {
		$post = get_post( $args[0] );
		if ( empty( $post ) ) {
			return $caps;
		}
		$post_type = get_post_type_object( $post->post_type );
		if ( empty( $post_type ) ) {
			return $caps;
		}
		$caps = array();
		if ( 'edit_product' === $cap ) {
			if ( $user_id === $post->post_author ) {
				if ( isset( $post_type->cap->edit_posts ) ) {
					$caps[] = $post_type->cap->edit_posts;
				}
			} elseif ( isset( $post_type->cap->edit_others_posts ) ) {
				$caps[] = $post_type->cap->edit_others_posts;
			}
		} elseif ( 'delete_product' === $cap ) {
			if ( $user_id === $post->post_author ) {
				if ( isset( $post_type->cap->delete_posts ) ) {
					$caps[] = $post_type->cap->delete_posts;
				}
			} elseif ( isset( $post_type->cap->delete_others_posts ) ) {
				$caps[] = $post_type->cap->delete_others_posts;
			}
		} elseif ( 'read_product' === $cap ) {
			if ( 'private' !== $post->post_status ) {
				$caps[] = 'read';
			} elseif ( $user_id === $post->post_author ) {
				$caps[] = 'read';
			} elseif ( isset( $post_type->cap->read_private_posts ) ) {
				$caps[] = $post_type->cap->read_private_posts;
			}
		}
	}

	return $caps;
}
