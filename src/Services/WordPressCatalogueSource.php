<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

final class WordPressCatalogueSource implements CatalogueSource {
	public const BRAND_TAXONOMY = 'product_brand';

	public function permalink(int $productId): string {
		$url = get_permalink($productId);

		return is_string($url) ? $url : '';
	}

	public function categoryPath(int $productId): string {
		$terms = get_the_terms($productId, 'product_cat');
		if (!is_array($terms) || $terms === []) {
			return '';
		}

		$deepest = [];
		foreach ($terms as $term) {
			$chain = [$term->name];
			$parent = (int) $term->parent;
			while ($parent > 0) {
				$ancestor = get_term($parent, 'product_cat');
				if (!$ancestor || is_wp_error($ancestor)) {
					break;
				}
				array_unshift($chain, $ancestor->name);
				$parent = (int) $ancestor->parent;
			}
			if (count($chain) > count($deepest)) {
				$deepest = $chain;
			}
		}

		return html_entity_decode(implode(' > ', $deepest), ENT_QUOTES, 'UTF-8');
	}

	public function brand(int $productId): ?string {
		if (!taxonomy_exists(self::BRAND_TAXONOMY)) {
			return null;
		}

		$terms = get_the_terms($productId, self::BRAND_TAXONOMY);

		return is_array($terms) && $terms !== [] ? html_entity_decode($terms[0]->name, ENT_QUOTES, 'UTF-8') : null;
	}

	public function currency(): string {
		return get_woocommerce_currency();
	}
}
