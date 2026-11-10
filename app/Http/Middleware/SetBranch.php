<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;

/** يحدد فرع الطلب الحالي (انظر BranchContext) */
class SetBranch
{
    public function __construct(private BranchContext $ctx) {}

    public function handle(Request $r, Closure $next)
    {
        $this->ctx->set($this->resolve($r));
        return $next($r);
    }

    private function resolve(Request $r): ?int
    {
        if ($r->is('api/*')) {
            $id = $r->header('X-Branch-Id', $r->query('branch_id'));
        } elseif ($r->is('admin', 'admin/*')) {
            // الموظف المربوط بفرع يعمل على فرعه دائماً، والمدير يبدّل من القائمة
            $id = $r->user()?->branch_id ?? ($r->hasSession() ? $r->session()->get('admin_branch') : null);
        } else {
            $id = $r->hasSession() ? $r->session()->get('shop_branch') : null;
        }
        return ctype_digit((string) $id) && Branch::whereKey((int) $id)->where('active', true)->exists() ? (int) $id : null;
    }
}
