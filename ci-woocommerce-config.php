<?php

require __DIR__ . '/vendor/autoload.php';

use CiWoocommerce\Core\System\Container;

function ci_woocommerce_config(string $main_plugin_file): Container {
	$registry = [
		wpdb::class => static function (): wpdb {
			global $wpdb;
			return $wpdb;
		},
		'plugin_file' => static fn(): string => $main_plugin_file,
		'plugin_path' => static fn(): string => plugin_dir_path($main_plugin_file),
		'plugin_url' => static fn(): string => plugin_dir_url($main_plugin_file),
	];

	return Container::createInstance($registry);
}
