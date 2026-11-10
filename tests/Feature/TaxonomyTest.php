<?php

namespace Tests\Feature;

use App\Models\CarModel;
use App\Models\Category;
use App\Models\Make;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@store.example')->firstOrFail();
    }

    public function test_pages_render(): void
    {
        $this->actingAs($this->admin)->get('/admin/catalog/categories')->assertOk()->assertSee('قسم جديد');
        $this->actingAs($this->admin)->get('/admin/catalog/makes')->assertOk()->assertSee('ماركة جديدة');
        $this->actingAs($this->admin)->get('/admin/catalog?category='.Category::first()->id)->assertOk();
    }

    public function test_create_category_appears_in_api(): void
    {
        $this->actingAs($this->admin)->post('/admin/catalog/categories', ['name_ar' => 'المكيف والتبريد', 'name_en' => 'A/C & Cooling'])->assertRedirect();
        $c = Category::where('name_ar', 'المكيف والتبريد')->firstOrFail();
        $this->assertSame('ac-cooling', $c->slug);
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonFragment(['slug' => 'ac-cooling']);
    }

    public function test_category_with_parts_cannot_be_deleted(): void
    {
        $c = Category::has('parts')->firstOrFail();
        $this->actingAs($this->admin)->delete("/admin/catalog/categories/{$c->id}")->assertSessionHasErrors('category');
        $this->assertModelExists($c);
    }

    public function test_make_and_model_flow(): void
    {
        $this->actingAs($this->admin)->post('/admin/catalog/makes', ['name_ar' => 'مازدا', 'name_en' => 'Mazda'])->assertRedirect();
        $make = Make::where('name_en', 'Mazda')->firstOrFail();
        $this->actingAs($this->admin)->post("/admin/catalog/makes/{$make->id}/models", ['name_ar' => 'CX-5', 'name_en' => 'CX-5', 'year_from' => 2017, 'year_to' => 2015])
            ->assertSessionHasErrors('year_to');
        $this->actingAs($this->admin)->post("/admin/catalog/makes/{$make->id}/models", ['name_ar' => 'CX-5', 'name_en' => 'CX-5', 'year_from' => 2017])->assertRedirect();
        $this->getJson("/api/v1/makes/{$make->id}/models")->assertOk()->assertJsonFragment(['name_en' => 'CX-5']);

        $model = CarModel::where('name_en', 'CX-5')->firstOrFail();
        $this->actingAs($this->admin)->delete("/admin/catalog/models/{$model->id}")->assertRedirect();
        $this->assertModelMissing($model);
    }

    public function test_model_used_by_customer_car_is_protected(): void
    {
        $used = CarModel::whereHas('fitments')->firstOrFail();
        $this->actingAs($this->admin)->delete("/admin/catalog/models/{$used->id}")->assertSessionHasErrors('model');
        $this->actingAs($this->admin)->delete("/admin/catalog/makes/{$used->make_id}")->assertSessionHasErrors('make');
        $this->assertModelExists($used);
    }

    public function test_pos_staff_cannot_manage(): void
    {
        $pos = User::where('email', 'omar.pos@store.example')->firstOrFail();
        $this->actingAs($pos)->get('/admin/catalog/categories')->assertForbidden();
    }
}
