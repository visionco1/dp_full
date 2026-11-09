@extends('shop.layout')
@section('title', 'الكتالوج')
@php
  $grades = ['oem' => 'أصلي وكالة', 'cert' => 'تجاري بديل معتمد', 'first' => 'درجة أولى'];
  $gradeStyle = ['oem' => 'bg-emerald-100 text-emerald-900', 'cert' => 'bg-sky-100 text-sky-900', 'first' => 'bg-amber-100 text-amber-900'];
  $isLanding = !request()->hasAny(['q', 'category', 'grade', 'model', 'in_stock', 'sort']) && $parts->currentPage() === 1;
  $strips = $banners->get('strip', collect());
  $sides = $banners->get('side', collect());
@endphp

@section('hero')
  @if ($isLanding) @include('shop._hero') @endif
@endsection

@section('content')
@if ($isLanding && $categories->isNotEmpty())
  <section class="mb-6">
    <h2 class="font-kufi font-extrabold text-lg mb-3">تسوّق حسب الفئة</h2>
    <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
      @foreach ($categories as $c)
        <a href="{{ route('shop.index', ['category' => $c->slug]) }}" class="shrink-0 h-11 px-5 inline-flex items-center rounded-full bg-white border border-line text-sm font-semibold hover:border-brand hover:text-brandDark">{{ $c->name_ar }}</a>
      @endforeach
    </div>
  </section>
@endif

<div class="grid lg:grid-cols-4 gap-6">
  <aside class="lg:col-span-1 space-y-4 self-start lg:sticky lg:top-32">
    <form method="get" class="bg-white rounded-2xl border border-line p-4 space-y-3" x-data="{ make: '{{ request('make') }}' }">
      <h2 class="font-bold flex items-center gap-2"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>ابحث لسيارتك</h2>
      <input name="q" value="{{ request('q') }}" placeholder="بحث" aria-label="بحث" class="h-11 rounded-xl border px-3 w-full sm:hidden">
      <label class="block text-sm font-semibold">سيارتك
        <select x-model="make" name="make" class="mt-1 w-full h-11 rounded-xl border px-3"><option value="">الماركة</option>@foreach ($makes as $m)<option value="{{ $m->id }}">{{ $m->name_ar }}</option>@endforeach</select></label>
      @foreach ($makes as $m)
        <select x-show="make=='{{ $m->id }}'" x-cloak name="model" {{ request('make') == $m->id ? '' : 'disabled' }} :disabled="make!='{{ $m->id }}'" aria-label="الموديل" class="w-full h-11 rounded-xl border px-3"><option value="">الموديل</option>@foreach ($m->models as $mo)<option value="{{ $mo->id }}" @selected(request('model') == $mo->id)>{{ $mo->name_ar }}</option>@endforeach</select>
      @endforeach
      <input name="year" type="number" min="1990" max="2030" value="{{ request('year') }}" placeholder="سنة الصنع" class="h-11 rounded-xl border px-3 w-full">
      <label class="block text-sm font-semibold">الفئة<select name="category" class="mt-1 w-full h-11 rounded-xl border px-3"><option value="">الكل</option>@foreach ($categories as $c)<option value="{{ $c->slug }}" @selected(request('category') === $c->slug)>{{ $c->name_ar }}</option>@endforeach</select></label>
      <label class="block text-sm font-semibold">الجودة<select name="grade" class="mt-1 w-full h-11 rounded-xl border px-3"><option value="">الكل</option>@foreach ($grades as $k => $l)<option value="{{ $k }}" @selected(request('grade') === $k)>{{ $l }}</option>@endforeach</select></label>
      <label class="block text-sm font-semibold">الترتيب<select name="sort" class="mt-1 w-full h-11 rounded-xl border px-3"><option value="">الأنسب</option><option value="price_asc" @selected(request('sort') === 'price_asc')>الأقل سعراً</option><option value="price_desc" @selected(request('sort') === 'price_desc')>الأعلى سعراً</option><option value="newest" @selected(request('sort') === 'newest')>الأحدث</option></select></label>
      <label class="flex items-center gap-2 min-h-[44px] text-sm"><input type="checkbox" name="in_stock" value="1" class="w-5 h-5 accent-black" @checked(request('in_stock'))>المتوفر فقط</label>
      <button class="w-full h-11 rounded-xl bg-ink text-white font-semibold">تطبيق</button>
      @unless ($isLanding)<a href="{{ route('shop.index') }}" class="block text-center text-sm text-brandDark font-semibold min-h-[44px] leading-[44px]">مسح الفلاتر</a>@endunless
    </form>
    @foreach ($sides->take(2) as $ad)
      <div class="hidden lg:block">@include('shop._ad', ['ad' => $ad, 'variant' => 'side'])</div>
    @endforeach
  </aside>

  <section class="lg:col-span-3">
    <div class="flex items-center justify-between mb-3">
      <h1 class="font-kufi font-extrabold text-lg">{{ $isLanding ? 'أحدث القطع' : 'نتائج البحث' }}</h1>
      <p class="text-sm text-neutral-600"><span class="num">{{ $parts->total() }}</span> قطعة</p>
    </div>
    <div class="grid grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4">
    @forelse ($parts as $p)
      <article class="group bg-white rounded-2xl border border-line overflow-hidden flex flex-col hover:shadow-lg hover:shadow-black/5 hover:-translate-y-0.5 transition">
        <a href="{{ route('shop.part', $p) }}" class="relative block aspect-[4/3] bg-neutral-100 grid place-items-center text-neutral-400 text-sm overflow-hidden">
          @if ($p->image)<img src="{{ asset('storage/'.$p->image) }}" alt="{{ $p->name_ar }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">@else {{ $p->category?->name_ar }} @endif
          <span class="absolute top-2 right-2 text-[11px] font-semibold rounded-full px-2 py-0.5 {{ $gradeStyle[$p->grade] ?? 'bg-neutral-100' }}">{{ $grades[$p->grade] ?? $p->grade }}</span>
          @if ($p->available <= 0)<span class="absolute inset-0 bg-white/60 grid place-items-center font-bold text-red-800">غير متوفر</span>@endif
        </a>
        <div class="p-3 sm:p-4 flex-1 flex flex-col gap-1">
          <a href="{{ route('shop.part', $p) }}" class="font-semibold leading-snug line-clamp-2 hover:text-brandDark">{{ $p->name_ar }}</a>
          <div class="text-xs text-neutral-500 truncate">{{ $p->brand }} · <span dir="ltr">{{ $p->sku }}</span></div>
          <div class="mt-auto pt-2 flex flex-wrap items-center justify-between gap-2">
            <b class="num text-lg">{{ number_format($p->price, 2) }} <span class="text-xs font-normal">ر.س</span></b>
            @if ($p->available > 0)
              <form method="post" action="{{ route('shop.cart.add') }}">@csrf<input type="hidden" name="part_id" value="{{ $p->id }}"><button class="h-10 px-3 sm:px-4 rounded-xl bg-brand text-ink font-bold text-sm hover:brightness-105">أضف للسلة</button></form>
            @endif
          </div>
        </div>
      </article>

      {{-- إعلان عريض بعد كل 6 منتجات --}}
      @if ($loop->iteration % 6 === 0 && !$loop->last && ($ad = $strips->get(intdiv($loop->iteration, 6) - 1)))
        <div class="col-span-full">@include('shop._ad', ['ad' => $ad, 'variant' => 'strip'])</div>
      @endif
    @empty<p class="col-span-full text-center text-neutral-500 py-12">لا توجد نتائج مطابقة.</p>@endforelse
    </div>

    {{-- لو المنتجات قليلة نعرض أول إعلان عريض تحتها --}}
    @if ($parts->count() > 0 && $parts->count() <= 6 && ($ad = $strips->first()))
      <div class="mt-4">@include('shop._ad', ['ad' => $ad, 'variant' => 'strip'])</div>
    @endif

    {{-- الإعلانات الجانبية على الجوال --}}
    @if ($sides->isNotEmpty())
      <div class="lg:hidden grid grid-cols-2 gap-3 mt-4">@foreach ($sides->take(2) as $ad)@include('shop._ad', ['ad' => $ad, 'variant' => 'side'])@endforeach</div>
    @endif

    <div class="mt-6">{{ $parts->links() }}</div>
  </section>
</div>
@endsection
