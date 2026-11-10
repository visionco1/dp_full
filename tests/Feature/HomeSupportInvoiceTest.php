<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSupportInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_home_for_guest(): void
    {
        $this->getJson('/api/v1/home')->assertOk()
            ->assertJsonStructure([
                'delivery_address', 'notification_count', 'active_vehicle', 'search_suggestions',
                'banners' => [['id', 'title', 'image_url', 'action_type', 'action_id']],
                'categories' => [['id', 'name_ar', 'slug', 'image']],
                'suggested_products' => [['id', 'sku', 'name', 'brand', 'price', 'image']],
            ])
            ->assertJsonPath('notification_count', 0)
            ->assertJsonPath('delivery_address', null);
    }

    public function test_home_for_logged_in_user_uses_address(): void
    {
        $u = User::where('phone', '966512345678')->firstOrFail();
        $res = $this->actingAs($u, 'sanctum')->getJson('/api/v1/home')->assertOk();
        $this->assertSame('الرياض، حي النرجس', $res->json('delivery_address'));
    }

    public function test_faqs(): void
    {
        $this->getJson('/api/v1/support/faqs')->assertOk()
            ->assertJsonStructure(['faqs' => [['id', 'question', 'answer']], 'contact']);
    }

    public function test_invoice_json_with_valid_zatca_qr(): void
    {
        $o = Order::whereNotNull('user_id')->with('items')->firstOrFail();
        $res = $this->getJson("/api/v1/orders/{$o->number}/invoice?phone={$o->customer_phone}")->assertOk()
            ->assertJsonPath('invoice.order_number', $o->number)
            ->assertJsonStructure(['invoice' => ['invoice_number', 'issue_date', 'seller_tax_number', 'items' => [['name', 'part_number', 'quantity', 'unit_price', 'total_price']], 'subtotal_before_vat', 'vat_amount', 'grand_total', 'qr_code_data']]);

        $inv = $res->json('invoice');
        $this->assertEqualsWithDelta($inv['grand_total'], $inv['subtotal_before_vat'] + $inv['vat_amount'], 0.01);

        // فك TLV والتأكد من الحقول الخمسة
        $raw = base64_decode($inv['qr_code_data'], true);
        $tags = [];
        for ($i = 0; $i < strlen($raw);) {
            $tag = ord($raw[$i]);
            $len = ord($raw[$i + 1]);
            $tags[$tag] = substr($raw, $i + 2, $len);
            $i += 2 + $len;
        }
        $this->assertSame([1, 2, 3, 4, 5], array_keys($tags));
        $this->assertSame(config('dp.seller_name'), $tags[1]);
        $this->assertSame(number_format($o->total, 2, '.', ''), $tags[4]);
    }

    public function test_invoice_html_still_available(): void
    {
        $o = Order::whereNotNull('user_id')->firstOrFail();
        $this->get("/api/v1/orders/{$o->number}/invoice?phone={$o->customer_phone}")->assertOk()->assertSee('فاتورة ضريبية');
        $this->getJson("/api/v1/orders/{$o->number}/invoice?format=html&phone={$o->customer_phone}")->assertOk()->assertHeader('content-type', 'text/html; charset=utf-8');
    }

    public function test_invoice_requires_ownership(): void
    {
        $o = Order::firstOrFail();
        $this->getJson("/api/v1/orders/{$o->number}/invoice?phone=966500000000")->assertNotFound();
    }
}
