<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Part;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_guest_purchase_and_tracking(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->get('/')->assertOk();
        $part = Part::whereHas('inventory', fn ($q) => $q->where('on_hand', '>', 3))->first();
        $this->get('/parts/'.$part->id)->assertOk();
        $this->post('/cart', ['part_id' => $part->id, 'qty' => 1])->assertRedirect();
        $this->get('/cart')->assertOk();
        $this->get('/checkout')->assertOk();
        $res = $this->post('/checkout', ['name' => 'ضيف', 'phone' => '0512345600', 'city' => 'جدة', 'line' => 'حي الروضة', 'payment_method' => 'cod']);
        $o = Order::latest('id')->first();
        $res->assertRedirect('/orders/'.$o->number);
        $this->get('/orders/'.$o->number)->assertOk();
        $this->flushSession();
        $this->get('/orders/'.$o->number)->assertNotFound();
        $this->post('/track', ['number' => $o->number, 'phone' => '0512345600'])->assertRedirect('/orders/'.$o->number);
    }
}
