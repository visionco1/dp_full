@extends('layouts.admin')
@section('title', $part->exists ? 'تعديل قطعة' : 'قطعة جديدة')
@section('heading', $part->exists ? 'تعديل: '.$part->sku : 'قطعة جديدة')
@section('content')
@php $isNew = !$part->exists; @endphp
<div class="grid gap-6 xl:grid-cols-2">
<form method="post" enctype="multipart/form-data" action="{{ $isNew ? route('admin.catalog.store') : route('admin.catalog.update', $part) }}" class="bg-white rounded-2xl border border-line p-5 grid gap-4 sm:grid-cols-2 self-start">
  @csrf @if(!$isNew) @method('PUT') @endif
  @php $v = fn ($k, $d = '') => old($k, $part->{$k} ?? $d); $in = 'mt-1 w-full h-11 rounded-xl border px-3'; @endphp
  <label class="text-sm font-semibold">رقم القطعة (OEM)<input name="sku" value="{{ $v('sku') }}" required dir="ltr" class="{{ $in }} font-mono text-left"></label>
  <label class="text-sm font-semibold">القسم<select name="category_id" class="{{ $in }}"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected($v('category_id') == $c->id)>{{ $c->name_ar }}</option>@foreach ($c->children as $s)<option value="{{ $s->id }}" @selected($v('category_id') == $s->id)>&nbsp;&nbsp;— {{ $s->name_ar }}</option>@endforeach @endforeach</select></label>
  <label class="text-sm font-semibold sm:col-span-2">الاسم بالعربي<input name="name_ar" value="{{ $v('name_ar') }}" required class="{{ $in }}"></label>
  <label class="text-sm font-semibold">الاسم بالإنجليزي<input name="name_en" value="{{ $v('name_en') }}" dir="ltr" class="{{ $in }} text-left"></label>
  <label class="text-sm font-semibold">العلامة<input name="brand" value="{{ $v('brand') }}" class="{{ $in }}"></label>
  <label class="text-sm font-semibold">الدرجة<select name="grade" class="{{ $in }}">@foreach (\App\Http\Presenter::GRADES as $k => $l)<option value="{{ $k }}" @selected($v('grade') === $k)>{{ $l }}</option>@endforeach</select></label>
  <label class="text-sm font-semibold">الجهة<select name="side" class="{{ $in }}"><option value="">—</option><option value="R" @selected($v('side') === 'R')>يمين</option><option value="L" @selected($v('side') === 'L')>يسار</option></select></label>
  <label class="text-sm font-semibold">السعر شامل الضريبة<input name="price" type="number" step="0.01" value="{{ $v('price') }}" required class="{{ $in }} num"></label>
  @can('inventory.cost')<label class="text-sm font-semibold">التكلفة<input name="cost" type="number" step="0.01" value="{{ $v('cost', 0) }}" class="{{ $in }} num"></label>@endcan
  <label class="text-sm font-semibold">مفتاح المجموعة <span class="font-normal text-neutral-500">(يربط يمين/يسار والدرجات)</span><input name="group_key" value="{{ $v('group_key') }}" dir="ltr" class="{{ $in }} text-left"></label>
  <div x-data="{ bulky: {{ $v('is_bulky') ? 'true' : 'false' }} }" class="flex items-end gap-3">
    <label class="flex items-center gap-2 text-sm font-semibold min-h-[44px]"><input type="checkbox" name="is_bulky" value="1" x-model="bulky" class="w-5 h-5 accent-black">قطعة كبيرة الحجم</label>
    <label class="text-sm font-semibold flex-1" x-show="bulky">رسوم شحنها<input name="shipping_fee" type="number" step="0.01" value="{{ $v('shipping_fee', 0) }}" class="{{ $in }} num"></label>
  </div>
  @if ($isNew)<label class="text-sm font-semibold">الكمية الافتتاحية<input name="on_hand" type="number" min="0" value="{{ old('on_hand', 0) }}" class="{{ $in }} num"></label>@endif
  <label class="text-sm font-semibold">صورة<input name="image" type="file" accept="image/*" class="{{ $in }} pt-2"></label>
  <label class="text-sm font-semibold sm:col-span-2">الوصف<textarea name="description" rows="3" class="mt-1 w-full rounded-xl border p-3">{{ $v('description') }}</textarea></label>
  <label class="flex items-center gap-2 text-sm font-semibold min-h-[44px]"><input type="checkbox" name="active" value="1" @checked($v('active', true)) class="w-5 h-5 accent-black">ظاهرة في التطبيق والموقع</label>
  <div class="sm:col-span-2 flex gap-2"><button class="h-12 px-6 rounded-xl bg-brand text-ink font-bold">حفظ</button><a href="{{ route('admin.catalog.index') }}" class="h-12 px-5 rounded-xl border inline-flex items-center font-semibold">رجوع</a></div>
</form>

@unless ($isNew)
<section class="bg-white rounded-2xl border border-line p-5 space-y-5 self-start" aria-labelledby="fit-h">
  <h2 id="fit-h" class="font-bold">التوافق مع السيارات</h2>
  <ul class="space-y-2">
    @forelse ($part->fitments as $f)
      <li class="flex items-center justify-between rounded-xl bg-paper px-3 py-2 text-sm">
        <span>{{ $f->carModel->make->name_ar }} {{ $f->carModel->name_ar }} <b class="num">{{ $f->year_from }}–{{ $f->year_to }}</b></span>
        <form method="post" action="{{ route('admin.catalog.fitments.remove', [$part, $f]) }}" onsubmit="return confirm('حذف هذا التوافق؟')">@csrf @method('DELETE')<button class="text-red-700 font-semibold min-h-[40px] px-2">حذف</button></form>
      </li>
    @empty <li class="text-sm text-red-700">لا توجد سيارات متوافقة — القطعة لن تظهر عند البحث بالسيارة.</li> @endforelse
  </ul>

  <form method="post" action="{{ route('admin.catalog.fitments.add', $part) }}" class="border-t border-line pt-4 space-y-3" data-url="{{ url('admin/catalog/makes') }}"
        x-data="{ models: [], loading: false, async load(id) { this.models = []; if (!id) return; this.loading = true; const r = await fetch(this.$root.dataset.url + '/' + id + '/models', { headers: { Accept: 'application/json' } }); this.models = await r.json(); this.loading = false; } }">
    @csrf
    <h3 class="text-sm font-bold">إضافة توافق: الشركة ← الموديلات ← السنوات</h3>
    <select class="w-full h-11 rounded-xl border px-3" @change="load($event.target.value)" aria-label="الشركة"><option value="">اختر الشركة</option>@foreach ($makes as $m)<option value="{{ $m->id }}">{{ $m->name_ar }}</option>@endforeach</select>
    <div class="flex flex-wrap gap-2" role="group" aria-label="الموديلات">
      <template x-for="m in models" :key="m.id"><label class="flex items-center gap-2 rounded-full border px-3 min-h-[40px] text-sm"><input type="checkbox" name="models[]" :value="m.id" class="accent-black"><span x-text="m.name_ar"></span></label></template>
      <span x-show="loading" class="text-sm text-neutral-500">جارٍ التحميل…</span>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <label class="text-sm">من سنة<input name="year_from" type="number" value="{{ old('year_from', 2018) }}" class="mt-1 w-full h-11 rounded-xl border px-3 num"></label>
      <label class="text-sm">إلى سنة<input name="year_to" type="number" value="{{ old('year_to', 2024) }}" class="mt-1 w-full h-11 rounded-xl border px-3 num"></label>
    </div>
    <button class="h-11 px-5 rounded-xl bg-ink text-white font-semibold">إضافة</button>
  </form>

  <form method="post" action="{{ route('admin.catalog.fitments.copy', $part) }}" class="border-t border-line pt-4 flex gap-2 items-end" onsubmit="return confirm('سيستبدل هذا كل التوافق الحالي. متابعة؟')">@csrf
    <label class="text-sm flex-1">نسخ التوافق من قطعة أخرى (رقمها)<input name="from_sku" dir="ltr" class="mt-1 w-full h-11 rounded-xl border px-3 font-mono text-left"></label>
    <button class="h-11 px-4 rounded-xl border font-semibold">نسخ</button>
  </form>
  <form method="post" action="{{ route('admin.catalog.destroy', $part) }}" onsubmit="return confirm('حذف القطعة؟')">@csrf @method('DELETE')<button class="text-red-700 text-sm font-semibold min-h-[40px]">حذف القطعة</button></form>
</section>
@endunless
</div>
@endsection
