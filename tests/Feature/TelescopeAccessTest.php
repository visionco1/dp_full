<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Telescope محمي بصلاحية telescope.view حتى في بيئة local */
class TelescopeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // مُطفأ في phpunit.xml لباقي الاختبارات — نشغّله هنا فقط قبل إقلاع التطبيق
        $this->setTelescope('true');
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->app['env'] = 'local';   // أسوأ حالة: الخادم يعمل بـ APP_ENV=local
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->setTelescope('false');
    }

    private function setTelescope(string $v): void
    {
        putenv("TELESCOPE_ENABLED=$v");
        $_ENV['TELESCOPE_ENABLED'] = $_SERVER['TELESCOPE_ENABLED'] = $v;
    }

    public function test_guest_and_staff_without_permission_are_blocked(): void
    {
        $this->get('/telescope')->assertForbidden();
        $this->getJson('/telescope/telescope-api/requests')->assertForbidden();

        $pos = User::where('email', 'omar.pos@store.example')->firstOrFail();
        $this->actingAs($pos)->get('/telescope')->assertForbidden();

        $customer = User::where('phone', '966512345678')->firstOrFail();
        $this->actingAs($customer)->get('/telescope')->assertForbidden();
    }

    public function test_admin_can_open(): void
    {
        $admin = User::where('email', 'admin@store.example')->firstOrFail();
        $this->actingAs($admin)->get('/telescope')->assertOk();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertSee('مراقبة النظام');
    }
}
