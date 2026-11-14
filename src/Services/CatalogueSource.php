<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

interface CatalogueSource {
	public function permalink(int $productId): string;

	public function categoryPath(int $productId): string;

	public function brand(int $productId): ?string;

	public function currency(): string;
}
