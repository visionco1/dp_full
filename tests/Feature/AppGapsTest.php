<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Part;
use App\Models\ReturnRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** متطلبات فريق التطبيق (11 نوفمبر) */
class AppGapsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dp.otp.debug' => true]);
        $this->seed(DatabaseSeeder::class);
        $this->customer = User::where('phone', '966512345678')->firstOrFail();
    }

    // 1
    public function test_cart_switch_grade_and_side(): void
    {
        $oemR = Part::where('sku', '53811-06D90')->firstOrFail();   // رفرف يمين أصلي
        $h = ['X-Cart-Token' => $this->getJson('/api/v1/cart')->json('token')];
        $item = $this->postJson('/api/v1/cart/items', ['part_id' => $oemR->id, 'qty' => 1], $h)->json('items.0.id');

        $this->patchJson("/api/v1/cart/items/$item", ['grade' => 'first', 'side' => 'L'], $h)->assertOk()
            ->assertJsonPath('items.0.sku', 'FG-53812-L')->assertJsonPath('items.0.grade', 'first')->assertJsonPath('items.0.side', 'L');
        $this->patchJson("/api/v1/cart/items/$item", ['qty' => 2, 'grade' => 'aftermarket', 'side' => 'R'], $h)->assertOk()
            ->assertJsonPath('items.0.sku', 'FG-53811-R')->assertJsonPath('items.0.qty', 2);
        // بديل غير موجود (الدرجة التجارية يسار نفدت كميتها)
        $this->patchJson("/api/v1/cart/items/$item", ['grade' => 'cert', 'side' => 'L'], $h)->assertStatus(422);
    }

    // 2
    public function test_guest_lookup_requires_otp(): void
    {
        $code = $this->postJson('/api/v1/orders/guest-lookup', ['phone' => '0512345678'])->assertStatus(202)->json('debug_code');
        $this->postJson('/api/v1/orders/guest-lookup', ['phone' => '0512345678', 'code' => '0000'])->assertStatus(422);
        $code = $this->postJson('/api/v1/orders/guest-lookup', ['phone' => '0512345678'])->json('debug_code') ?? $code;
        $this->travel(61)->seconds();
        $code = $this->postJson('/api/v1/orders/guest-lookup', ['phone' => '0512345678'])->assertStatus(202)->json('debug_code');
        $res = $this->postJson('/api/v1/orders/guest-lookup', ['phone' => '0512345678', 'code' => $code])->assertOk();
        $this->assertGreaterThan(0, $res->json('count'));
        $this->assertContains('DM-261014-0472', array_column($res->json('orders'), 'number'));
    }

    // 3 + 6
    public function test_pages_and_returns_flow(): void
    {
        foreach (['terms', 'privacy', 'returns-policy'] as $slug) {
            $this->getJson("/api/v1/pages/$slug")->assertOk()->assertJsonStructure(['title', 'content']);
        }
        $this->getJson('/api/v1/pages/nope')->assertNotFound();

        Storage::fake('public');
        $o = Order::where('number', 'DM-260921-0187')->firstOrFail();   // مكتمل
        $o->events()->create(['status' => 'completed', 'note' => 'تم']);
        $item = $o->items()->first();
        $this->actingAs($this->customer, 'sanctum');

        $this->getJson("/api/v1/orders/{$o->number}/returns")->assertOk()->assertJsonPath('eligible.allowed', true);
        $this->post("/api/v1/orders/{$o->number}/returns", [
            'order_item_id' => $item->id, 'action' => 'exchange', 'reason' => 'القطعة غير مطابقة',
            'image' => UploadedFile::fake()->image('p.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('return.status', 'pending');
        $this->postJson("/api/v1/orders/{$o->number}/returns", ['order_item_id' => $item->id, 'action' => 'return', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('qty');

        // طلب غير مكتمل لا يقبل إرجاعاً
        $open = Order::where('number', 'DM-261014-0472')->firstOrFail();
        $this->postJson("/api/v1/orders/{$open->number}/returns", ['order_item_id' => $open->items()->value('id'), 'action' => 'return', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('order');

        // الإدارة: موافقة ثم إكمال
        $admin = User::where('email', 'admin@store.example')->firstOrFail();
        $ret = ReturnRequest::firstOrFail();
        $this->actingAs($admin)->get('/admin/returns')->assertOk()->assertSee('القطعة غير مطابقة');
        $this->actingAs($admin)->post("/admin/returns/{$ret->id}/approve")->assertRedirect();
        $this->actingAs($admin)->post("/admin/returns/{$ret->id}/complete")->assertRedirect();
        $this->assertSame('completed', $ret->fresh()->status);
    }

    // 4 + 7 + 5
    public function test_me_stats_devices_and_phone_change(): void
    {
        $t1 = $this->customer->createToken('phone-1')->plainTextToken;
        $other = $this->customer->createToken('tablet');

        $this->withToken($t1)->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('user.cars_count', 3)->assertJsonPath('user.addresses_count', 2)
            ->assertJsonStructure(['user' => ['created_at', 'orders_count']]);

        $this->withToken($t1)->deleteJson('/api/v1/me/devices/'.$other->accessToken->id)->assertOk()->assertJsonPath('current', false);
        $this->assertSame(1, $this->customer->tokens()->count());
        $this->withToken($t1)->deleteJson('/api/v1/me/devices/999999')->assertNotFound();

        $this->withToken($t1)->postJson('/api/v1/me/phone/change-request', ['new_phone' => '0512345678'])->assertStatus(422);
        $code = $this->withToken($t1)->postJson('/api/v1/me/phone/change-request', ['new_phone' => '0512341234'])->assertOk()->json('debug_code');
        $this->withToken($t1)->postJson('/api/v1/me/phone/change-verify', ['new_phone' => '0512341234', 'code' => $code])->assertOk();
        $this->assertSame('966512341234', $this->customer->fresh()->phone);
        // رمز تغيير الجوال لا يصلح لتسجيل الدخول برقم جديد
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '0512341234', 'code' => $code])->assertStatus(422);
    }

    // 8
    public function test_parts_side_filter(): void
    {
        $sides = array_unique(array_column($this->getJson('/api/v1/parts?side=R&per_page=50')->assertOk()->json('data'), 'side'));
        $this->assertSame(['R'], array_values($sides));
        $sides = array_unique(array_column($this->getJson('/api/v1/parts?side=l&per_page=50')->json('data'), 'side'));
        $this->assertSame(['L'], array_values($sides));
    }

    // 9 + 10 + 11
    public function test_login_type_check_cr_file_and_activity_alias(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '0512345678', 'type' => 'business'])->assertStatus(422)->assertJsonValidationErrors('type');
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '0512345678', 'type' => 'individual'])->assertOk();

        Storage::fake('local');
        $res = $this->post('/api/v1/auth/register', [
            'type' => 'business', 'phone' => '0550000009', 'name' => 'مدير', 'city' => 'جدة', 'terms' => '1',
            'business_name' => 'مركز النخبة', 'activity' => 'service_center', 'cr_number' => '1010123456', 'vat_number' => '300000000000003',
            'cr_file' => UploadedFile::fake()->create('cr.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();
        $user = $this->postJson('/api/v1/auth/otp/verify', ['phone' => '0550000009', 'code' => $res->json('debug_code')])->assertOk()->json('user');
        $this->assertSame('center', $user['business']['activity']);
        $this->assertTrue($user['business']['cr_document_uploaded']);
        Storage::disk('local')->assertExists(User::where('phone', '966550000009')->value('cr_document'));

        $this->getJson('/api/v1/lookups')->assertOk()->assertJsonFragment(['code' => 'center', 'label_ar' => 'مركز صيانة']);
    }

    // 12
    public function test_bank_accounts(): void
    {
        BankAccount::query()->delete();
        $this->getJson('/api/v1/bank-accounts')->assertOk()->assertJsonPath('iban', null);
        BankAccount::create(['bank_name' => 'مصرف الإنماء', 'beneficiary_name' => 'شركة دفع المحركات', 'iban' => 'SA0380000000608010167519', 'account_number' => '12345678']);
        $this->getJson('/api/v1/bank-accounts')->assertOk()->assertJsonPath('bank_name', 'مصرف الإنماء')->assertJsonCount(1, 'accounts');

        $admin = User::where('email', 'admin@store.example')->firstOrFail();
        $this->actingAs($admin)->post('/admin/content/accounts', ['bank_name' => 'x', 'beneficiary_name' => 'y', 'iban' => '123'])->assertSessionHasErrors('iban');
        $this->actingAs($admin)->get('/admin/content')->assertOk()->assertSee('مصرف الإنماء');
    }
}
