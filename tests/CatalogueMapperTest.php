<?php

declare(strict_types=1);

namespace CiWoocommerce\Tests;

use CiWoocommerce\Services\CatalogueMapper;
use CiWoocommerce\Services\CatalogueSource;
use PHPUnit\Framework\TestCase;

final class CatalogueMapperTest extends TestCase {

	public function testCatalogueMapperShape(): void {
		$mapper = new CatalogueMapper(new FakeCatalogueSource('Alati > Bušilice', 'Bosch'));

		$this->assertSame([
			'product_id' => '15',
			'url' => 'https://shop.test/p/15',
			'title' => 'Bušilica X',
			'category_path' => 'Alati > Bušilice',
			'price' => 129900,
			'currency' => 'RSD',
			'in_stock' => true,
			'sku' => 'BX-1',
			'brand' => 'Bosch',
		], $mapper->map(new StubProduct(15, 0, 'Bušilica X', '1299.00', 'BX-1', true)));
	}

	public function testVariationSendsParentAsProductAndOmitsEmptyOptionals(): void {
		$mapper = new CatalogueMapper(new FakeCatalogueSource('', null));

		$data = $mapper->map(new StubProduct(16, 15, 'Bušilica X Plava', '10', '', false));

		$this->assertSame('15', $data['product_id']);
		$this->assertSame('16', $data['variation_id']);
		$this->assertSame(1000, $data['price']);
		$this->assertFalse($data['in_stock']);
		$this->assertArrayNotHasKey('sku', $data);
		$this->assertArrayNotHasKey('brand', $data);
	}

	public function testProductWithoutPriceIsSkipped(): void {
		$mapper = new CatalogueMapper(new FakeCatalogueSource('', null));

		$this->assertNull($mapper->map(new StubProduct(17, 0, 'X', '', '', true)));
	}
}

final class FakeCatalogueSource implements CatalogueSource {
	private string $path;
	private ?string $brand;

	public function __construct(string $path, ?string $brand) {
		$this->path = $path;
		$this->brand = $brand;
	}

	public function permalink(int $productId): string {
		return 'https://shop.test/p/' . $productId;
	}

	public function categoryPath(int $productId): string {
		return $this->path;
	}

	public function brand(int $productId): ?string {
		return $this->brand;
	}

	public function currency(): string {
		return 'RSD';
	}
}

final class StubProduct {
	private int $id;
	private int $parentId;
	private string $name;
	private string $price;
	private string $sku;
	private bool $inStock;

	public function __construct(int $id, int $parentId, string $name, string $price, string $sku, bool $inStock) {
		$this->id = $id;
		$this->parentId = $parentId;
		$this->name = $name;
		$this->price = $price;
		$this->sku = $sku;
		$this->inStock = $inStock;
	}

	public function get_id(): int { return $this->id; }
	public function get_parent_id(): int { return $this->parentId; }
	public function get_name(): string { return $this->name; }
	public function get_price(): string { return $this->price; }
	public function get_sku(): string { return $this->sku; }
	public function is_in_stock(): bool { return $this->inStock; }
}
