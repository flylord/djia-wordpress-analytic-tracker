<?php

declare(strict_types=1);

namespace CiWoocommerce\Tests;

use CiWoocommerce\Services\EventMapper;
use CommerceIntelligence\Sdk\Context;
use PHPUnit\Framework\TestCase;

final class EventMapperTest extends TestCase {

	private EventMapper $mapper;

	protected function setUp(): void {
		$this->mapper = new EventMapper();
	}

	public function testCartStepBuildsCartViewedAndCheckoutStarted(): void {
		$items = [['product_id' => 10, 'variation_id' => 0, 'quantity' => 2, 'unit_price' => '125.000000'], ['product_id' => 11, 'variation_id' => 12, 'quantity' => 1, 'unit_price' => '9.5']];
		$ctx = new Context('aid-1', 'sid-1', '42', null);
		$cart = $this->mapper->cartStep(false, $items, '259.50', 'RSD', $ctx)->toArray();
		$checkout = $this->mapper->cartStep(true, $items, '259.50', 'RSD', $ctx)->toArray();

		$this->assertSame('cart_viewed', $cart['event']);
		$this->assertSame('checkout_started', $checkout['event']);
		$this->assertSame('sid-1', $cart['session_id']);
		$this->assertSame('42', $cart['user_id']);
		$this->assertSame([
			'items' => [
				['product_id' => '10', 'quantity' => 2, 'unit_price' => 12500],
				['product_id' => '11', 'variation_id' => '12', 'quantity' => 1, 'unit_price' => 950],
			],
			'total' => 25950,
			'currency' => 'RSD',
		], $cart['properties']);
	}

	public function testAddToCartOmitsZeroVariationAndSendsStringIds(): void {
		$data = $this->mapper->addToCart(10, 0, 2, '125.00', 'EUR', 'aid-1', '42', 'sid-1')->toArray();

		$this->assertSame('product_added_to_cart', $data['event']);
		$this->assertSame('aid-1', $data['anonymous_id']);
		$this->assertSame('42', $data['user_id']);
		$this->assertSame('sid-1', $data['session_id']);
		$this->assertSame(
			['product_id' => '10', 'quantity' => 2, 'unit_price' => 12500, 'currency' => 'EUR'],
			$data['properties']
		);
	}

	public function testAddToCartKeepsVariationId(): void {
		$data = $this->mapper->addToCart(10, 11, 1, '5.00', 'EUR', null, null)->toArray();

		$this->assertSame('11', $data['properties']['variation_id']);
		$this->assertArrayNotHasKey('session_id', $data);
		$this->assertArrayNotHasKey('anonymous_id', $data);
		$this->assertArrayNotHasKey('user_id', $data);
	}

	public function testMoneyIsExactFromDecimalString(): void {
		$data = $this->mapper->addToCart(1, 0, 1, '19.99', 'USD', null, null)->toArray();

		$this->assertSame(1999, $data['properties']['unit_price']);
	}

	public function testEmptyPriceIsZero(): void {
		$data = $this->mapper->addToCart(1, 0, 1, '', 'EUR', null, null)->toArray();

		$this->assertSame(0, $data['properties']['unit_price']);
	}

	public function testMoneyUsesIsoExponentNotShopDecimals(): void {
		$jpy = $this->mapper->addToCart(1, 0, 1, '1500', 'JPY', null, null)->toArray();
		$kwd = $this->mapper->addToCart(1, 0, 1, '1.234', 'KWD', null, null)->toArray();
		$eur = $this->mapper->addToCart(1, 0, 1, '12.5', 'EUR', null, null)->toArray();

		$this->assertSame(1500, $jpy['properties']['unit_price']);
		$this->assertSame(1234, $kwd['properties']['unit_price']);
		$this->assertSame(1250, $eur['properties']['unit_price']);
	}

	public function testRemoveFromCart(): void {
		$data = $this->mapper->removeFromCart(10, 11, 3, null, null)->toArray();

		$this->assertSame('product_removed_from_cart', $data['event']);
		$this->assertSame(['product_id' => '10', 'variation_id' => '11', 'quantity' => 3], $data['properties']);
	}

	public function testRemoveFromCartOmitsZeroVariation(): void {
		$data = $this->mapper->removeFromCart(10, 0, 3, null, null)->toArray();

		$this->assertSame(['product_id' => '10', 'quantity' => 3], $data['properties']);
	}

	public function testOrderCompletedItemsAndRevenue(): void {
		$items = [
			['product_id' => 10, 'variation_id' => 0, 'quantity' => 2, 'unit_price' => '12.50'],
			['product_id' => 20, 'variation_id' => 21, 'quantity' => 1, 'unit_price' => '99.99'],
		];

		$data = $this->mapper->orderCompleted(7, $items, '124.99', 'EUR', 'aid-9', '5')->toArray();

		$this->assertSame('order_completed', $data['event']);
		$this->assertSame('aid-9', $data['anonymous_id']);
		$this->assertSame('5', $data['user_id']);
		$this->assertSame('7', $data['properties']['order_id']);
		$this->assertSame(12499, $data['properties']['revenue']);
		$this->assertSame('EUR', $data['properties']['currency']);
		$this->assertSame(
			[
				['product_id' => '10', 'quantity' => 2, 'unit_price' => 1250],
				['product_id' => '20', 'variation_id' => '21', 'quantity' => 1, 'unit_price' => 9999],
			],
			$data['properties']['items']
		);
	}

	public function testOrderCompletedCarriesSessionId(): void {
		$data = $this->mapper->orderCompleted(7, [], '10.00', 'EUR', 'aid-9', null, 'sid-9')->toArray();

		$this->assertSame('sid-9', $data['session_id']);
	}

	public function testPaymentConfirmedCarriesSessionId(): void {
		$data = $this->mapper->paymentConfirmed(7, '50.00', 'EUR', 'stripe', 'aid-9', null, 'sid-9')->toArray();

		$this->assertSame('payment_confirmed', $data['event']);
		$this->assertSame('sid-9', $data['session_id']);
		$this->assertSame(
			['order_id' => '7', 'amount' => 5000, 'currency' => 'EUR', 'method' => 'stripe'],
			$data['properties']
		);
	}

	public function testOrderCancelledOmitsEmptyReason(): void {
		$data = $this->mapper->orderCancelled(7, 'merchant_cancelled', '', 'aid-9', null)->toArray();

		$this->assertSame('order_cancelled', $data['event']);
		$this->assertSame(['order_id' => '7', 'status' => 'merchant_cancelled'], $data['properties']);
	}

	public function testOrderCancelledKeepsReason(): void {
		$data = $this->mapper->orderCancelled(7, 'customer_cancelled', 'Changed my mind', null, null)->toArray();

		$this->assertSame('Changed my mind', $data['properties']['reason']);
	}

	/**
	 * @dataProvider cancellationStatuses
	 */
	public function testCancellationStatus(string $from, string $note, string $expected): void {
		$this->assertSame($expected, $this->mapper->cancellationStatus($from, $note));
	}

	public function cancellationStatuses(): array {
		return [
			'customer' => ['pending', 'Order cancelled by customer.', 'customer_cancelled'],
			'failed payment' => ['failed', '', 'payment_failed'],
			'timeout cron' => ['pending', 'Unpaid order cancelled - time limit reached.', 'payment_timeout'],
			'admin' => ['processing', '', 'merchant_cancelled'],
		];
	}

	public function testRefund(): void {
		$items = [['product_id' => 10, 'variation_id' => 0, 'quantity' => 1, 'amount' => '12.50']];

		$data = $this->mapper->orderRefunded(7, 8, '12.50', $items, 'EUR', 'aid-9', '5')->toArray();

		$this->assertSame('order_refunded', $data['event']);
		$this->assertSame(
			[
				'order_id' => '7',
				'refund_id' => '8',
				'amount' => 1250,
				'currency' => 'EUR',
				'items' => [['product_id' => '10', 'quantity' => 1, 'amount' => 1250]],
			],
			$data['properties']
		);
	}

	public function testRefundWithoutItemsOmitsItems(): void {
		$data = $this->mapper->orderRefunded(7, 8, '12.50', [], 'EUR', null, null)->toArray();

		$this->assertArrayNotHasKey('items', $data['properties']);
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
