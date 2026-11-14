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

	public function testGlobalsEqual(): void {
		$globals = ['revision' => 3, 'tokens' => ['color' => ['a' => '#fff']], 'customCode' => ['scripts' => [['id' => 'ga', 'html' => 'x']]]];

		$this->assertTrue(SiteCodeInstaller::equalApartFromItem($globals, ['revision' => 2] + $globals, 'ci-tracker'));
	}

	public function testGlobalsEqualApartFromTrackerItem(): void {
		$published = ['revision' => 2, 'customCode' => (object) []];
		$draft = ['revision' => 3, 'customCode' => ['scripts' => [['id' => 'ci-tracker', 'html' => 'old']]]];

		$this->assertTrue(SiteCodeInstaller::equalApartFromItem($draft, $published, 'ci-tracker'));
	}

	public function testGlobalsDifferent(): void {
		$published = ['revision' => 2, 'tokens' => ['color' => ['a' => '#fff']]];
		$draft = ['revision' => 3, 'tokens' => ['color' => ['a' => '#000']], 'customCode' => ['scripts' => [['id' => 'ci-tracker', 'html' => 'x']]]];

		$this->assertFalse(SiteCodeInstaller::equalApartFromItem($draft, $published, 'ci-tracker'));
	}
}
