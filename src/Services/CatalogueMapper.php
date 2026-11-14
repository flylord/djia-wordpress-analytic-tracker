<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

use CommerceIntelligence\Sdk\Events;

final class CatalogueMapper {
	private CatalogueSource $source;

	public function __construct(CatalogueSource $source) {
		$this->source = $source;
	}

	/**
	 * @param \WC_Product $product
	 * @return array<string, mixed>|null null when the product has no price
	 */
	public function map($product): ?array {
		$price = (string) $product->get_price();
		if ($price === '') {
			return null;
		}

		$id = (int) $product->get_id();
		$parentId = (int) $product->get_parent_id();
		$catalogueId = $parentId > 0 ? $parentId : $id;
		$currency = $this->source->currency();
		$sku = (string) $product->get_sku();
		$brand = $this->source->brand($catalogueId);

		$data = [
			'product_id' => (string) $catalogueId,
			'url' => $this->source->permalink($id),
			'title' => (string) $product->get_name(),
			'category_path' => $this->source->categoryPath($catalogueId),
			'price' => Events::minorUnits($price, $currency),
			'currency' => $currency,
			'in_stock' => (bool) $product->is_in_stock(),
		];
		if ($parentId > 0) {
			$data['variation_id'] = (string) $id;
		}
		if ($sku !== '') {
			$data['sku'] = $sku;
		}
		if ($brand !== null && $brand !== '') {
			$data['brand'] = $brand;
		}

		return $data;
	}
}
