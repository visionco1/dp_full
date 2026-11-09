@extends('layouts.admin')
@section('title', 'البانرات والإعلانات')
@section('heading', 'البانرات والإعلانات')
@section('actions')
  <a href="{{ route('shop.index') }}" target="_blank" class="h-11 px-4 inline-flex items-center rounded-xl border border-line bg-white text-sm font-semibold">عرض الموقع ↗</a>
@endsection
@section('content')

<div class="grid gap-6 xl:grid-cols-3">
  {{-- إضافة بانر --}}
  <section class="xl:col-span-1">
    <form method="post" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data" class="bg-white rounded-2xl border border-line p-5 space-y-3 xl:sticky xl:top-6">
      @csrf
      <h2 class="font-bold text-lg">بانر جديد</h2>
      @include('admin.banners._fields', ['b' => null])
      <button class="w-full h-11 rounded-xl bg-brand text-ink font-bold">إضافة</button>
      <p class="text-xs text-neutral-500 leading-relaxed">المقاسات المقترحة: السلايدر 1600×600 · الإعلان العريض 1600×300 · الجانبي 600×800. شريط الإعلان النصي لا يحتاج صورة.</p>
    </form>
  </section>

  {{-- القائمة --}}
  <section class="xl:col-span-2 space-y-4">
    <form method="get" class="flex flex-wrap gap-2">
      <a href="{{ route('admin.banners.index') }}" class="h-10 px-4 inline-flex items-center rounded-full text-sm {{ request('placement') ? 'bg-white border border-line' : 'bg-ink text-white' }}">الكل ({{ \App\Models\Banner::count() }})</a>
      @foreach ($placements as $k => $label)
        <a href="{{ route('admin.banners.index', ['placement' => $k]) }}" class="h-10 px-4 inline-flex items-center rounded-full text-sm {{ request('placement') === $k ? 'bg-ink text-white' : 'bg-white border border-line' }}">{{ Str::before($label, ' (') }}</a>
      @endforeach
    </form>

    @forelse ($banners as $b)
      <article x-data="{ edit: false }" class="bg-white rounded-2xl border border-line overflow-hidden">
        <div class="flex flex-wrap gap-4 p-4 items-center">
          <div class="w-40 aspect-[8/3] rounded-xl overflow-hidden grid place-items-center text-white text-xs font-bold shrink-0" style="background: {{ $b->bg_color }}">
            @if ($b->image)<img src="{{ $b->imageUrl() }}" alt="" class="w-full h-full object-cover">@else<span class="px-2 text-center">{{ Str::limit($b->title, 30) }}</span>@endif
          </div>
          <div class="flex-1 min-w-[200px]">
            <div class="flex flex-wrap items-center gap-2">
              <b>{{ $b->title }}</b>
              <span class="text-xs rounded-full bg-paper px-2 py-0.5">{{ Str::before($placements[$b->placement] ?? $b->placement, ' (') }}</span>
              @if ($b->isLive())<span class="text-xs rounded-full bg-emerald-100 text-emerald-900 px-2 py-0.5">ظاهر الآن</span>
              @elseif (!$b->active)<span class="text-xs rounded-full bg-neutral-200 px-2 py-0.5">موقوف</span>
              @else<span class="text-xs rounded-full bg-amber-100 text-amber-900 px-2 py-0.5">خارج فترة العرض</span>@endif
            </div>
            @if ($b->subtitle)<p class="text-sm text-neutral-600 mt-1">{{ $b->subtitle }}</p>@endif
            <p class="text-xs text-neutral-500 mt-1 num">
              ترتيب {{ $b->sort }} · {{ number_format($b->clicks) }} نقرة
              @if ($b->starts_at || $b->ends_at) · من {{ $b->starts_at?->format('Y-m-d') ?? '—' }} إلى {{ $b->ends_at?->format('Y-m-d') ?? '—' }}@endif
            </p>
          </div>
          <div class="flex gap-2">
            <button type="button" @click="edit = !edit" class="h-10 px-4 rounded-xl border border-line text-sm font-semibold" x-text="edit ? 'إغلاق' : 'تعديل'">تعديل</button>
            <form method="post" action="{{ route('admin.banners.toggle', $b) }}">@csrf<button class="h-10 px-4 rounded-xl border border-line text-sm">{{ $b->active ? 'إيقاف' : 'تفعيل' }}</button></form>
            <form method="post" action="{{ route('admin.banners.destroy', $b) }}" onsubmit="return confirm('حذف البانر؟')">@csrf @method('DELETE')<button class="h-10 px-4 rounded-xl text-red-800 text-sm font-semibold">حذف</button></form>
          </div>
        </div>
        <form x-show="edit" x-cloak method="post" action="{{ route('admin.banners.update', $b) }}" enctype="multipart/form-data" class="border-t border-line p-4 grid sm:grid-cols-2 gap-3 bg-paper/50">
          @csrf @method('PUT')
          @include('admin.banners._fields', ['b' => $b])
          <div class="sm:col-span-2"><button class="h-11 px-6 rounded-xl bg-ink text-white font-semibold">حفظ التعديلات</button></div>
        </form>
      </article>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-line p-10 text-center text-neutral-500">لا توجد بانرات بعد — أضف أول بانر من النموذج.</div>
    @endforelse
  </section>
</div>
@endsection
