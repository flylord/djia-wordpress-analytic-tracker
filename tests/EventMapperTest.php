<?php

declare(strict_types=1);

namespace CiWoocommerce\Tests;

use CiWoocommerce\Services\EventMapper;
use PHPUnit\Framework\TestCase;

final class EventMapperTest extends TestCase {

	private EventMapper $mapper;

	protected function setUp(): void {
		$this->mapper = new EventMapper();
	}

	public function testAddToCart(): void {
		$data = $this->mapper->addToCart(10, 0, 2, 125.0, 2, 'EUR', 'aid-1', '42')->toArray();

		$this->assertSame('product_added_to_cart', $data['event']);
		$this->assertSame('aid-1', $data['anonymous_id']);
		$this->assertSame('42', $data['user_id']);
		$this->assertSame(
			['product_id' => 10, 'variation_id' => 0, 'quantity' => 2, 'unit_price' => 12500, 'currency' => 'EUR'],
			$data['properties']
		);
	}

	public function testAddToCartRoundsFloatNoise(): void {
		$data = $this->mapper->addToCart(1, 0, 1, 19.99, 2, 'USD', null, null)->toArray();

		$this->assertSame(1999, $data['properties']['unit_price']);
		$this->assertArrayNotHasKey('anonymous_id', $data);
		$this->assertArrayNotHasKey('user_id', $data);
	}

	public function testRemoveFromCart(): void {
		$data = $this->mapper->removeFromCart(10, 11, 3, null, null)->toArray();

		$this->assertSame('product_removed_from_cart', $data['event']);
		$this->assertSame(['product_id' => 10, 'variation_id' => 11, 'quantity' => 3], $data['properties']);
	}

	public function testOrderCompletedItemsAndRevenue(): void {
		$items = [
			['product_id' => 10, 'variation_id' => 0, 'quantity' => 2, 'unit_price' => 12.5],
			['product_id' => 20, 'variation_id' => 21, 'quantity' => 1, 'unit_price' => 99.99],
		];

		$data = $this->mapper->orderCompleted(7, $items, 124.99, 2, 'EUR', 'aid-9', '5')->toArray();

		$this->assertSame('order_completed', $data['event']);
		$this->assertSame('aid-9', $data['anonymous_id']);
		$this->assertSame('5', $data['user_id']);
		$this->assertSame(7, $data['properties']['order_id']);
		$this->assertSame(12499, $data['properties']['revenue']);
		$this->assertSame('EUR', $data['properties']['currency']);
		$this->assertSame(
			[
				['product_id' => 10, 'variation_id' => 0, 'quantity' => 2, 'unit_price' => 1250],
				['product_id' => 20, 'variation_id' => 21, 'quantity' => 1, 'unit_price' => 9999],
			],
			$data['properties']['items']
		);
	}

	public function testPaymentConfirmed(): void {
		$data = $this->mapper->paymentConfirmed(7, 50.0, 2, 'EUR', 'stripe', 'aid-9', null)->toArray();

		$this->assertSame('payment_confirmed', $data['event']);
		$this->assertSame(
			['order_id' => 7, 'amount' => 5000, 'currency' => 'EUR', 'method' => 'stripe'],
			$data['properties']
		);
	}

	public function testOrderCancelledDefaultsToEmptyReason(): void {
		$data = $this->mapper->orderCancelled(7, '', 'aid-9', null)->toArray();

		$this->assertSame('order_cancelled', $data['event']);
		$this->assertSame(['order_id' => 7, 'reason' => ''], $data['properties']);
	}

	public function testRefund(): void {
		$items = [['product_id' => 10, 'variation_id' => 0, 'quantity' => 1, 'amount' => 12.5]];

		$data = $this->mapper->orderRefunded(7, 8, 12.5, $items, 2, 'EUR', 'aid-9', '5')->toArray();

		$this->assertSame('order_refunded', $data['event']);
		$this->assertSame(
			[
				'order_id' => 7,
				'refund_id' => 8,
				'amount' => 1250,
				'currency' => 'EUR',
				'items' => [['product_id' => 10, 'variation_id' => 0, 'quantity' => 1, 'amount' => 1250]],
			],
			$data['properties']
		);
	}

	public function testZeroDecimalCurrency(): void {
		$data = $this->mapper->addToCart(1, 0, 1, 1500.0, 0, 'JPY', null, null)->toArray();

		$this->assertSame(1500, $data['properties']['unit_price']);
	}

	public function testUserIdentified(): void {
		$data = $this->mapper->userIdentified('42', 'aid-1')->toArray();

		$this->assertSame('user_identified', $data['event']);
		$this->assertSame('42', $data['user_id']);
		$this->assertSame('aid-1', $data['anonymous_id']);
		$this->assertEquals(new \stdClass(), $data['properties']);
	}

	public function testCustomerIdIsNullWhenZero(): void {
		$this->assertNull($this->mapper->customerId(0));
		$this->assertSame('42', $this->mapper->customerId(42));
	}
}
