@extends('layouts.admin')
@section('title', 'الإرجاع والاستبدال')
@section('heading', 'طلبات الإرجاع والاستبدال')
@section('content')
@php $st = \App\Models\ReturnRequest::STATUSES; @endphp
<div class="flex gap-2 mb-4 flex-wrap">
  <a href="{{ route('admin.returns.index') }}" class="h-10 px-4 inline-flex items-center rounded-full text-sm {{ request('status') ? 'bg-white border border-line' : 'bg-ink text-white' }}">المفتوحة</a>
  @foreach ($st as $k => $l)
    <a href="{{ route('admin.returns.index', ['status' => $k]) }}" class="h-10 px-4 inline-flex items-center rounded-full text-sm {{ request('status') === $k ? 'bg-ink text-white' : 'bg-white border border-line' }}">{{ $l }}</a>
  @endforeach
</div>

<div class="space-y-3">
@forelse ($returns as $x)
  <article class="bg-white rounded-2xl border border-line p-4 flex flex-wrap gap-4" x-data="{ reject: false }">
    @if ($x->image)<a href="{{ asset('storage/'.$x->image) }}" target="_blank" class="w-24 h-24 rounded-xl overflow-hidden bg-paper shrink-0"><img src="{{ asset('storage/'.$x->image) }}" alt="صورة القطعة" class="w-full h-full object-cover"></a>@endif
    <div class="flex-1 min-w-[240px] space-y-1">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold rounded-full px-2 py-0.5 {{ $x->action === 'return' ? 'bg-red-100 text-red-900' : 'bg-sky-100 text-sky-900' }}">{{ \App\Models\ReturnRequest::ACTIONS[$x->action] }}</span>
        <b>{{ $x->item?->name }}</b> <span class="font-mono text-xs" dir="ltr">{{ $x->item?->sku }}</span> × <span class="num">{{ $x->qty }}</span>
        <span class="text-xs rounded-full bg-paper px-2 py-0.5">{{ $st[$x->status] }}</span>
      </div>
      <div class="text-sm">السبب: {{ $x->reason }}</div>
      <div class="text-xs text-neutral-500">
        <a href="{{ route('admin.orders.show', $x->order) }}" class="font-mono text-brandDark font-semibold" dir="ltr">{{ $x->order->number }}</a>
        · {{ $x->order->customer_name }} · {{ $x->order->branch?->name }} · {{ $x->created_at->format('Y-m-d H:i') }}
      </div>
      @if ($x->admin_note)<div class="text-xs text-neutral-600">ملاحظة: {{ $x->admin_note }}</div>@endif
    </div>
    @can('orders.manage')
    <div class="flex flex-col gap-2 items-stretch min-w-[180px]">
      @if ($x->status === 'pending')
        <form method="post" action="{{ route('admin.returns.update', [$x, 'approve']) }}">@csrf<input type="hidden" name="note" value=""><button class="w-full h-10 rounded-xl bg-emerald-700 text-white text-sm font-semibold">موافقة</button></form>
      @endif
      @if ($x->status === 'approved')
        <form method="post" action="{{ route('admin.returns.update', [$x, 'complete']) }}" onsubmit="return confirm('{{ $x->action === 'return' ? 'استلمت القطعة؟ ستعود للمخزون' : 'تم الاستبدال؟' }}')">@csrf<button class="w-full h-10 rounded-xl bg-ink text-white text-sm font-semibold">{{ $x->action === 'return' ? 'استلمنا القطعة' : 'تم الاستبدال' }}</button></form>
      @endif
      @if (in_array($x->status, ['pending', 'approved']))
        <button type="button" @click="reject = !reject" class="h-10 rounded-xl border-2 border-red-700 text-red-800 text-sm font-semibold">رفض</button>
        <form x-show="reject" x-cloak method="post" action="{{ route('admin.returns.update', [$x, 'reject']) }}" class="space-y-1">@csrf
          <input name="note" required placeholder="سبب الرفض للعميل" class="w-full h-10 rounded-lg border px-2 text-sm">
          <button class="w-full h-9 rounded-lg bg-red-700 text-white text-sm">تأكيد الرفض</button>
        </form>
      @endif
    </div>
    @endcan
  </article>
@empty
  <div class="bg-white rounded-2xl border border-dashed border-line p-10 text-center text-neutral-500">لا توجد طلبات.</div>
@endforelse
</div>
<div class="mt-4">{{ $returns->links() }}</div>
@endsection
