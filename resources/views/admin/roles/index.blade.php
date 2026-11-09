@extends('layouts.admin')
@section('title', 'الصلاحيات')
@section('heading', 'الأدوار والموظفون')
@section('content')
@if ($errors->any())<div class="mb-4 rounded-xl bg-red-50 text-red-900 p-3 text-sm">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="grid gap-4 lg:grid-cols-2">
  <section class="space-y-4">
    <h2 class="font-bold">الأدوار</h2>
    @foreach ($roles as $role)
      <form method="post" action="{{ route('admin.roles.update', $role) }}" class="bg-white rounded-2xl border border-line p-4 space-y-3">
        @csrf @method('PUT')
        <div class="flex gap-2 items-center">
          <input name="name" value="{{ $role->name }}" class="h-11 rounded-xl border px-3 flex-1 font-semibold" @disabled($role->locked)>
          <span class="text-xs text-neutral-500">{{ $role->users_count }} موظف</span>
        </div>
        @if ($role->locked)
          <p class="text-sm text-neutral-600">دور المدير يملك كل الصلاحيات ولا يُعدَّل.</p>
        @else
          <div class="grid sm:grid-cols-2 gap-1">
            @foreach ($perms as $key => $label)
              <label class="flex items-center gap-2 min-h-[40px] text-sm"><input type="checkbox" name="permissions[]" value="{{ $key }}" class="w-5 h-5 accent-black" @checked(in_array($key, $role->permissions ?? []))>{{ $label }}</label>
            @endforeach
          </div>
          <div class="flex gap-2">
            <button class="h-11 px-5 rounded-xl bg-ink text-white font-semibold">حفظ</button>
            @if (!$role->users_count)<button form="del{{ $role->id }}" class="h-11 px-5 rounded-xl border-2 border-red-700 text-red-800 font-semibold">حذف</button>@endif
          </div>
        @endif
      </form>
      @unless ($role->locked || $role->users_count)<form id="del{{ $role->id }}" method="post" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('حذف الدور؟')">@csrf @method('DELETE')</form>@endunless
    @endforeach

    <form method="post" action="{{ route('admin.roles.store') }}" class="bg-white rounded-2xl border border-dashed border-neutral-400 p-4 space-y-3">
      @csrf
      <h3 class="font-semibold">دور جديد</h3>
      <input name="name" required placeholder="اسم الدور" class="h-11 rounded-xl border px-3 w-full">
      <div class="grid sm:grid-cols-2 gap-1">
        @foreach ($perms as $key => $label)<label class="flex items-center gap-2 min-h-[40px] text-sm"><input type="checkbox" name="permissions[]" value="{{ $key }}" class="w-5 h-5 accent-black">{{ $label }}</label>@endforeach
      </div>
      <button class="h-11 px-5 rounded-xl bg-brand text-ink font-bold">إنشاء</button>
    </form>
  </section>

  <section class="space-y-4">
    <h2 class="font-bold">الموظفون</h2>
    @foreach ($staff as $u)
      <form method="post" action="{{ route('admin.roles.staff.update', $u) }}" class="bg-white rounded-2xl border border-line p-4 grid sm:grid-cols-2 gap-2">
        @csrf @method('PUT')
        <input name="name" value="{{ $u->name }}" aria-label="الاسم" class="h-11 rounded-xl border px-3">
        <input name="email" type="email" dir="ltr" value="{{ $u->email }}" aria-label="البريد" class="h-11 rounded-xl border px-3">
        <select name="role_id" aria-label="الدور" class="h-11 rounded-xl border px-3">@foreach ($roles as $r)<option value="{{ $r->id }}" @selected($u->role_id === $r->id)>{{ $r->name }}</option>@endforeach</select>
        <input name="password" type="password" dir="ltr" placeholder="كلمة مرور جديدة (اختياري)" class="h-11 rounded-xl border px-3">
        <label class="flex items-center gap-2 min-h-[44px] text-sm"><input type="checkbox" name="active" value="1" class="w-5 h-5 accent-black" @checked($u->active)>الحساب مفعّل</label>
        <button class="h-11 px-5 rounded-xl bg-ink text-white font-semibold">حفظ</button>
      </form>
    @endforeach
    <form method="post" action="{{ route('admin.roles.staff.store') }}" class="bg-white rounded-2xl border border-dashed border-neutral-400 p-4 grid sm:grid-cols-2 gap-2">
      @csrf
      <h3 class="font-semibold sm:col-span-2">موظف جديد</h3>
      <input name="name" required placeholder="الاسم" class="h-11 rounded-xl border px-3">
      <input name="email" type="email" dir="ltr" required placeholder="البريد" class="h-11 rounded-xl border px-3">
      <select name="role_id" required class="h-11 rounded-xl border px-3">@foreach ($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>
      <input name="password" type="password" dir="ltr" required minlength="8" placeholder="كلمة المرور (8+)" class="h-11 rounded-xl border px-3">
      <button class="h-11 px-5 rounded-xl bg-brand text-ink font-bold sm:col-span-2">إضافة</button>
    </form>
  </section>
</div>
@endsection
