<?php
declare(strict_types=1);

namespace CiWoocommerce\Admin;

use CiWoocommerce\Services\SettingsService;
use CiWoocommerce\Services\SiteCodeInstaller;
use CiWoocommerce\Templates\SettingsTemplate;

final class SettingsPage {
	public const SLUG = 'ci_woocommerce_settings';
	public const ACTION = 'ci_woocommerce_save_settings';

	private SettingsService $settings;
	private SiteCodeInstaller $installer;

	public function __construct(SettingsService $settings, SiteCodeInstaller $installer) {
		$this->settings = $settings;
		$this->installer = $installer;
	}

	public function init(): void {
		add_action('admin_menu', [$this, 'addMenu']);
		add_action('admin_post_' . self::ACTION, [$this, 'save']);
	}

	public function addMenu(): void {
		add_options_page(
			__('Commerce Intelligence', 'ci-woocommerce'),
			__('Commerce Intelligence', 'ci-woocommerce'),
			'manage_options',
			self::SLUG,
			[$this, 'render']
		);
	}

	public function render(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$values = $this->settings->get();
		$error = $this->settings->lastError();

		SettingsTemplate::render([
			'action' => self::ACTION,
			'endpoint' => $values['endpoint'],
			'keyId' => $values['key_id'],
			'secretIsSet' => $values['secret'] !== '',
			'publicKey' => $values['public_key'],
			'trackerUrl' => $values['tracker_url'],
			'snippet' => $this->installer->isInstalled() ? null : $this->installer->configuredSnippet(),
			'lastError' => $error === null ? null : [
				'message' => $error['message'],
				'time' => $error['time']->format('Y-m-d H:i:s') . ' UTC',
			],
			'saved' => isset($_GET['updated']) && sanitize_text_field(wp_unslash($_GET['updated'])) === '1',
		]);
	}

	public function save(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to do this.', 'ci-woocommerce'), 403);
		}
		check_admin_referer(self::ACTION, 'security');

		$endpoint = isset($_POST['endpoint']) ? esc_url_raw(sanitize_text_field(wp_unslash($_POST['endpoint']))) : '';
		$keyId = isset($_POST['key_id']) ? sanitize_text_field(wp_unslash($_POST['key_id'])) : '';
		$secret = isset($_POST['secret']) ? trim(sanitize_text_field(wp_unslash($_POST['secret']))) : '';

		$publicKey = isset($_POST['public_key']) ? sanitize_text_field(wp_unslash($_POST['public_key'])) : '';
		$trackerUrl = isset($_POST['tracker_url']) ? esc_url_raw(sanitize_text_field(wp_unslash($_POST['tracker_url']))) : '';

		$this->settings->save($endpoint, $keyId, $secret, $publicKey, $trackerUrl);
		$this->installer->install();

		wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'updated' => '1'], admin_url('options-general.php')));
		exit;
	}
}
