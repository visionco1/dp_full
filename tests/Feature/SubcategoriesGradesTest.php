<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Part;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubcategoriesGradesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_categories_include_subcategories_with_counts(): void
    {
        $res = $this->getJson('/api/v1/categories')->assertOk()
            ->assertJsonStructure(['categories' => [['id', 'slug', 'name_ar', 'image', 'parts_count', 'subcategories' => [['id', 'parent_id', 'slug', 'name_ar', 'image', 'parts_count']]]]]);
        $electrical = collect($res->json('categories'))->firstWhere('slug', 'electrical');
        $this->assertContains('batteries', array_column($electrical['subcategories'], 'slug'));
        $this->assertSame(array_sum(array_column($electrical['subcategories'], 'parts_count')) + Part::where('active', true)->where('category_id', $electrical['id'])->count(), $electrical['parts_count']);
        // الأقسام الفرعية لا تظهر كأقسام رئيسية
        $this->assertNotContains('batteries', array_column($res->json('categories'), 'slug'));
    }

    public function test_subcategories_endpoint_by_id_and_slug(): void
    {
        $id = Category::where('slug', 'body')->value('id');
        $this->getJson("/api/v1/categories/$id/subcategories")->assertOk()->assertJsonFragment(['slug' => 'fenders']);
        $this->getJson('/api/v1/categories/body/subcategories')->assertOk()->assertJsonPath('category.slug', 'body');
    }

    public function test_main_category_filter_includes_subcategory_parts(): void
    {
        $battery = Part::where('sku', '31500-5X00A')->firstOrFail();
        $this->assertSame('batteries', $battery->category->slug);
        $this->getJson('/api/v1/parts?category=electrical&per_page=50')->assertOk()->assertJsonFragment(['sku' => '31500-5X00A']);
        $this->getJson('/api/v1/parts?category=batteries&per_page=50')->assertOk()->assertJsonFragment(['sku' => '31500-5X00A'])->assertJsonMissing(['sku' => '27060-0V160']);
        $this->get('/?category=electrical')->assertOk()->assertSee('بطاريات');
    }

    public function test_grades_endpoint_and_alias(): void
    {
        $this->getJson('/api/v1/grades')->assertOk()
            ->assertJsonCount(3, 'grades')
            ->assertJsonStructure(['grades' => [['code', 'label_ar', 'label_en', 'parts_count']]])
            ->assertJsonFragment(['code' => 'first', 'label_ar' => 'درجة أولى']);
        $data = $this->getJson('/api/v1/parts?grade=aftermarket&per_page=50')->assertOk()->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame(['first'], array_values(array_unique(array_column($data, 'grade'))));
    }

    public function test_admin_subcategory_rules(): void
    {
        $admin = User::where('email', 'admin@store.example')->firstOrFail();
        $body = Category::where('slug', 'body')->firstOrFail();
        $fenders = Category::where('slug', 'fenders')->firstOrFail();

        $this->actingAs($admin)->post('/admin/catalog/categories', ['name_ar' => 'شبك أمامي', 'name_en' => 'Grilles', 'parent_id' => $body->id])->assertSessionHasNoErrors();
        $this->assertSame($body->id, Category::where('slug', 'grilles')->value('parent_id'));

        // لا قسم فرعي تحت قسم فرعي
        $this->actingAs($admin)->post('/admin/catalog/categories', ['name_ar' => 'x', 'name_en' => 'x', 'parent_id' => $fenders->id])->assertSessionHasErrors('parent_id');
        // القسم الرئيسي الذي فيه فروع لا يُحذف
        $this->actingAs($admin)->delete("/admin/catalog/categories/{$body->id}")->assertSessionHasErrors('category');
        $this->actingAs($admin)->get('/admin/catalog/categories')->assertOk()->assertSee('رفارف');
    }
}
