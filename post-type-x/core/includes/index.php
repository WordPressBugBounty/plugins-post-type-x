<?php
/**
 * Loads the plugin includes layer.
 *
 * The plugin includes folder is defined and managed here.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog/includes
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once AL_BASE_PATH . '/includes/tracking.php';
require_once AL_BASE_PATH . '/includes/util/index.php';
require_once AL_BASE_PATH . '/includes/class-ic-epc-defaults-sync-controller.php';
require_once AL_BASE_PATH . '/includes/class-ic-activation-wizard.php';
require_once AL_BASE_PATH . '/includes/activation-config.php';
require_once AL_BASE_PATH . '/includes/class-ic-catalog-hooks.php';
require_once AL_BASE_PATH . '/includes/settings/index.php';
require_once AL_BASE_PATH . '/includes/widgets/index.php';
require_once AL_BASE_PATH . '/includes/class-ic-register-product.php';
require_once AL_BASE_PATH . '/includes/class-ic-product.php';

require_once AL_BASE_PATH . '/includes/product-columns.php';
require_once AL_BASE_PATH . '/includes/product-category-columns.php';
require_once AL_BASE_PATH . '/includes/system.php';
require_once AL_BASE_PATH . '/includes/extensions.php';
require_once AL_BASE_PATH . '/includes/product-filter.php';
require_once AL_BASE_PATH . '/includes/product-filters.php';

require_once __DIR__ . '/class-ic-catalog-ajax.php';

global $ic_catalog_ajax;
$ic_catalog_ajax = new IC_Catalog_Ajax();

require_once AL_BASE_PATH . '/includes/class-ic-catalog-customizer.php';

require_once AL_BASE_PATH . '/includes/class-ic-epc-cron.php';

$ic_epc_cron = new IC_EPC_Cron();

require_once AL_BASE_PATH . '/includes/class-ic-featured-products.php';
require_once AL_BASE_PATH . '/includes/class-ic-sitewide-bar.php';
