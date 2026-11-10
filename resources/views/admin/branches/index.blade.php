@extends('layouts.admin')
@section('title', 'الفروع')
@section('heading', 'الفروع')
@section('content')
@php $field = 'mt-1 w-full h-11 rounded-xl border border-line px-3 bg-white'; @endphp

<div class="grid gap-6 xl:grid-cols-3">
  <section class="xl:col-span-1">
    <form method="post" action="{{ route('admin.branches.store') }}" class="bg-white rounded-2xl border border-line p-5 space-y-3 xl:sticky xl:top-6">
      @csrf
      <h2 class="font-bold text-lg">فرع جديد</h2>
      @include('admin.branches._fields', ['b' => null])
      <button class="w-full h-11 rounded-xl bg-brand text-ink font-bold">إضافة الفرع</button>
      <p class="text-xs text-neutral-500 leading-relaxed">الفرع الجديد يبدأ بمخزون صفر. أضف كمياته من صفحة المخزون (بعد التبديل إليه) أو حوّل له من فرع آخر.</p>
    </form>
  </section>

  <section class="xl:col-span-2 space-y-3">
    @foreach ($branches as $b)
      @php $s = $stock[$b->id] ?? null; @endphp
      <article x-data="{ edit: false }" class="bg-white rounded-2xl border {{ $b->active ? 'border-line' : 'border-dashed border-neutral-300 opacity-80' }}">
        <div class="p-4 flex flex-wrap gap-4 items-start">
          <div class="flex-1 min-w-[220px]">
            <div class="flex flex-wrap items-center gap-2">
              <b class="text-lg">{{ $b->name }}</b>
              <span class="font-mono text-xs rounded bg-paper px-2 py-0.5" dir="ltr">{{ $b->code }}</span>
              @if ($b->is_default)<span class="text-xs rounded-full bg-brand/15 text-brandDark px-2 py-0.5 font-semibold">الافتراضي</span>@endif
              @unless ($b->active)<span class="text-xs rounded-full bg-neutral-200 px-2 py-0.5">موقوف</span>@endunless
            </div>
            <p class="text-sm text-neutral-600 mt-1">{{ $b->city }}@if ($b->address) — {{ $b->address }}@endif</p>
            <p class="text-xs text-neutral-500 mt-1">
              @if ($b->phone)<span dir="ltr">{{ $b->phone }}</span> · @endif{{ $b->hours }}
            </p>
            <div class="flex flex-wrap gap-2 mt-2 text-xs">
              <span class="rounded-full px-2 py-0.5 {{ $b->pickup_enabled ? 'bg-emerald-100 text-emerald-900' : 'bg-neutral-100 text-neutral-500 line-through' }}">استلام من الفرع</span>
              <span class="rounded-full px-2 py-0.5 {{ $b->delivery_enabled ? 'bg-sky-100 text-sky-900' : 'bg-neutral-100 text-neutral-500 line-through' }}">توصيل</span>
            </div>
          </div>
          <dl class="grid grid-cols-3 gap-3 text-center text-xs">
            <div class="rounded-xl bg-paper px-3 py-2"><dt class="text-neutral-500">قطع متوفرة</dt><dd class="num text-lg font-bold">{{ (int) ($s->skus ?? 0) }}</dd></div>
            <div class="rounded-xl bg-paper px-3 py-2"><dt class="text-neutral-500">إجمالي الكمية</dt><dd class="num text-lg font-bold">{{ (int) ($s->on_hand ?? 0) }}</dd></div>
            <div class="rounded-xl bg-paper px-3 py-2"><dt class="text-neutral-500">طلبات مفتوحة</dt><dd class="num text-lg font-bold">{{ $open[$b->id] ?? 0 }}</dd></div>
          </dl>
        </div>
        <div class="px-4 pb-4 flex flex-wrap gap-2">
          <button type="button" @click="edit = !edit" class="h-10 px-4 rounded-xl border border-line text-sm font-semibold" x-text="edit ? 'إغلاق' : 'تعديل'">تعديل</button>
          @if ($b->active && !auth()->user()->branch_id)
            <form method="post" action="{{ route('admin.branch.switch') }}">@csrf<input type="hidden" name="branch_id" value="{{ $b->id }}"><button class="h-10 px-4 rounded-xl border border-line text-sm">إدارة مخزونه</button></form>
          @endif
          <a href="{{ route('admin.orders.index', ['branch' => $b->id]) }}" class="h-10 px-4 inline-flex items-center rounded-xl border border-line text-sm">طلباته</a>
          @if (!$b->is_default && $b->active)
            <form method="post" action="{{ route('admin.branches.default', $b) }}">@csrf<button class="h-10 px-4 rounded-xl text-sm text-brandDark font-semibold">اجعله الافتراضي</button></form>
          @endif
          @unless ($b->is_default)
            <form method="post" action="{{ route('admin.branches.destroy', $b) }}" onsubmit="return confirm('حذف الفرع؟')">@csrf @method('DELETE')<button class="h-10 px-4 rounded-xl text-sm text-red-800 font-semibold">حذف</button></form>
          @endunless
        </div>
        <form x-show="edit" x-cloak method="post" action="{{ route('admin.branches.update', $b) }}" class="border-t border-line p-4 grid sm:grid-cols-2 gap-3 bg-paper/50">
          @csrf @method('PUT')
          @include('admin.branches._fields', ['b' => $b])
          <div class="sm:col-span-2"><button class="h-11 px-6 rounded-xl bg-ink text-white font-semibold">حفظ</button></div>
        </form>
      </article>
    @endforeach
  </section>
</div>
@endsection
