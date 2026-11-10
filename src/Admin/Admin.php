<?php
declare(strict_types=1);

namespace CiWoocommerce\Admin;

final class Admin {
	private SettingsPage $settingsPage;

	public function __construct(SettingsPage $settingsPage) {
		$this->settingsPage = $settingsPage;
	}

	public function init(): void {
		$this->settingsPage->init();
	}
}
