<?php

declare(strict_types=1);

namespace CiWoocommerce\Tests;

use CiWoocommerce\Services\SettingsService;
use CiWoocommerce\Services\SiteCodeInstaller;
use PHPUnit\Framework\TestCase;

final class SiteCodeInstallerTest extends TestCase {

	public function testSnippetContainsKeyAndAdapter(): void {
		$snippet = (new SiteCodeInstaller(new SettingsService()))->snippet('pk_alteo_local', 'http://localhost:11404/t.v1.js');

		$this->assertStringContainsString('window.djia_ci = window.djia_ci || function () { (window.djia_ci.q = window.djia_ci.q || []).push(arguments); };', $snippet);
		$this->assertStringContainsString("djia_ci('init', \"pk_alteo_local\", {adapter: 'djia-builder'});", $snippet);
		$this->assertStringContainsString('<script async src="http://localhost:11404/t.v1.js"></script>', $snippet);
	}

	public function testSnippetEscapesKey(): void {
		$snippet = (new SiteCodeInstaller(new SettingsService()))->snippet('pk"</script>', 'http://x/t.js?a=1&b=2');

		$this->assertStringNotContainsString('pk"</script>', $snippet);
		$this->assertStringContainsString('src="http://x/t.js?a=1&amp;b=2"', $snippet);
	}

	public function testInstallReturnsFalseWithoutDjiaBuilder(): void {
		$this->assertFalse((new SiteCodeInstaller(new SettingsService()))->install());
	}
}
