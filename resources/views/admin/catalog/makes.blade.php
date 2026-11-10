@extends('layouts.admin')
@section('title', 'الماركات والموديلات')
@section('heading', 'الكتالوج والتوافق')
@section('content')
@include('admin.catalog._tabs')
@php
  $field = 'mt-1 w-full h-11 rounded-xl border border-line px-3 bg-white';
  $maxYear = now()->year + 1;
@endphp

<div class="grid gap-6 xl:grid-cols-3">
  <section class="xl:col-span-1">
    <form method="post" action="{{ route('admin.catalog.makes.store') }}" class="bg-white rounded-2xl border border-line p-5 space-y-3 xl:sticky xl:top-6">
      @csrf
      <h2 class="font-bold text-lg">ماركة جديدة</h2>
      <label class="block text-sm font-semibold">الاسم بالعربي<input name="name_ar" value="{{ old('name_ar') }}" required maxlength="60" placeholder="مازدا" class="{{ $field }}"></label>
      <label class="block text-sm font-semibold">الاسم بالإنجليزي<input name="name_en" value="{{ old('name_en') }}" required maxlength="60" dir="ltr" placeholder="Mazda" class="{{ $field }}"></label>
      <button class="w-full h-11 rounded-xl bg-brand text-ink font-bold">إضافة الماركة</button>
      <p class="text-xs text-neutral-500">بعد الإضافة افتح الماركة وأضف موديلاتها وسنوات الصنع. الموديلات تظهر للعميل عند اختيار سيارته وفي شاشة توافق القطع.</p>
    </form>
  </section>

  <section class="xl:col-span-2 space-y-3">
    @forelse ($makes as $mk)
      <article x-data="{ open: {{ $open === $mk->id ? 'true' : 'false' }}, edit: false }" class="bg-white rounded-2xl border border-line">
        <div class="p-4 flex flex-wrap gap-3 items-center">
          <button type="button" @click="open = !open" class="flex-1 min-w-[200px] flex items-center gap-3 text-right min-h-[44px]" :aria-expanded="open">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="transition shrink-0" :class="open ? 'rotate-90' : ''" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
            <span><b>{{ $mk->name_ar }}</b> <span class="text-sm text-neutral-500" dir="ltr">· {{ $mk->name_en }}</span></span>
            <span class="text-xs rounded-full bg-paper px-2 py-0.5 num">{{ $mk->models->count() }} موديل</span>
          </button>
          <div class="flex gap-2">
            <button type="button" @click="edit = !edit" class="h-10 px-4 rounded-xl border border-line text-sm font-semibold">تعديل</button>
            <form method="post" action="{{ route('admin.catalog.makes.destroy', $mk) }}" onsubmit="return confirm('حذف الماركة وكل موديلاتها؟')">@csrf @method('DELETE')<button class="h-10 px-4 rounded-xl text-red-800 text-sm font-semibold">حذف</button></form>
          </div>
        </div>

        <form x-show="edit" x-cloak method="post" action="{{ route('admin.catalog.makes.update', $mk) }}" class="border-t border-line p-4 flex flex-wrap gap-3 items-end bg-paper/50">
          @csrf @method('PUT')
          <label class="text-sm font-semibold flex-1 min-w-[160px]">الاسم بالعربي<input name="name_ar" value="{{ $mk->name_ar }}" required class="{{ $field }}"></label>
          <label class="text-sm font-semibold flex-1 min-w-[160px]">الاسم بالإنجليزي<input name="name_en" value="{{ $mk->name_en }}" required dir="ltr" class="{{ $field }}"></label>
          <button class="h-11 px-6 rounded-xl bg-ink text-white font-semibold">حفظ</button>
        </form>

        <div x-show="open" x-cloak class="border-t border-line">
          <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
              <thead class="bg-paper text-xs text-neutral-600"><tr><th class="p-3 text-right">الموديل</th><th class="p-3 text-right">بالإنجليزي</th><th class="p-3">من سنة</th><th class="p-3">إلى سنة</th><th class="p-3">قطع / سيارات</th><th class="p-3"></th></tr></thead>
              <tbody>
              @foreach ($mk->models as $mo)
                @php $cars = $vehicles[$mo->id] ?? 0; $locked = $mo->fitments_count || $cars; @endphp
                <tr class="border-t border-line">
                  <td class="p-2" colspan="4">
                    <form id="mo{{ $mo->id }}" method="post" action="{{ route('admin.catalog.makes.models.update', $mo) }}" class="grid grid-cols-4 gap-2">@csrf @method('PUT')
                      <input name="name_ar" value="{{ $mo->name_ar }}" required aria-label="الموديل" class="h-10 rounded-lg border border-line px-2">
                      <input name="name_en" value="{{ $mo->name_en }}" required dir="ltr" aria-label="بالإنجليزي" class="h-10 rounded-lg border border-line px-2">
                      <input type="number" name="year_from" value="{{ $mo->year_from }}" min="1970" max="{{ $maxYear }}" required aria-label="من سنة" class="h-10 rounded-lg border border-line px-2 num text-center">
                      <input type="number" name="year_to" value="{{ $mo->year_to }}" min="1970" max="{{ $maxYear }}" placeholder="حتى الآن" aria-label="إلى سنة" class="h-10 rounded-lg border border-line px-2 num text-center">
                    </form>
                  </td>
                  <td class="p-2 text-center text-xs num whitespace-nowrap">{{ $mo->fitments_count }} / {{ $cars }}</td>
                  <td class="p-2 whitespace-nowrap text-left">
                    <button form="mo{{ $mo->id }}" class="h-10 px-3 rounded-lg bg-ink text-white text-xs font-semibold">حفظ</button>
                    @unless ($locked)
                      <form method="post" action="{{ route('admin.catalog.makes.models.destroy', $mo) }}" class="inline" onsubmit="return confirm('حذف الموديل؟')">@csrf @method('DELETE')<button class="h-10 px-3 text-red-800 text-xs font-semibold">حذف</button></form>
                    @endunless
                  </td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
          <form method="post" action="{{ route('admin.catalog.makes.models.store', $mk) }}" class="border-t border-line p-3 grid grid-cols-2 sm:grid-cols-5 gap-2 bg-brand/5">
            @csrf
            <input name="name_ar" required placeholder="موديل جديد" aria-label="اسم الموديل" class="h-10 rounded-lg border border-line px-2 bg-white">
            <input name="name_en" required dir="ltr" placeholder="Model name" aria-label="الاسم بالإنجليزي" class="h-10 rounded-lg border border-line px-2 bg-white">
            <input type="number" name="year_from" required min="1970" max="{{ $maxYear }}" placeholder="من سنة" aria-label="من سنة" class="h-10 rounded-lg border border-line px-2 num bg-white">
            <input type="number" name="year_to" min="1970" max="{{ $maxYear }}" placeholder="إلى سنة" aria-label="إلى سنة" class="h-10 rounded-lg border border-line px-2 num bg-white">
            <button class="h-10 rounded-lg bg-brand text-ink font-bold text-sm col-span-2 sm:col-span-1">+ إضافة موديل</button>
          </form>
        </div>
      </article>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-line p-10 text-center text-neutral-500">لا توجد ماركات بعد.</div>
    @endforelse
  </section>
</div>
@endsection
