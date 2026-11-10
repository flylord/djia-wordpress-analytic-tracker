<?php
declare(strict_types=1);

use CiWoocommerce\Services\PluginService;
use CiWoocommerce\Core\System\Container;

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

include_once __DIR__ . '/ci-woocommerce-config.php';

ci_woocommerce_config(__DIR__ . '/ci-woocommerce.php');

function ci_woocommerce_uninstall_site(): void {
	Container::getInstance()->get(PluginService::class)->uninstall();
}

if (is_multisite()) {
	$original_site_id = get_current_blog_id();

	foreach (get_sites(['fields' => 'ids']) as $site_id) {
		switch_to_blog((int)$site_id);
		ci_woocommerce_uninstall_site();
		restore_current_blog();
	}

	switch_to_blog($original_site_id);
} else {
	ci_woocommerce_uninstall_site();
}
