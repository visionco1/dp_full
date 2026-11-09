@extends('layouts.admin')
@section('title', 'المخزون')
@section('heading', 'المخزون المركزي')
@section('actions')
  <a href="{{ route('admin.inventory.export', ['format' => 'xlsx']) }}" class="h-11 px-4 rounded-xl border bg-white inline-flex items-center text-sm font-semibold">تصدير Excel</a>
  <a href="{{ route('admin.inventory.export', ['format' => 'csv']) }}" class="h-11 px-4 rounded-xl border bg-white inline-flex items-center text-sm font-semibold">CSV</a>
  @if ($canImport)<a href="#import" class="h-11 px-4 rounded-xl bg-brand text-ink font-bold inline-flex items-center text-sm">استيراد Excel / CSV</a>@endif
@endsection
@section('content')
<div class="grid grid-cols-3 gap-3 mb-4">
  @foreach ([['all', 'إجمالي الأصناف', $stats['total'], ''], ['low', 'مخزون منخفض', $stats['low'], 'low'], ['out', 'نفدت', $stats['out'], 'out']] as [$k, $l, $n, $st])
    <a href="{{ route('admin.inventory.index', $st ? ['status' => $st] : []) }}" class="bg-white border rounded-2xl p-4 {{ request('status') === $st && $st ? 'border-ink' : 'border-line' }}"><div class="text-xs text-neutral-600">{{ $l }}</div><div class="font-kufi text-3xl font-extrabold num">{{ $n }}</div></a>
  @endforeach
</div>

<form method="get" class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="{{ request('q') }}" placeholder="رقم القطعة أو الاسم" class="h-11 rounded-xl border px-4 flex-1 min-w-[200px]">
  <select name="category" class="h-11 rounded-xl border px-3"><option value="">كل الأقسام</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name_ar }}</option>@endforeach</select>
  <select name="status" class="h-11 rounded-xl border px-3"><option value="">كل الحالات</option><option value="low" @selected(request('status') === 'low')>منخفض</option><option value="out" @selected(request('status') === 'out')>نفد</option></select>
  <button class="h-11 px-5 rounded-xl bg-ink text-white font-semibold">بحث</button>
</form>

<div class="bg-white rounded-2xl border border-line overflow-x-auto">
  <table class="w-full text-sm min-w-[860px]">
    <thead class="bg-paper text-neutral-600 text-xs"><tr>
      <th class="text-right p-3">رقم القطعة</th><th class="text-right p-3">الاسم</th><th class="p-3">الفعلي</th><th class="p-3">محجوز</th><th class="p-3">المتاح</th>
      <th class="p-3">السعر</th>@if ($canCost)<th class="p-3">التكلفة</th><th class="p-3">الهامش</th>@endif @if ($canEdit)<th class="p-3"></th>@endif
    </tr></thead>
    <tbody>
    @foreach ($parts as $p)
      @php $i = $p->inventory; $av = $p->available; @endphp
      <tr class="border-t border-line align-middle">
        <td class="p-3 font-mono text-xs" dir="ltr">{{ $p->sku }}<form id="f{{ $p->id }}" method="post" action="{{ route('admin.inventory.update', $p) }}">@csrf @method('PATCH')</form></td>
        <td class="p-3">{{ $p->name_ar }}@if($p->side) {{ $p->side === 'R' ? 'يمين' : 'يسار' }}@endif <span class="text-xs text-neutral-500">· {{ \App\Http\Presenter::GRADES[$p->grade] }}</span></td>
        <td class="p-3 text-center">@if ($canEdit)<input form="f{{ $p->id }}" name="on_hand" type="number" min="0" value="{{ $i->on_hand ?? 0 }}" class="w-20 h-10 rounded-lg border text-center num">@else<span class="num">{{ $i->on_hand ?? 0 }}</span>@endif</td>
        <td class="p-3 text-center num">{{ $i->reserved ?? 0 }}</td>
        <td class="p-3 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-bold num {{ $av <= 0 ? 'bg-red-100 text-red-800' : ($av <= ($i->low_stock_at ?? 3) ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900') }}">{{ $av <= 0 ? 'نفد' : $av }}</span></td>
        <td class="p-3 text-center">@if ($canEdit)<input form="f{{ $p->id }}" name="price" type="number" step="0.01" value="{{ $p->price }}" class="w-24 h-10 rounded-lg border text-center num">@else<span class="num">{{ number_format($p->price, 2) }}</span>@endif</td>
        @if ($canCost)
          <td class="p-3 text-center">@if ($canEdit)<input form="f{{ $p->id }}" name="cost" type="number" step="0.01" value="{{ $p->cost }}" class="w-24 h-10 rounded-lg border text-center num">@else<span class="num">{{ number_format($p->cost, 2) }}</span>@endif</td>
          <td class="p-3 text-center num">{{ $p->price > 0 ? round(($p->price - $p->cost) / $p->price * 100) : 0 }}%</td>
        @endif
        @if ($canEdit)<td class="p-3"><button form="f{{ $p->id }}" class="h-10 px-3 rounded-lg bg-ink text-white text-xs font-semibold">حفظ</button></td>@endif
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
<div class="mt-4">{{ $parts->links() }}</div>

@if ($canImport)
<section id="import" class="mt-8 bg-white rounded-2xl border border-line p-5 max-w-2xl">
  <h2 class="font-bold mb-1">استيراد المخزون من Excel / CSV</h2>
  <p class="text-sm text-neutral-600 mb-3">الأعمدة: <span class="font-mono" dir="ltr">sku, on_hand, price, cost</span> (السعر والتكلفة اختياريان). نعرض لك التغييرات والأخطاء قبل التطبيق. <a class="text-brandDark font-semibold" href="{{ route('admin.inventory.template') }}">تنزيل القالب</a></p>
  <form method="post" action="{{ route('admin.inventory.import.preview') }}" enctype="multipart/form-data" class="flex gap-2 flex-wrap">@csrf
    <input type="file" name="file" accept=".csv,.xlsx,.txt" required class="flex-1 min-w-[220px] h-12 rounded-xl border px-3 pt-2.5">
    <button class="h-12 px-5 rounded-xl bg-brand text-ink font-bold">معاينة</button>
  </form>
</section>
@endif
@endsection
