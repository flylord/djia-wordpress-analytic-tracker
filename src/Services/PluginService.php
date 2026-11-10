<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

use CiWoocommerce\Repositories\OutboxRepository;
use wpdb;

final class PluginService {

	private wpdb $wpdb;

	public function __construct(wpdb $wpdb) {
		$this->wpdb = $wpdb;
	}

	public function activate(): void {
	}

	public function deactivate(): void {
	}

	public function uninstall(): void {
		$this->drop_table(OutboxRepository::TABLENAME);
		$this->delete_options('ci_woocommerce_%');
	}

	private function drop_table(string $tablename): void {
		$this->wpdb->query('DROP TABLE IF EXISTS ' . $this->wpdb->prefix . $tablename);
	}

	private function delete_options(string $pattern): void {
		$this->wpdb->query($this->wpdb->prepare(
			"DELETE FROM {$this->wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$pattern,
			'_transient_' . $pattern,
			'_transient_timeout_' . $pattern
		));
	}
}
