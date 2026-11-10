<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index()
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount('users')->orderBy('id')->get(),
            'staff' => User::with('role')->where('is_staff', true)->orderBy('id')->get(),
            'perms' => config('dp.permissions'),
        ]);
    }

    private function perms(Request $r): array
    {
        return array_values(array_intersect(array_keys(config('dp.permissions')), (array) $r->input('permissions', [])));
    }

    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:60']);
        Role::create(['name' => $d['name'], 'slug' => Str::slug($d['name']) ?: 'role-'.Str::random(5), 'permissions' => $this->perms($r)]);
        return back()->with('ok', 'تم إنشاء الدور');
    }

    public function update(Request $r, Role $role)
    {
        abort_if($role->locked, 403, 'دور المدير لا يُعدَّل');
        $d = $r->validate(['name' => 'required|string|max:60']);
        $role->update(['name' => $d['name'], 'permissions' => $this->perms($r)]);
        return back()->with('ok', 'تم حفظ صلاحيات «'.$role->name.'»');
    }

    public function destroy(Role $role)
    {
        abort_if($role->locked, 403);
        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'لا يمكن حذف دور مرتبط بموظفين']);
        }
        $role->delete();
        return back()->with('ok', 'تم حذف الدور');
    }

    public function storeStaff(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:80', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:8', 'role_id' => 'required|exists:roles,id', 'branch_id' => 'nullable|exists:branches,id']);
        User::create($d + ['is_staff' => true, 'phone_verified_at' => now()]);
        return back()->with('ok', 'تمت إضافة الموظف');
    }

    public function updateStaff(Request $r, User $user)
    {
        abort_unless($user->is_staff, 404);
        $d = $r->validate(['name' => 'required|string|max:80', 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8', 'role_id' => 'required|exists:roles,id', 'branch_id' => 'nullable|exists:branches,id', 'active' => 'nullable|boolean']);
        $d['branch_id'] = $d['branch_id'] ?? null;
        $active = $r->boolean('active');
        $adminRole = Role::where('slug', 'admin')->first();
        $losesAdmin = $user->role_id === $adminRole?->id && ($d['role_id'] != $adminRole->id || !$active);
        if ($losesAdmin && User::where('is_staff', true)->where('active', true)->where('role_id', $adminRole->id)->count() <= 1) {
            throw ValidationException::withMessages(['staff' => 'لا يمكن إزالة آخر مدير نظام']);
        }
        if ($user->id === $r->user()->id && !$active) {
            throw ValidationException::withMessages(['staff' => 'لا يمكنك تعطيل حسابك']);
        }
        if (empty($d['password'])) {
            unset($d['password']);
        }
        $user->update($d + ['active' => $active]);
        $active || $user->tokens()->delete();
        return back()->with('ok', 'تم تحديث الموظف');
    }
}
