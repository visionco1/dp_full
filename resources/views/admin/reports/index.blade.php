@extends('layouts.admin')
@section('title', 'التقارير')
@section('heading', 'التقارير والتصدير')
@section('content')
@unless ($unmask)<div class="mb-4 rounded-xl bg-amber-50 text-amber-900 p-3 text-sm">أرقام جوالات العملاء ستظهر مقنّعة في ملفاتك (صلاحيتك لا تشمل بيانات العملاء الكاملة).</div>@endunless
<div class="grid gap-4 md:grid-cols-2">
@foreach ($types as $key => [$title, $desc])
  <form method="get" action="{{ route('admin.reports.export', $key) }}" class="bg-white rounded-2xl border border-line p-5 space-y-3">
    <h2 class="font-bold text-lg">{{ $title }}</h2>
    <p class="text-sm text-neutral-600">{{ $desc }}</p>
    @if (in_array($key, ['sales', 'movements']))
    <div class="flex gap-2">
      <label class="text-xs flex-1">من<input type="date" name="from" value="{{ now()->subDays(30)->toDateString() }}" class="block w-full h-11 rounded-xl border px-3"></label>
      <label class="text-xs flex-1">إلى<input type="date" name="to" value="{{ now()->toDateString() }}" class="block w-full h-11 rounded-xl border px-3"></label>
    </div>
    @endif
    <div class="flex gap-2">
      <button name="format" value="xlsx" class="h-11 px-5 rounded-xl bg-ink text-white font-semibold">Excel</button>
      <button name="format" value="csv" class="h-11 px-5 rounded-xl border font-semibold">CSV</button>
    </div>
  </form>
@endforeach
</div>
@endsection
