<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

use Throwable;

class DatabaseMigrationService {

	private array $migrations = [];
	private array $admin_notices = [];

	public function register_migration(string $migration_id, callable $migration): void {
		$this->migrations[$migration_id] = $migration;
	}

	public function check_and_execute(): void {
		$executed_migrations = $this->get_executed_migrations();
		$migrations_executed = false;

		foreach ($this->migrations as $migration_id => $migration) {
			if (in_array($migration_id, $executed_migrations, true)) {
				continue;
			}

			$success = $this->execute_migration($migration, $migration_id);
			if (!$success) {
				return;
			}

			$this->mark_migration_executed($migration_id);
			$migrations_executed = true;
		}

		if ($migrations_executed) {
			$this->queue_admin_notice('Database migrations completed successfully', 'success');
			add_action('admin_notices', [$this, 'display_admin_notices']);
		}
	}

	private function get_executed_migrations(): array {
		$executed = get_option('ci_woocommerce_executed_migrations', []);
		return is_array($executed) ? $executed : [];
	}

	private function mark_migration_executed(string $migration_id): void {
		$executed = $this->get_executed_migrations();
		$executed[] = $migration_id;
		update_option('ci_woocommerce_executed_migrations', $executed);
	}

	private function execute_migration(callable $migration, string $migration_id): bool {
		try {
			call_user_func($migration);
			return true;
		} catch (Throwable $e) {
			$this->queue_admin_notice("Migration '{$migration_id}' failed: " . $e->getMessage(), 'error');
			add_action('admin_notices', [$this, 'display_admin_notices']);
			return false;
		}
	}

	private function queue_admin_notice(string $message, string $type): void {
		$this->admin_notices[] = ['message' => $message, 'type' => $type];
	}

	public function display_admin_notices(): void {
		if (!current_user_can('manage_options')) {
			return;
		}
		foreach ($this->admin_notices as $notice) {
			$class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';
			printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($notice['message']));
		}
		$this->admin_notices = [];
	}
}
