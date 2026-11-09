@extends('layouts.admin')
@section('title', 'الكتالوج')
@section('heading', 'الكتالوج والتوافق')
@section('actions')<a href="{{ route('admin.catalog.create') }}" class="h-11 px-4 rounded-xl bg-brand text-ink font-bold inline-flex items-center text-sm">+ قطعة جديدة</a>@endsection
@section('content')
<form method="get" class="flex gap-2 mb-4"><input name="q" value="{{ request('q') }}" placeholder="رقم القطعة أو الاسم" class="h-11 rounded-xl border px-4 flex-1"><button class="h-11 px-5 rounded-xl bg-ink text-white font-semibold">بحث</button></form>
<div class="bg-white rounded-2xl border border-line overflow-x-auto">
<table class="w-full text-sm min-w-[720px]"><thead class="bg-paper text-xs text-neutral-600"><tr><th class="p-3 text-right">رقم القطعة</th><th class="p-3 text-right">الاسم</th><th class="p-3 text-right">القسم</th><th class="p-3">الدرجة</th><th class="p-3">السعر</th><th class="p-3">السيارات</th><th class="p-3"></th></tr></thead><tbody>
@foreach ($parts as $p)
<tr class="border-t border-line"><td class="p-3 font-mono text-xs" dir="ltr">{{ $p->sku }}</td><td class="p-3">{{ $p->name_ar }}@if($p->side) {{ $p->side === 'R' ? 'يمين' : 'يسار' }}@endif @unless($p->active)<span class="text-xs text-red-700">· مخفية</span>@endunless</td>
<td class="p-3">{{ $p->category?->name_ar }}</td><td class="p-3 text-center">{{ \App\Http\Presenter::GRADES[$p->grade] }}</td><td class="p-3 text-center num">{{ number_format($p->price, 2) }}</td>
<td class="p-3 text-center"><span class="num {{ $p->fitments_count ? '' : 'text-red-700 font-bold' }}">{{ $p->fitments_count }}</span></td>
<td class="p-3"><a class="text-brandDark font-semibold" href="{{ route('admin.catalog.edit', $p) }}">تعديل / توافق</a></td></tr>
@endforeach</tbody></table></div>
<div class="mt-4">{{ $parts->links() }}</div>
@endsection
