@extends('admin.print._base')
@section('title', 'فاتورة ضريبية')
@section('body')
@foreach ($orders as $o)
@php $net = round($o->total - $o->vat, 2); @endphp
<div class="page">
  <div class="hdr">
    <div><h1 style="margin:0">فاتورة ضريبية مبسّطة</h1><div class="muted">Tax Invoice</div></div>
    <div style="text-align:left"><b>{{ config('dp.seller_name') }}</b><br>الرقم الضريبي: <span class="num">{{ config('dp.seller_vat_number') }}</span></div>
  </div>
  <div class="box">
    <div>رقم الفاتورة/الطلب: <b class="num">{{ $o->number }}</b></div>
    <div>التاريخ: <span class="num">{{ $o->placed_at->format('Y-m-d H:i') }}</span></div>
    <div>العميل: {{ $o->customer_name }} @if($o->customer_phone)— <span class="num">{{ $o->customer_phone }}</span>@endif</div>
  </div>
  <table>
    <thead><tr><th>#</th><th>الصنف</th><th>الكمية</th><th>سعر الوحدة (شامل)</th><th>الإجمالي (شامل)</th></tr></thead>
    <tbody>@foreach ($o->items as $i => $it)<tr><td>{{ $i + 1 }}</td><td>{{ $it->name }} <span class="num muted">{{ $it->sku }}</span></td><td class="num">{{ $it->qty }}</td><td class="num">{{ number_format($it->unit_price, 2) }}</td><td class="num">{{ number_format($it->line_total, 2) }}</td></tr>@endforeach</tbody>
  </table>
  <table style="width:300px;margin-top:10px;margin-right:auto">
    <tr><td>الإجمالي قبل الضريبة</td><td class="num">{{ number_format($net, 2) }}</td></tr>
    <tr><td>ضريبة القيمة المضافة 15%</td><td class="num">{{ number_format($o->vat, 2) }}</td></tr>
    <tr><td>الشحن (مضمّن)</td><td class="num">{{ number_format($o->shipping, 2) }}</td></tr>
    <tr><th>الإجمالي شامل الضريبة (ر.س)</th><th class="num">{{ number_format($o->total, 2) }}</th></tr>
  </table>
  <p class="muted" style="margin-top:14px">طريقة الدفع: {{ ['cod' => 'عند الاستلام', 'bank' => 'تحويل بنكي', 'cash' => 'نقداً', 'card' => 'بطاقة'][$o->payment_method] ?? $o->payment_method }}. الأسعار شاملة ضريبة القيمة المضافة.</p>
</div>
@endforeach
@endsection
