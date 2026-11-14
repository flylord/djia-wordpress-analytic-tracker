<?php
declare(strict_types=1);

namespace CiWoocommerce;

use CiWoocommerce\Admin\Admin;
use CiWoocommerce\Commands\SyncCatalogueCommand;
use CiWoocommerce\Core\System\Container;
use CiWoocommerce\Crons\FlushOutbox;
use CiWoocommerce\Hooks\CatalogueHooks;
use CiWoocommerce\Hooks\WooCommerceHooks;
use CiWoocommerce\Migrations\CreateOutboxTable;
use CiWoocommerce\Services\DatabaseMigrationService;
use CiWoocommerce\Services\PluginService;
use CiWoocommerce\Services\SiteCodeInstaller;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

final class MainCiWoocommerce {

	private string $plugin_file;

	public function __construct(string $plugin_file) {
		$this->plugin_file = $plugin_file;
	}

	public function init(): void {
		register_activation_hook($this->plugin_file, [$this, 'activate']);
		register_deactivation_hook($this->plugin_file, [$this, 'deactivate']);

		$this->run_migrations();
		$this->actions();
	}

	public function actions(): void {
		add_action('plugins_loaded', [$this, 'wp_init']);
		add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);

		if (is_admin()) {
			/* @var Admin $admin */
			$admin = Container::getInstance()->get(Admin::class);
			$admin->init();
		}

		$this->hooks();
		$this->crons();
		$this->commands();
	}

	public function declare_hpos_compatibility(): void {
		if (class_exists(FeaturesUtil::class)) {
			FeaturesUtil::declare_compatibility('custom_order_tables', $this->plugin_file, true);
		}
	}

	public function wp_init(): void {
		load_plugin_textdomain('ci-woocommerce', false, dirname(plugin_basename($this->plugin_file)) . '/languages');
	}

	private function run_migrations(): void {
		/* @var DatabaseMigrationService $migration_service */
		$migration_service = Container::getInstance()->get(DatabaseMigrationService::class);

		$migration_service->register_migration(CreateOutboxTable::ID, Container::getInstance()->get(CreateOutboxTable::class));
		$migration_service->check_and_execute();
	}

	private function hooks(): void {
		/* @var WooCommerceHooks $hooks */
		$hooks = Container::getInstance()->get(WooCommerceHooks::class);
		$hooks->init();

		/* @var CatalogueHooks $catalogue */
		$catalogue = Container::getInstance()->get(CatalogueHooks::class);
		$catalogue->init();
	}

	private function commands(): void {
		if (defined('WP_CLI') && WP_CLI) {
			\WP_CLI::add_command('ci catalogue-sync', Container::getInstance()->get(SyncCatalogueCommand::class));
		}
	}

	private function crons(): void {
		/* @var FlushOutbox $flushOutbox */
		$flushOutbox = Container::getInstance()->get(FlushOutbox::class);
		$flushOutbox->init();
	}

	public function activate(): void {
		Container::getInstance()->get(PluginService::class)->activate();

		/* @var FlushOutbox $flushOutbox */
		$flushOutbox = Container::getInstance()->get(FlushOutbox::class);
		$flushOutbox->schedule();

		Container::getInstance()->get(SiteCodeInstaller::class)->install();
	}

	public function deactivate(): void {
		Container::getInstance()->get(PluginService::class)->deactivate();

		/* @var FlushOutbox $flushOutbox */
		$flushOutbox = Container::getInstance()->get(FlushOutbox::class);
		$flushOutbox->unscheduleAll();

		Container::getInstance()->get(SiteCodeInstaller::class)->uninstall();
	}
}
