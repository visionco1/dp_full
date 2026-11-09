@extends('admin.print._base')
@section('title', 'بوليصة شحن')
@section('body')
@foreach ($orders as $o)
@php $a = $o->address ?? []; $cod = $o->payment_method === 'cod'; @endphp
<div class="page">
  <div class="hdr"><div><h1 style="margin:0">بوليصة شحن</h1><div class="muted">Waybill</div></div><div style="text-align:left"><b>{{ config('dp.seller_name') }}</b></div></div>
  <div class="box"><div class="bar">{{ $o->waybill_no ?: '—' }}</div><div style="text-align:center" class="num">{{ $o->number }}</div></div>
  <div class="box"><b>المستلم</b><div style="font-size:16px">{{ $o->customer_name }}</div><div class="num">{{ $o->customer_phone }}</div>
    <div>{{ $a['city'] ?? '' }} — {{ $a['line'] ?? '' }}</div>@if (!empty($a['national_address']))<div>العنوان الوطني: <span class="num">{{ $a['national_address'] }}</span></div>@endif</div>
  <table><tr><th>عدد القطع</th><td class="num">{{ $o->items->sum('qty') }}</td><th>الدفع</th><td>{{ $cod ? 'عند الاستلام' : 'مدفوع مسبقاً' }}</td></tr>
    <tr><th>المبلغ المطلوب تحصيله</th><td colspan="3" class="num" style="font-size:18px"><b>{{ $cod ? number_format($o->total, 2).' ر.س' : '0.00 ر.س' }}</b></td></tr></table>
  <table style="margin-top:10px"><thead><tr><th>الصنف</th><th>الكمية</th></tr></thead><tbody>@foreach ($o->items as $it)<tr><td>{{ $it->name }} <span class="num muted">{{ $it->sku }}</span></td><td class="num">{{ $it->qty }}</td></tr>@endforeach</tbody></table>
</div>
@endforeach
@endsection
