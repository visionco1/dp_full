@php
  $field = 'mt-1 w-full h-11 rounded-xl border border-line px-3 bg-white';
  $dt = fn ($v) => $v?->format('Y-m-d\TH:i');
@endphp
<label class="block text-sm font-semibold">المكان
  <select name="placement" class="{{ $field }}" required>
    @foreach (\App\Models\Banner::PLACEMENTS as $k => $label)<option value="{{ $k }}" @selected(($b->placement ?? 'hero') === $k)>{{ $label }}</option>@endforeach
  </select></label>
<label class="block text-sm font-semibold">العنوان<input name="title" value="{{ $b->title ?? '' }}" required maxlength="120" class="{{ $field }}" placeholder="خصم 20% على الفلاتر"></label>
<label class="block text-sm font-semibold sm:col-span-2">النص الفرعي<input name="subtitle" value="{{ $b->subtitle ?? '' }}" maxlength="250" class="{{ $field }}" placeholder="لفترة محدودة على كل فلاتر الزيت والهواء"></label>
<label class="block text-sm font-semibold">الرابط<input name="link_url" value="{{ $b->link_url ?? '' }}" dir="ltr" class="{{ $field }}" placeholder="/?category=filters"></label>
<label class="block text-sm font-semibold">نص الزر<input name="button_text" value="{{ $b->button_text ?? '' }}" maxlength="40" class="{{ $field }}" placeholder="تسوّق الآن"></label>
<label class="block text-sm font-semibold">الصورة
  <input type="file" name="image" accept="image/*" class="mt-1 w-full text-sm"></label>
@if ($b?->image)<label class="flex items-center gap-2 text-sm min-h-[44px]"><input type="checkbox" name="remove_image" value="1" class="w-5 h-5 accent-black">حذف الصورة الحالية</label>@endif
<div class="grid grid-cols-2 gap-3 sm:col-span-2">
  <label class="block text-sm font-semibold">لون الخلفية<input type="color" name="bg_color" value="{{ $b->bg_color ?? '#111111' }}" class="mt-1 w-full h-11 rounded-xl border border-line px-1 bg-white"></label>
  <label class="block text-sm font-semibold">الترتيب<input type="number" name="sort" min="0" max="999" value="{{ $b->sort ?? 0 }}" class="{{ $field }} num"></label>
  <label class="block text-sm font-semibold">يبدأ<input type="datetime-local" name="starts_at" value="{{ $dt($b?->starts_at) }}" class="{{ $field }}"></label>
  <label class="block text-sm font-semibold">ينتهي<input type="datetime-local" name="ends_at" value="{{ $dt($b?->ends_at) }}" class="{{ $field }}"></label>
</div>
<label class="flex items-center gap-2 text-sm min-h-[44px] sm:col-span-2"><input type="checkbox" name="active" value="1" class="w-5 h-5 accent-black" @checked($b->active ?? true)>مفعّل</label>
