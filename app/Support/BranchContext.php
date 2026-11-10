<?php

namespace App\Support;

use App\Models\Branch;

/**
 * الفرع الحالي للطلب (HTTP request). كل قراءات المخزون ($part->inventory و $part->available) تكون لهذا الفرع.
 * يُحدَّد في الميدلوير SetBranch: هيدر X-Branch-Id للتطبيق، الجلسة للموقع، فرع الموظف للوحة الإدارة.
 * بدون اختيار → الفرع الافتراضي (الرئيسي).
 */
class BranchContext
{
    private ?int $selected = null;
    private ?int $default = null;

    public function set(?int $branchId): void
    {
        $this->selected = $branchId;
    }

    public function selected(): ?int
    {
        return $this->selected;
    }

    public function id(): ?int
    {
        return $this->selected ?? ($this->default ??= Branch::defaultId());
    }

    /** نفّذ كوداً على فرع معيّن ثم أرجع الاختيار السابق */
    public function run(int $branchId, callable $fn): mixed
    {
        $prev = $this->selected;
        $this->selected = $branchId;
        try {
            return $fn();
        } finally {
            $this->selected = $prev;
        }
    }

    public static function current(): ?int
    {
        return app(self::class)->id();
    }
}
