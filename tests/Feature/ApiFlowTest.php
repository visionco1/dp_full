<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Part;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dp.otp.debug' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    private function login(string $phone = '512345699'): string
    {
        // التسجيل يرسل الرمز مباشرة (طلب رمز ثانٍ فوراً يُرفض بمهلة إعادة الإرسال)
        $code = $this->postJson('/api/v1/auth/register', ['type' => 'individual', 'name' => 'اختبار', 'city' => 'الرياض', 'phone' => $phone, 'country' => 'SA', 'terms' => true])->assertSuccessful()->json('debug_code');
        $this->assertNotEmpty($code);
        return $this->postJson('/api/v1/auth/otp/verify', ['phone' => $phone, 'country' => 'SA', 'code' => $code])->assertOk()->json('token');
    }

    public function test_otp_login_and_me(): void
    {
        $t = $this->login();
        $this->withToken($t)->getJson('/api/v1/me')->assertOk();
    }

    public function test_wrong_otp_rejected(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '512345670', 'country' => 'SA']);
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '512345670', 'country' => 'SA', 'code' => '0000'])->assertStatus(422);
    }

    public function test_guest_checkout_reserves_stock_and_cancel_releases(): void
    {
        $part = Part::whereHas('inventory', fn ($q) => $q->where('on_hand', '>', 3))->first();
        $before = $part->inventory->fresh()->reserved;
        $cart = $this->getJson('/api/v1/cart')->json('token');
        $h = ['X-Cart-Token' => $cart];
        $this->postJson('/api/v1/cart/items', ['part_id' => $part->id, 'qty' => 2], $h)->assertSuccessful();
        $res = $this->postJson('/api/v1/checkout', ['payment_method' => 'cod', 'address' => ['name' => 'ضيف', 'phone' => '512345600', 'city' => 'جدة', 'line' => 'حي الروضة']], $h)->assertSuccessful();
        $this->assertSame($before + 2, $part->inventory->fresh()->reserved);
        $order = Order::latest('id')->first();
        $this->postJson("/api/v1/orders/{$order->number}/cancel", ['phone' => '966512345600'])->assertSuccessful();
        $this->assertSame($before, $part->inventory->fresh()->reserved);
    }

    public function test_track_requires_matching_phone(): void
    {
        $o = Order::first();
        $this->postJson('/api/v1/orders/track', ['number' => $o->number, 'phone' => '966500000000'])->assertStatus(404);
        $this->postJson('/api/v1/orders/track', ['number' => $o->number, 'phone' => $o->customer_phone])->assertOk();
    }

    public function test_orders_require_auth(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }
}
