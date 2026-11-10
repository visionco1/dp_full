<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Part;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $main;
    private Branch $jed;
    private Branch $dmm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->main = Branch::where('is_default', true)->firstOrFail();
        $this->jed = Branch::where('code', 'JED')->firstOrFail();
        $this->dmm = Branch::where('code', 'DMM')->firstOrFail();
    }

    /** يضبط كمية قطعة في فرع */
    private function stock(Part $p, Branch $b, int $qty): void
    {
        Inventory::updateOrCreate(['part_id' => $p->id, 'branch_id' => $b->id], ['on_hand' => $qty, 'reserved' => 0]);
    }

    private function reserved(Part $p, Branch $b): int
    {
        return (int) Inventory::where('part_id', $p->id)->where('branch_id', $b->id)->value('reserved');
    }

    private function cartWith(Part $p, int $qty, array $headers = []): array
    {
        $token = $this->getJson('/api/v1/cart', $headers)->json('token');
        $h = $headers + ['X-Cart-Token' => $token];
        $this->postJson('/api/v1/cart/items', ['part_id' => $p->id, 'qty' => $qty], $h)->assertSuccessful();
        return $h;
    }

    public function test_availability_follows_selected_branch(): void
    {
        $p = Part::where('sku', '04152-YZZA6')->firstOrFail();
        $this->stock($p, $this->main, 5);
        $this->stock($p, $this->jed, 0);

        $this->getJson("/api/v1/parts/{$p->id}")->assertOk()->assertJsonPath('part.available', 5);
        $this->getJson("/api/v1/parts/{$p->id}", ['X-Branch-Id' => $this->jed->id])->assertOk()->assertJsonPath('part.available', 0);

        $stock = collect($this->getJson("/api/v1/parts/{$p->id}")->json('part.branches_stock'))->keyBy('branch_id');
        $this->assertSame(5, $stock[$this->main->id]['available']);
        $this->assertSame(0, $stock[$this->jed->id]['available']);
    }

    public function test_branches_endpoint_reports_cart_coverage(): void
    {
        $p = Part::where('sku', '04152-YZZA6')->firstOrFail();
        $this->stock($p, $this->main, 10);
        $this->stock($p, $this->jed, 1);
        $h = $this->cartWith($p, 3);

        $rows = collect($this->getJson('/api/v1/branches', $h)->assertOk()->json('branches'))->keyBy('code');
        $this->assertTrue($rows['MAIN']['cart']['can_fulfill']);
        $this->assertFalse($rows['JED']['cart']['can_fulfill']);
        $this->assertSame(1, $rows['JED']['cart']['missing'][0]['available']);

        $pickup = collect($this->getJson('/api/v1/branches?fulfillment=delivery')->json('branches'))->pluck('code');
        $this->assertNotContains('DMM', $pickup);   // الدمام لا يدعم التوصيل
    }

    public function test_pickup_order_full_flow_uses_branch_stock(): void
    {
        $p = Part::where('sku', '04152-YZZA6')->firstOrFail();
        $this->stock($p, $this->main, 10);
        $this->stock($p, $this->jed, 4);
        $h = $this->cartWith($p, 2, ['X-Branch-Id' => $this->jed->id]);

        $res = $this->postJson('/api/v1/checkout', [
            'fulfillment' => 'pickup', 'branch_id' => $this->jed->id, 'payment_method' => 'cod',
            'contact' => ['name' => 'مستلم', 'phone' => '0512345611'],
        ], $h)->assertCreated()
            ->assertJsonPath('order.fulfillment', 'pickup')
            ->assertJsonPath('order.branch.code', 'JED')
            ->assertJsonPath('order.address', null)
            ->assertJsonPath('order.totals.shipping', 0);
        $this->assertContains('ready', array_column($res->json('order.timeline'), 'status'));

        $this->assertSame(2, $this->reserved($p, $this->jed));
        $this->assertSame(0, $this->reserved($p, $this->main));

        $order = Order::where('number', $res->json('order.number'))->firstOrFail();
        $admin = User::where('email', 'admin@store.example')->firstOrFail();
        $this->actingAs($admin)->post("/admin/orders/{$order->id}/processing")->assertRedirect();
        $this->actingAs($admin)->post("/admin/orders/{$order->id}/ship")->assertSessionHasErrors('order');   // الاستلام لا يُشحن
        $this->actingAs($admin)->post("/admin/orders/{$order->id}/ready")->assertRedirect();
        $this->assertSame('ready', $order->fresh()->status);
        $this->actingAs($admin)->post("/admin/orders/{$order->id}/complete")->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);

        $inv = Inventory::where('part_id', $p->id)->where('branch_id', $this->jed->id)->first();
        $this->assertSame([2, 0], [$inv->on_hand, $inv->reserved]);
        $this->assertSame(10, Inventory::where('part_id', $p->id)->where('branch_id', $this->main->id)->value('on_hand'));
    }

    public function test_checkout_rejected_when_branch_lacks_stock_or_mode(): void
    {
        $p = Part::where('sku', '04152-YZZA6')->firstOrFail();
        $this->stock($p, $this->main, 10);
        $this->stock($p, $this->jed, 1);
        $this->stock($p, $this->dmm, 10);
        $h = $this->cartWith($p, 3);
        $addr = ['name' => 'عميل', 'phone' => '0512345612', 'city' => 'جدة', 'line' => 'حي الروضة'];

        $this->postJson('/api/v1/checkout', ['branch_id' => $this->jed->id, 'payment_method' => 'cod', 'address' => $addr], $h)
            ->assertStatus(422)->assertJsonValidationErrors('items');
        $this->postJson('/api/v1/checkout', ['branch_id' => $this->dmm->id, 'fulfillment' => 'delivery', 'payment_method' => 'cod', 'address' => $addr], $h)
            ->assertStatus(422)->assertJsonValidationErrors('fulfillment');
        $this->postJson('/api/v1/checkout', ['fulfillment' => 'pickup', 'branch_id' => $this->main->id, 'payment_method' => 'cod'], $h)
            ->assertStatus(422)->assertJsonValidationErrors(['contact.name', 'contact.phone']);
        // بدون branch_id → الفرع الافتراضي (توافق مع الإصدار الحالي للتطبيق)
        $this->postJson('/api/v1/checkout', ['payment_method' => 'cod', 'address' => $addr], $h)->assertCreated()->assertJsonPath('order.branch.code', 'MAIN');
    }

    public function test_admin_branches_transfer_and_staff_scope(): void
    {
        $admin = User::where('email', 'admin@store.example')->firstOrFail();
        $p = Part::where('sku', '04152-YZZA6')->firstOrFail();
        $this->stock($p, $this->main, 10);
        $this->stock($p, $this->jed, 0);

        $this->actingAs($admin)->get('/admin/branches')->assertOk()->assertSee('فرع جدة');
        $this->actingAs($admin)->post('/admin/branches/transfer', ['part_id' => $p->id, 'from_branch' => $this->main->id, 'to_branch' => $this->jed->id, 'qty' => 4])->assertSessionHasNoErrors();
        $this->assertSame(6, Inventory::where('part_id', $p->id)->where('branch_id', $this->main->id)->value('on_hand'));
        $this->assertSame(4, Inventory::where('part_id', $p->id)->where('branch_id', $this->jed->id)->value('on_hand'));
        $this->actingAs($admin)->post('/admin/branches/transfer', ['part_id' => $p->id, 'from_branch' => $this->jed->id, 'to_branch' => $this->main->id, 'qty' => 50])->assertSessionHasErrors('qty');

        // المدير يبدّل فرع العمل → صفحة المخزون تعرض فرع جدة
        $this->actingAs($admin)->post('/admin/branch', ['branch_id' => $this->jed->id])->assertRedirect();
        $this->actingAs($admin)->get('/admin/inventory')->assertOk()->assertSee('المخزون — فرع جدة');

        // موظف مربوط بالفرع الرئيسي لا يرى طلبات جدة
        $ordersStaff = User::where('email', 'orders@store.example')->firstOrFail();
        $ordersStaff->update(['branch_id' => $this->main->id]);
        $jedOrder = Order::first();
        $jedOrder->update(['branch_id' => $this->jed->id]);
        $this->actingAs($ordersStaff)->get("/admin/orders/{$jedOrder->id}")->assertForbidden();
    }

    public function test_pos_sells_from_staff_branch(): void
    {
        $pos = User::where('email', 'omar.pos@store.example')->firstOrFail();
        $pos->update(['branch_id' => $this->jed->id]);
        $p = Part::where('sku', '04152-YZZA6')->firstOrFail();
        $this->stock($p, $this->main, 10);
        $this->stock($p, $this->jed, 3);

        $this->actingAs($pos)->postJson('/admin/pos/checkout', ['items' => [['part_id' => $p->id, 'qty' => 2]], 'payment' => 'cash'])->assertCreated();
        $this->assertSame(1, Inventory::where('part_id', $p->id)->where('branch_id', $this->jed->id)->value('on_hand'));
        $this->assertSame(10, Inventory::where('part_id', $p->id)->where('branch_id', $this->main->id)->value('on_hand'));
        $this->assertSame($this->jed->id, Order::where('channel', 'pos')->latest('id')->value('branch_id'));
    }

    public function test_web_checkout_pickup(): void
    {
        $p = Part::where('sku', '04152-YZZA6')->firstOrFail();
        $this->stock($p, $this->jed, 5);
        $this->post('/cart', ['part_id' => $p->id, 'qty' => 1]);   // يضيف حسب فرع الموقع الافتراضي
        $this->get('/checkout')->assertOk()->assertSee('استلام من الفرع')->assertSee('فرع جدة');
        $this->post('/checkout', ['fulfillment' => 'pickup', 'branch_id' => $this->jed->id, 'name' => 'ضيف', 'phone' => '0512345613', 'payment_method' => 'cod'])->assertRedirect();
        $o = Order::latest('id')->firstOrFail();
        $this->assertSame(['pickup', $this->jed->id, 0.0], [$o->fulfillment, $o->branch_id, $o->shipping]);
    }
}
