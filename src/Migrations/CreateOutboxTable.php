<?php
declare(strict_types=1);

namespace CiWoocommerce\Migrations;

use CiWoocommerce\Repositories\OutboxRepository;
use RuntimeException;
use wpdb;

final class CreateOutboxTable {
	public const ID = 'create_outbox_table';

	private const COLUMNS = [
		'event_id' => 'CHAR(36) NOT NULL',
		'payload' => 'LONGTEXT NOT NULL',
		'created_at' => 'DATETIME NOT NULL',
	];

	private wpdb $wpdb;

	public function __construct(wpdb $wpdb) {
		$this->wpdb = $wpdb;
	}

	public function __invoke(): void {
		$tablename = $this->wpdb->prefix . OutboxRepository::TABLENAME;

		if (!$this->table_exists($tablename)) {
			$this->create($tablename);

			return;
		}

		$this->add_missing_columns($tablename);
	}

	private function create(string $tablename): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();

		dbDelta("CREATE TABLE {$tablename} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id CHAR(36) NOT NULL,
			payload LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id)
		) {$charset_collate};");

		if (!$this->table_exists($tablename)) {
			throw new RuntimeException(
				$this->wpdb->last_error !== ''
					? $this->wpdb->last_error
					: __('Table creation failed.', 'ci-woocommerce')
			);
		}
	}

	private function add_missing_columns(string $tablename): void {
		$columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$tablename}");
		$existing = array_column(is_array($columns) ? $columns : [], 'Field');
		$previous = 'id';

		foreach (self::COLUMNS as $column => $definition) {
			if (!in_array($column, $existing, true)) {
				$this->wpdb->query("ALTER TABLE {$tablename} ADD COLUMN {$column} {$definition} AFTER {$previous}");
			}

			$previous = $column;
		}
	}

	private function table_exists(string $tablename): bool {
		$found = $this->wpdb->get_var(
			$this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($tablename))
		);

		return $found !== null;
	}
}
