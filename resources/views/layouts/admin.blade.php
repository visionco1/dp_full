<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'لوحة التحكم') — دفع المحركات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Noto+Kufi+Arabic:wght@600;700;800&display=swap">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: {
  colors: { ink: '#111111', brand: '#FF6B00', brandDark: '#A84400', paper: '#F5F5F7', line: '#E4E4E8' },
  fontFamily: { sans: ['"IBM Plex Sans Arabic"', 'Tahoma', 'sans-serif'], kufi: ['"Noto Kufi Arabic"', '"IBM Plex Sans Arabic"', 'sans-serif'], mono: ['"IBM Plex Mono"', 'monospace'] }
} } }
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<style>
  [x-cloak]{display:none!important}
  body{font-family:"IBM Plex Sans Arabic",Tahoma,sans-serif}
  .num{font-variant-numeric:tabular-nums}
  :focus-visible{outline:2px solid #FF6B00;outline-offset:2px}
</style>
@stack('head')
</head>
<body class="bg-paper text-ink antialiased">
@php
  $u = auth()->user();
  $nav = [
    ['admin.dashboard', 'لوحة التحكم', 'dashboard.view', 'M4 4h7v9H4zM13 4h7v5h-7zM13 11h7v9h-7zM4 15h7v5H4z'],
    ['admin.pos.index', 'نقطة البيع', 'pos.use', 'M3 4h18v12H3zM8 20h8M12 16v4'],
    ['admin.inventory.index', 'المخزون', 'inventory.view', 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10'],
    ['admin.catalog.index', 'الكتالوج والتوافق', 'catalog.manage', 'M4 5h16M4 12h16M4 19h10'],
    ['admin.banners.index', 'البانرات والإعلانات', 'banners.manage', 'M3 5h18v10H3zM7 19h10M9 9l3 2-3 2z'],
    ['admin.faqs.index', 'الأسئلة الشائعة', 'support.manage', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01'],
    ['admin.orders.index', 'الطلبات', 'orders.view', 'M6 2h12v20l-3-2-3 2-3-2-3 2zM9 7h6M9 11h6M9 15h4'],
    ['admin.roles.index', 'الموظفون والصلاحيات', 'roles.manage', 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0'],
    ['admin.reports.index', 'التقارير والتصدير', 'reports.export', 'M12 3v12M7 10l5 5 5-5M5 21h14'],
  ];
@endphp
<div class="min-h-screen flex flex-wrap">
  <aside class="bg-ink text-white w-full lg:w-64 lg:min-h-screen lg:sticky lg:top-0 lg:self-start shrink-0">
    <div class="p-5 flex items-center justify-between lg:block">
      <a href="{{ route('admin.home') }}" class="block">
        <div class="font-kufi font-extrabold text-lg leading-tight">دفع المحركات</div>
        <div class="text-xs text-white/60 font-mono" dir="ltr">Engine Power &amp; More</div>
      </a>
      <span class="lg:hidden text-xs text-white/70">{{ $u->name }}</span>
    </div>
    <nav class="px-3 pb-3 flex lg:flex-col gap-1 overflow-x-auto" aria-label="القائمة الرئيسية">
      @foreach ($nav as [$route, $label, $perm, $icon])
        @can($perm)
          <a href="{{ route($route) }}" class="flex items-center gap-3 min-h-[44px] px-3 rounded-xl whitespace-nowrap text-sm {{ request()->routeIs(substr_count($route, '.') > 1 ? Str::beforeLast($route, '.').'.*' : $route) ? 'bg-brand text-ink font-bold' : 'text-white/80 hover:bg-white/10' }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
            {{ $label }}
          </a>
        @endcan
      @endforeach
    </nav>
    <div class="hidden lg:block p-4 mt-4 border-t border-white/10 text-sm">
      <div class="font-semibold">{{ $u->name }}</div>
      <div class="text-white/60 text-xs">{{ $u->role?->name }}</div>
      <form method="post" action="{{ route('admin.logout') }}" class="mt-3">@csrf
        <button class="min-h-[44px] px-3 rounded-xl border border-white/30 text-white/90 hover:bg-white/10 text-sm">تسجيل الخروج</button>
      </form>
    </div>
  </aside>

  <main class="flex-1 min-w-0 p-4 lg:p-8">
    <header class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <h1 class="font-kufi text-2xl font-bold">@yield('heading')</h1>
      <div class="flex gap-2 flex-wrap">@yield('actions')</div>
    </header>

    @if (session('ok'))
      <div role="status" class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-3 text-sm">{{ session('ok') }}</div>
    @endif
    @if ($errors->any())
      <div role="alert" class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-900 px-4 py-3 text-sm">
        <ul class="list-disc pr-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    @yield('content')
  </main>
</div>
@stack('scripts')
</body>
</html>
