@extends('shop.layout')
@section('title', $part->name_ar)
@section('content')
@php $grades = ['oem' => 'أصلي وكالة', 'cert' => 'تجاري بديل معتمد', 'first' => 'درجة أولى']; @endphp
<nav class="text-sm mb-3"><a href="{{ route('shop.index') }}" class="text-brandDark font-semibold">← الكتالوج</a></nav>
<div class="grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl border border-line aspect-[4/3] grid place-items-center text-neutral-400 overflow-hidden">@if ($part->image)<img src="{{ asset('storage/'.$part->image) }}" alt="{{ $part->name_ar }}" class="w-full h-full object-cover">@else{{ $part->category?->name_ar }}@endif</div>
  <div class="space-y-3">
    <h1 class="font-kufi text-2xl font-extrabold">{{ $part->name_ar }}</h1>
    <div class="text-sm text-neutral-600">{{ $part->brand }} · <span dir="ltr">{{ $part->sku }}</span> · {{ $grades[$part->grade] ?? $part->grade }}</div>
    <div class="num text-3xl font-bold">{{ number_format($part->price, 2) }} <span class="text-base font-normal">ر.س شامل الضريبة</span></div>
    @if ($part->is_bulky)<p class="text-sm text-amber-800 bg-amber-50 rounded-xl p-3">قطعة كبيرة — رسوم شحن إضافية {{ number_format($part->shipping_fee, 2) }} ر.س.</p>@endif
    @if ($part->available > 0)
      <p class="text-sm {{ $part->available <= 3 ? 'text-amber-800' : 'text-emerald-800' }} font-semibold">{{ $part->available <= 3 ? 'كمية محدودة — متبقي '.$part->available : 'متوفر' }}</p>
      <form method="post" action="{{ route('shop.cart.add') }}" class="flex gap-2">@csrf<input type="hidden" name="part_id" value="{{ $part->id }}">
        <input type="number" name="qty" value="1" min="1" max="{{ min(99, $part->available) }}" aria-label="الكمية" class="w-24 h-12 rounded-xl border px-3 num">
        <button class="h-12 px-6 rounded-xl bg-brand text-ink font-bold">أضف للسلة</button>
        <button name="buy_now" value="1" class="h-12 px-6 rounded-xl bg-ink text-white font-bold">اشترِ الآن</button></form>
    @else<p class="text-red-800 font-semibold">غير متوفر حالياً</p>@endif
    @if ($part->description)<p class="text-sm leading-7">{{ $part->description }}</p>@endif
    @if ($variants->count() > 1)
      <div><h2 class="font-semibold mb-1 text-sm">خيارات أخرى لنفس القطعة</h2><div class="flex flex-wrap gap-2">@foreach ($variants as $v)<a href="{{ route('shop.part', $v) }}" class="px-3 min-h-[44px] inline-flex items-center rounded-xl border {{ $v->id === $part->id ? 'bg-ink text-white' : 'bg-white' }} text-sm">{{ $grades[$v->grade] ?? $v->grade }}{{ $v->side ? ' · '.($v->side === 'left' ? 'يسار' : ($v->side === 'right' ? 'يمين' : $v->side)) : '' }}</a>@endforeach</div></div>
    @endif
  </div>
</div>
@if ($part->fitments->count())
<section class="mt-8 bg-white rounded-2xl border border-line p-5"><h2 class="font-bold mb-2">السيارات المتوافقة</h2>
  <ul class="grid sm:grid-cols-2 gap-1 text-sm">@foreach ($part->fitments as $f)<li>{{ $f->carModel?->make?->name_ar }} {{ $f->carModel?->name_ar }} <span class="num text-neutral-500">{{ $f->year_from }}–{{ $f->year_to }}</span></li>@endforeach</ul></section>
@endif
@endsection
