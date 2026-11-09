<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'قطع غيار السيارات') — دفع المحركات</title>
<meta name="description" content="قطع غيار أصلية وبديلة معتمدة لسيارتك — توصيل لكل مدن المملكة والدفع عند الاستلام.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Noto+Kufi+Arabic:wght@700;800&display=swap">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{ink:'#111',brand:'#FF6B00',brandDark:'#A84400',paper:'#F5F5F7',line:'#E4E4E8'},fontFamily:{sans:['"IBM Plex Sans Arabic"','Tahoma','sans-serif'],kufi:['"Noto Kufi Arabic"','sans-serif']}}}}</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<style>
  [x-cloak]{display:none!important}
  body{font-family:"IBM Plex Sans Arabic",Tahoma,sans-serif}
  .num{font-variant-numeric:tabular-nums}
  :focus-visible{outline:2px solid #FF6B00;outline-offset:2px}
  .no-scrollbar{scrollbar-width:none}.no-scrollbar::-webkit-scrollbar{display:none}
</style>
@stack('head')
</head>
<body class="bg-paper text-ink antialiased min-h-screen flex flex-col">
@php
  $banners = $banners ?? collect();
  $shopCategories = $shopCategories ?? collect();
  $topbar = $banners->get('topbar', collect());
@endphp

{{-- شريط الإعلانات النصي --}}
@if ($topbar->isNotEmpty())
<div x-data="{ i: 0, n: {{ $topbar->count() }}, show: true }" x-init="n > 1 && setInterval(() => i = (i + 1) % n, 5000)" x-show="show"
     class="text-white text-sm" :style="'background:' + {{ Js::from($topbar->pluck('bg_color')) }}[i]" style="background: {{ $topbar->first()->bg_color }}">
  <div class="max-w-6xl mx-auto px-4 min-h-[40px] flex items-center justify-center gap-3 relative">
    @foreach ($topbar as $k => $t)
      <p x-show="i === {{ $k }}" @if ($k) x-cloak @endif class="text-center py-2">
        <b>{{ $t->title }}</b>@if ($t->subtitle) <span class="opacity-90">— {{ $t->subtitle }}</span>@endif
        @if ($t->link_url)<a href="{{ route('shop.banner', $t) }}" class="underline underline-offset-4 font-semibold mr-2">{{ $t->button_text ?: 'التفاصيل' }}</a>@endif
      </p>
    @endforeach
    <button type="button" @click="show = false" class="absolute left-2 w-9 h-9 grid place-items-center opacity-80 hover:opacity-100" aria-label="إغلاق الإعلان">✕</button>
  </div>
</div>
@endif

{{-- الهيدر --}}
<header class="bg-ink text-white sticky top-0 z-30 shadow-lg shadow-black/10" x-data="{ menu: false }">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-3">
    <button type="button" class="sm:hidden w-11 h-11 grid place-items-center rounded-xl hover:bg-white/10" @click="menu = !menu" aria-label="القائمة">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <a href="{{ route('shop.index') }}" class="flex items-center gap-2 shrink-0">
      <span class="w-9 h-9 rounded-xl bg-brand text-ink grid place-items-center" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg>
      </span>
      <span class="font-kufi font-extrabold text-lg leading-none">دفع <span class="text-brand">المحركات</span></span>
    </a>
    <form action="{{ route('shop.index') }}" class="flex-1 hidden sm:flex relative">
      <input name="q" value="{{ request('q') }}" placeholder="ابحث باسم القطعة أو رقمها (SKU)" aria-label="بحث" class="w-full h-11 rounded-xl pr-4 pl-12 text-ink bg-white">
      <button class="absolute left-1 top-1 h-9 w-10 rounded-lg bg-brand text-ink grid place-items-center" aria-label="بحث">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      </button>
    </form>
    <div class="flex items-center gap-1 mr-auto sm:mr-0">
      <a href="{{ route('shop.track') }}" class="hidden md:inline-flex text-sm min-h-[44px] items-center gap-2 px-3 rounded-xl hover:bg-white/10">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v10H3zM14 10h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>تتبّع طلبك</a>
      <a href="{{ route('shop.cart') }}" class="relative min-h-[44px] inline-flex items-center gap-2 px-4 rounded-xl bg-brand text-ink font-bold text-sm hover:brightness-105">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.4 11h10.2L20 8H6.2"/><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/></svg>
        السلة <span class="num min-w-[22px] h-[22px] px-1 rounded-full bg-ink text-white text-xs grid place-items-center">{{ $cartCount ?? 0 }}</span>
      </a>
    </div>
  </div>

  {{-- بحث الجوال --}}
  <form action="{{ route('shop.index') }}" class="sm:hidden px-4 pb-3"><input name="q" value="{{ request('q') }}" placeholder="ابحث عن قطعة" aria-label="بحث" class="w-full h-11 rounded-xl px-4 text-ink"></form>

  {{-- شريط الفئات --}}
  @if ($shopCategories->isNotEmpty())
  <nav class="border-t border-white/10 bg-[#1a1a1a]" aria-label="الفئات" :class="menu ? 'block' : 'hidden sm:block'">
    <div class="max-w-6xl mx-auto px-4 flex sm:items-center gap-1 overflow-x-auto no-scrollbar flex-col sm:flex-row py-2 sm:py-0">
      <a href="{{ route('shop.index') }}" class="whitespace-nowrap text-sm min-h-[44px] inline-flex items-center px-3 border-b-2 {{ request()->routeIs('shop.index') && !request('category') ? 'border-brand text-white font-semibold' : 'border-transparent text-white/75 hover:text-white' }}">كل القطع</a>
      @foreach ($shopCategories as $c)
        <a href="{{ route('shop.index', ['category' => $c->slug]) }}" class="whitespace-nowrap text-sm min-h-[44px] inline-flex items-center px-3 border-b-2 {{ request('category') === $c->slug ? 'border-brand text-white font-semibold' : 'border-transparent text-white/75 hover:text-white' }}">{{ $c->name_ar }}</a>
      @endforeach
      <a href="{{ route('shop.track') }}" class="md:hidden whitespace-nowrap text-sm min-h-[44px] inline-flex items-center px-3 text-white/75">تتبّع طلبك</a>
    </div>
  </nav>
  @endif
</header>

@yield('hero')

<main class="flex-1 max-w-6xl w-full mx-auto px-4 py-6">
  @if (session('ok'))<div role="status" class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 p-3 text-sm">{{ session('ok') }}</div>@endif
  @if ($errors->any())<div role="alert" class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-900 p-3 text-sm">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
  @yield('content')
</main>

{{-- مميزات المتجر --}}
<section class="bg-white border-t border-line">
  <div class="max-w-6xl mx-auto px-4 py-6 grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
    @foreach ([
      ['M5 12l4 4L19 6', 'قطع مضمونة', 'أصلي وكالة وبديل معتمد'],
      ['M3 7h11v10H3zM14 10h4l3 3v4h-7', 'توصيل لكل المدن', 'شحن سريع داخل المملكة'],
      ['M4 7h16v10H4zM4 11h16', 'الدفع عند الاستلام', 'أو تحويل بنكي'],
      ['M12 3a9 9 0 1 0 9 9M12 7v5l3 2', 'متوافق مع سيارتك', 'ابحث بالماركة والموديل والسنة'],
    ] as [$icon, $t, $s])
      <div class="flex items-center gap-3">
        <span class="w-11 h-11 rounded-xl bg-brand/10 text-brandDark grid place-items-center shrink-0"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg></span>
        <div><b class="block">{{ $t }}</b><span class="text-neutral-500 text-xs">{{ $s }}</span></div>
      </div>
    @endforeach
  </div>
</section>

<footer class="bg-ink text-white/70 text-sm">
  <div class="max-w-6xl mx-auto px-4 py-10 grid gap-8 sm:grid-cols-3">
    <div>
      <div class="font-kufi font-extrabold text-lg text-white">دفع <span class="text-brand">المحركات</span></div>
      <p class="mt-2 leading-relaxed">قطع غيار السيارات الأصلية والبديلة المعتمدة، بأسعار واضحة شاملة الضريبة.</p>
    </div>
    <div>
      <b class="text-white block mb-2">الفئات</b>
      <ul class="grid grid-cols-2 gap-1">@foreach ($shopCategories->take(8) as $c)<li><a href="{{ route('shop.index', ['category' => $c->slug]) }}" class="hover:text-white">{{ $c->name_ar }}</a></li>@endforeach</ul>
    </div>
    <div>
      <b class="text-white block mb-2">خدمة العملاء</b>
      <ul class="space-y-1">
        <li><a href="{{ route('shop.track') }}" class="hover:text-white">تتبّع طلبك</a></li>
        <li><a href="{{ route('shop.cart') }}" class="hover:text-white">سلة المشتريات</a></li>
      </ul>
    </div>
  </div>
  <div class="border-t border-white/10"><div class="max-w-6xl mx-auto px-4 py-4 text-xs">{{ config('dp.seller_name') }} · الأسعار شاملة ضريبة القيمة المضافة 15% · © {{ date('Y') }}</div></div>
</footer>
@stack('scripts')
</body></html>
