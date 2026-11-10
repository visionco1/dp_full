@php
  $tabs = [
    ['admin.catalog.index', 'القطع', 'admin.catalog.index'],
    ['admin.catalog.categories.index', 'الأقسام', 'admin.catalog.categories.*'],
    ['admin.catalog.makes.index', 'الماركات والموديلات', 'admin.catalog.makes.*'],
  ];
@endphp
<nav class="flex gap-1 mb-5 border-b border-line overflow-x-auto" aria-label="أقسام الكتالوج">
  @foreach ($tabs as [$route, $label, $active])
    <a href="{{ route($route) }}" class="whitespace-nowrap min-h-[44px] inline-flex items-center px-4 border-b-2 -mb-px text-sm {{ request()->routeIs($active) ? 'border-brand font-bold' : 'border-transparent text-neutral-600 hover:text-ink' }}">{{ $label }}</a>
  @endforeach
</nav>
