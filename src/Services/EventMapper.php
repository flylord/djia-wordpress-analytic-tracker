<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

use CommerceIntelligence\Sdk\Event;

final class EventMapper {

	public function addToCart(int $productId, int $variationId, int $quantity, float $price, int $decimals, string $currency, ?string $anonymousId, ?string $userId): Event {
		return Event::create('product_added_to_cart', [
			'product_id' => $productId,
			'variation_id' => $variationId,
			'quantity' => $quantity,
			'unit_price' => $this->minorUnits($price, $decimals),
			'currency' => $currency,
		], $anonymousId, $userId);
	}

	public function removeFromCart(int $productId, int $variationId, int $quantity, ?string $anonymousId, ?string $userId): Event {
		return Event::create('product_removed_from_cart', [
			'product_id' => $productId,
			'variation_id' => $variationId,
			'quantity' => $quantity,
		], $anonymousId, $userId);
	}

	/**
	 * @param array<int, array{product_id: int, variation_id: int, quantity: int, unit_price: float}> $items
	 */
	public function orderCompleted(int $orderId, array $items, float $total, int $decimals, string $currency, ?string $anonymousId, ?string $userId): Event {
		$mapped = [];
		foreach ($items as $item) {
			$mapped[] = [
				'product_id' => (int) $item['product_id'],
				'variation_id' => (int) $item['variation_id'],
				'quantity' => (int) $item['quantity'],
				'unit_price' => $this->minorUnits((float) $item['unit_price'], $decimals),
			];
		}

		return Event::create('order_completed', [
			'order_id' => $orderId,
			'items' => $mapped,
			'revenue' => $this->minorUnits($total, $decimals),
			'currency' => $currency,
		], $anonymousId, $userId);
	}

	public function paymentConfirmed(int $orderId, float $amount, int $decimals, string $currency, string $method, ?string $anonymousId, ?string $userId): Event {
		return Event::create('payment_confirmed', [
			'order_id' => $orderId,
			'amount' => $this->minorUnits($amount, $decimals),
			'currency' => $currency,
			'method' => $method,
		], $anonymousId, $userId);
	}

	public function orderCancelled(int $orderId, string $reason, ?string $anonymousId, ?string $userId): Event {
		return Event::create('order_cancelled', [
			'order_id' => $orderId,
			'reason' => $reason,
		], $anonymousId, $userId);
	}

	/**
	 * @param array<int, array{product_id: int, variation_id: int, quantity: int, amount: float}> $items
	 */
	public function orderRefunded(int $orderId, int $refundId, float $amount, array $items, int $decimals, string $currency, ?string $anonymousId, ?string $userId): Event {
		$mapped = [];
		foreach ($items as $item) {
			$mapped[] = [
				'product_id' => (int) $item['product_id'],
				'variation_id' => (int) $item['variation_id'],
				'quantity' => (int) $item['quantity'],
				'amount' => $this->minorUnits((float) $item['amount'], $decimals),
			];
		}

		return Event::create('order_refunded', [
			'order_id' => $orderId,
			'refund_id' => $refundId,
			'amount' => $this->minorUnits($amount, $decimals),
			'currency' => $currency,
			'items' => $mapped,
		], $anonymousId, $userId);
	}

	public function userIdentified(string $userId, ?string $anonymousId): Event {
		return Event::create('user_identified', [], $anonymousId, $userId);
	}

	public function customerId(int $id): ?string {
		return $id > 0 ? (string) $id : null;
	}

	private function minorUnits(float $price, int $decimals): int {
		return (int) round($price * 10 ** $decimals);
	}
}
