@extends('layouts.admin')
@section('title', 'الصفحات والحسابات البنكية')
@section('heading', 'الصفحات والحسابات البنكية')
@section('content')
@php $field = 'mt-1 w-full h-11 rounded-xl border border-line px-3 bg-white'; @endphp

<div class="grid gap-6 xl:grid-cols-5">
  {{-- الصفحات --}}
  <section class="xl:col-span-3 space-y-4">
    <h2 class="font-bold text-lg">صفحات التطبيق والموقع</h2>
    @foreach ($pages as $p)
      <form x-data="{ open: false }" method="post" action="{{ route('admin.content.pages.update', $p) }}" class="bg-white rounded-2xl border border-line">
        @csrf @method('PUT')
        <button type="button" @click="open = !open" class="w-full p-4 flex items-center justify-between gap-3 text-right">
          <span><b>{{ $p->title }}</b> <span class="font-mono text-xs text-neutral-500" dir="ltr">/pages/{{ $p->slug }}</span></span>
          <span class="text-xs text-neutral-500">آخر تعديل {{ $p->updated_at?->diffForHumans() }}</span>
        </button>
        <div x-show="open" x-cloak class="border-t border-line p-4 space-y-3">
          <label class="block text-sm font-semibold">العنوان<input name="title" value="{{ $p->title }}" required maxlength="150" class="{{ $field }}"></label>
          <label class="block text-sm font-semibold">المحتوى
            <textarea name="content" rows="14" required class="mt-1 w-full rounded-xl border border-line px-3 py-2 leading-7">{{ $p->content }}</textarea></label>
          <p class="text-xs text-neutral-500">نص عادي — كل سطر فارغ يفصل فقرة. يظهر في التطبيق كما هو.</p>
          <button class="h-11 px-6 rounded-xl bg-ink text-white font-semibold">حفظ</button>
        </div>
      </form>
    @endforeach
  </section>

  {{-- الحسابات البنكية --}}
  <section class="xl:col-span-2 space-y-4">
    <h2 class="font-bold text-lg">الحسابات البنكية للتحويل المباشر</h2>
    <p class="text-sm text-neutral-600 -mt-2">تظهر للعميل في شاشة الدفع عند اختيار التحويل البنكي. الأول في الترتيب هو الأساسي.</p>
    @foreach ($accounts as $a)
      <form x-data="{ edit: false }" method="post" action="{{ route('admin.content.accounts.update', $a) }}" class="bg-white rounded-2xl border border-line">
        @csrf @method('PUT')
        <div class="p-4">
          <div class="flex items-center gap-2"><b>{{ $a->bank_name }}</b>@unless ($a->active)<span class="text-xs rounded-full bg-neutral-200 px-2">مخفي</span>@endunless</div>
          <div class="text-sm text-neutral-600">{{ $a->beneficiary_name }}</div>
          <div class="font-mono text-sm mt-1" dir="ltr">{{ trim(chunk_split($a->iban, 4, ' ')) }}</div>
          <div class="flex gap-2 mt-3">
            <button type="button" @click="edit = !edit" class="h-9 px-4 rounded-xl border border-line text-sm font-semibold" x-text="edit ? 'إغلاق' : 'تعديل'">تعديل</button>
            <button form="del-acc-{{ $a->id }}" class="h-9 px-4 rounded-xl text-sm text-red-800 font-semibold">حذف</button>
          </div>
        </div>
        <div x-show="edit" x-cloak class="border-t border-line p-4 space-y-2 bg-paper/50">
          @include('admin.content._account', ['a' => $a])
          <button class="h-11 px-6 rounded-xl bg-ink text-white font-semibold">حفظ</button>
        </div>
      </form>
      <form id="del-acc-{{ $a->id }}" method="post" action="{{ route('admin.content.accounts.destroy', $a) }}" onsubmit="return confirm('حذف الحساب؟')">@csrf @method('DELETE')</form>
    @endforeach

    <form method="post" action="{{ route('admin.content.accounts.store') }}" class="bg-white rounded-2xl border border-dashed border-neutral-400 p-4 space-y-2">
      @csrf
      <h3 class="font-bold">حساب جديد</h3>
      @include('admin.content._account', ['a' => null])
      <button class="w-full h-11 rounded-xl bg-brand text-ink font-bold">إضافة</button>
    </form>
  </section>
</div>
@endsection
