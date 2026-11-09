<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function form()
    {
        return Auth::check() ? redirect()->route('admin.home') : view('auth.login');
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $u = User::where('email', $d['email'])->where('is_staff', true)->first();
        if (!$u || !$u->active || !password_verify($d['password'], (string) $u->password)) {
            throw ValidationException::withMessages(['email' => 'البريد أو كلمة المرور غير صحيحة']);
        }
        Auth::login($u, $r->boolean('remember'));
        $r->session()->regenerate();
        return redirect()->intended(route('admin.home'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect()->route('login');
    }
}
