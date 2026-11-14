<?php
declare(strict_types=1);

/**
 * Commerce Intelligence for WooCommerce
 *
 * @wordpress-plugin
 * Plugin Name:  Commerce Intelligence for WooCommerce
 * Plugin URI:   https://biznis.co.rs/pluginovi/
 * Author URI:   https://biznis.co.rs/
 * Description:  Sends WooCommerce server events to the Commerce Intelligence collector.
 * Author:       Aleksandar Ilic & Djordje Nikolic
 * Version:      1.0.0
 * License:      GPLv2 or later
 * License URI:  http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Text Domain:  ci-woocommerce
 * Domain Path:  /languages
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * WC requires at least: 7.0
 */

if (!defined('ABSPATH')) {
	return;
}

const CI_WOOCOMMERCE_VERSION = '1.0.0';
define('CI_WOOCOMMERCE_DEVELOP', false); //have to be with define because of deploy script

include_once __DIR__ . '/ci-woocommerce-config.php';

use CiWoocommerce\MainCiWoocommerce;

$container = ci_woocommerce_config(__FILE__);

$main = $container->get(MainCiWoocommerce::class);

$main->init();
