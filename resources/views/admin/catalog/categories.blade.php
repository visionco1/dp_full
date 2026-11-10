@extends('layouts.admin')
@section('title', 'الأقسام')
@section('heading', 'الكتالوج والتوافق')
@section('content')
@include('admin.catalog._tabs')
@php $field = 'mt-1 w-full h-11 rounded-xl border border-line px-3 bg-white'; @endphp

<div class="grid gap-6 xl:grid-cols-3">
  <section class="xl:col-span-1">
    <form method="post" action="{{ route('admin.catalog.categories.store') }}" enctype="multipart/form-data" class="bg-white rounded-2xl border border-line p-5 space-y-3 xl:sticky xl:top-6">
      @csrf
      <h2 class="font-bold text-lg">قسم جديد</h2>
      <label class="block text-sm font-semibold">يتبع القسم الرئيسي
        <select name="parent_id" class="{{ $field }}">
          <option value="">— قسم رئيسي جديد —</option>
          @foreach ($categories as $r)<option value="{{ $r->id }}" @selected(old('parent_id', request('parent')) == $r->id)>{{ $r->name_ar }}</option>@endforeach
        </select></label>
      <label class="block text-sm font-semibold">الاسم بالعربي<input name="name_ar" value="{{ old('name_ar') }}" required maxlength="60" placeholder="رفارف" class="{{ $field }}"></label>
      <label class="block text-sm font-semibold">الاسم بالإنجليزي<input name="name_en" value="{{ old('name_en') }}" maxlength="60" dir="ltr" placeholder="Fenders" class="{{ $field }}"></label>
      <label class="block text-sm font-semibold">المعرّف في الرابط (slug) <span class="font-normal text-neutral-500">— اختياري</span>
        <input name="slug" value="{{ old('slug') }}" maxlength="60" dir="ltr" placeholder="fenders" class="{{ $field }} font-mono text-sm"></label>
      <label class="block text-sm font-semibold">الأيقونة / الصورة<input type="file" name="image" accept="image/*" class="mt-1 w-full text-sm"></label>
      <button class="w-full h-11 rounded-xl bg-brand text-ink font-bold">إضافة القسم</button>
      <p class="text-xs text-neutral-500 leading-relaxed">اختر "قسم رئيسي" لقسم جديد في القائمة الرئيسية، أو اختر قسماً لإضافة قسم فرعي تحته (مثل: البودي ← رفارف). القسم الرئيسي يعرض قطع كل أقسامه الفرعية. الصورة المقترحة 400×400.</p>
    </form>
  </section>

  <section class="xl:col-span-2 space-y-3">
    @forelse ($categories as $c)
      <article class="bg-white rounded-2xl border border-line overflow-hidden">
        @include('admin.catalog._category_row', ['c' => $c, 'roots' => $categories, 'sub' => false])
        @foreach ($c->children as $s)
          @include('admin.catalog._category_row', ['c' => $s, 'roots' => $categories, 'sub' => true])
        @endforeach
        <a href="{{ route('admin.catalog.categories.index', ['parent' => $c->id]) }}" class="block border-t border-line px-4 py-2 text-sm text-brandDark font-semibold hover:bg-brand/5">+ قسم فرعي تحت {{ $c->name_ar }}</a>
      </article>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-line p-10 text-center text-neutral-500">لا توجد أقسام بعد.</div>
    @endforelse
  </section>
</div>
@endsection
