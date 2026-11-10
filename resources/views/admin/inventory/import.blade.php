@extends('layouts.admin')
@section('title', 'معاينة الاستيراد')
@section('heading', 'معاينة استيراد المخزون — '.($branch->name ?? ''))
@section('content')
<p class="text-sm text-neutral-600 mb-4">الملف: <span class="font-mono" dir="ltr">{{ $file }}</span> — <b class="num">{{ count($changes) }}</b> تغيير صالح، <b class="num {{ count($errors) ? 'text-red-700' : '' }}">{{ count($errors) }}</b> خطأ (سيتم تخطيها).</p>
@if ($alerts)<div class="mb-4 rounded-xl bg-orange-50 border border-orange-200 px-4 py-3 text-sm">سيصل تنبيه «عادت للمخزون» إلى <b class="num">{{ $alerts }}</b> عميل مشترك في «أعلمني عند التوفر».</div>@endif

<div x-data="{ tab: 'changes' }" class="space-y-4">
  <div role="tablist" class="inline-flex bg-white border rounded-xl p-1 gap-1">
    <button role="tab" :aria-selected="tab==='changes'" @click="tab='changes'" :class="tab==='changes' ? 'bg-ink text-white' : ''" class="h-10 px-4 rounded-lg text-sm font-semibold">التغييرات ({{ count($changes) }})</button>
    <button role="tab" :aria-selected="tab==='errors'" @click="tab='errors'" :class="tab==='errors' ? 'bg-ink text-white' : ''" class="h-10 px-4 rounded-lg text-sm font-semibold">الأخطاء ({{ count($errors) }})</button>
  </div>

  <div x-show="tab==='changes'" class="bg-white border border-line rounded-2xl overflow-x-auto">
    <table class="w-full text-sm min-w-[600px]"><thead class="bg-paper text-xs text-neutral-600"><tr><th class="p-3 text-right">القطعة</th><th class="p-3">الكمية</th><th class="p-3">السعر</th></tr></thead><tbody>
    @forelse ($changes as $c)
      <tr class="border-t border-line"><td class="p-3"><span class="font-mono text-xs" dir="ltr">{{ $c['sku'] }}</span><br>{{ $c['name'] }}@if($c['restock']) <span class="text-xs text-emerald-700 font-bold">· عادت للمخزون</span>@endif</td>
        <td class="p-3 text-center num">{{ $c['old'] }} ← <b>{{ $c['new'] }}</b></td>
        <td class="p-3 text-center num">{{ $c['price'] !== null && $c['price'] != $c['old_price'] ? number_format($c['old_price'], 2).' ← '.number_format($c['price'], 2) : '—' }}</td></tr>
    @empty <tr><td colspan="3" class="p-6 text-center text-neutral-500">لا توجد تغييرات.</td></tr> @endforelse
    </tbody></table>
  </div>
  <div x-show="tab==='errors'" x-cloak class="bg-white border border-line rounded-2xl overflow-x-auto">
    <table class="w-full text-sm min-w-[500px]"><thead class="bg-paper text-xs text-neutral-600"><tr><th class="p-3 text-right">السطر</th><th class="p-3 text-right">رقم القطعة</th><th class="p-3 text-right">المشكلة</th></tr></thead><tbody>
    @forelse ($errors as $e)<tr class="border-t border-line"><td class="p-3 num">{{ $e['line'] }}</td><td class="p-3 font-mono text-xs" dir="ltr">{{ $e['sku'] ?: '—' }}</td><td class="p-3 text-red-800">{{ $e['msg'] }}</td></tr>
    @empty <tr><td colspan="3" class="p-6 text-center text-neutral-500">لا توجد أخطاء.</td></tr> @endforelse
    </tbody></table>
  </div>

  <form method="post" action="{{ route('admin.inventory.import.apply') }}" class="flex gap-2">@csrf
    <input type="hidden" name="id" value="{{ $id }}">
    <button @disabled(!count($changes)) class="h-12 px-6 rounded-xl bg-brand text-ink font-bold disabled:opacity-40">تطبيق {{ count($changes) }} تغيير@if(count($errors)) وتخطّي {{ count($errors) }} خطأ@endif</button>
    <a href="{{ route('admin.inventory.index') }}" class="h-12 px-5 rounded-xl border bg-white inline-flex items-center font-semibold">إلغاء</a>
  </form>
</div>
@endsection
