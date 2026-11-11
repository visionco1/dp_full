<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get('/admin/orders')->assertRedirect(route('login'));
    }

    public function test_admin_sees_dashboard_and_roles(): void
    {
        $a = User::where('email', 'admin@store.example')->first();
        $this->actingAs($a)->get('/admin/dashboard')->assertOk();
        $this->actingAs($a)->get('/admin/roles')->assertOk();
        $this->actingAs($a)->get('/admin/reports')->assertOk();
    }

    public function test_pos_staff_cannot_open_roles_or_orders(): void
    {
        $p = User::where('email', 'omar.pos@store.example')->first();
        $this->actingAs($p)->get('/admin/roles')->assertForbidden();
        $this->actingAs($p)->get('/admin/reports')->assertForbidden();
    }

    public function test_orders_staff_can_approve_bank_transfer(): void
    {
        $s = User::where('email', 'orders@store.example')->first();
        $o = Order::where('status', 'pending_transfer')->first();
        $this->assertNotNull($o);
        $this->actingAs($s)->post("/admin/orders/{$o->id}/approve")->assertSessionHasErrors('order');   // بدون إيصال يُرفض الاعتماد
        $o->update(['receipt_path' => 'receipts/test.png']);
        $this->actingAs($s)->post("/admin/orders/{$o->id}/approve")->assertRedirect();
        $this->assertSame('paid', $o->fresh()->payment_status);
    }

    public function test_print_views_render(): void
    {
        $a = User::where('email', 'admin@store.example')->first();
        $ids = Order::pluck('id')->all();
        foreach (['invoice', 'waybill'] as $t) {
            $this->actingAs($a)->get('/admin/orders/print?type='.$t.'&'.http_build_query(['ids' => $ids]))->assertOk();
        }
    }
}
