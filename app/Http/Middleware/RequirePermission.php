<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();
        if (!$user || !$user->is_staff || !$user->active) {
            abort(403, 'غير مصرّح');
        }
        foreach ($permissions as $p) {
            if ($user->hasPermission($p)) {
                return $next($request);
            }
        }
        abort(403, 'ليست لديك صلاحية لهذه الصفحة');
    }
}
