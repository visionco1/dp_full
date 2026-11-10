@php $field = 'mt-1 w-full h-11 rounded-xl border border-line px-3 bg-white'; @endphp
<label class="block text-sm font-semibold">اسم الفرع<input name="name" value="{{ $b->name ?? old('name') }}" required maxlength="80" placeholder="فرع جدة" class="{{ $field }}"></label>
<label class="block text-sm font-semibold">الكود<input name="code" value="{{ $b->code ?? old('code') }}" required maxlength="20" dir="ltr" placeholder="JED" class="{{ $field }} font-mono uppercase"></label>
<label class="block text-sm font-semibold">المدينة<input name="city" value="{{ $b->city ?? old('city') }}" required maxlength="60" class="{{ $field }}"></label>
<label class="block text-sm font-semibold">الجوال / الهاتف<input name="phone" value="{{ $b->phone ?? old('phone') }}" maxlength="20" dir="ltr" class="{{ $field }}"></label>
<label class="block text-sm font-semibold sm:col-span-2">العنوان<input name="address" value="{{ $b->address ?? old('address') }}" maxlength="255" class="{{ $field }}"></label>
<label class="block text-sm font-semibold sm:col-span-2">ساعات العمل<input name="hours" value="{{ $b->hours ?? old('hours') }}" maxlength="120" placeholder="السبت – الخميس 9 ص – 10 م" class="{{ $field }}"></label>
<div class="grid grid-cols-3 gap-2 sm:col-span-2">
  <label class="block text-sm font-semibold">خط العرض<input name="lat" value="{{ $b->lat ?? old('lat') }}" inputmode="decimal" dir="ltr" placeholder="24.7136" class="{{ $field }} num"></label>
  <label class="block text-sm font-semibold">خط الطول<input name="lng" value="{{ $b->lng ?? old('lng') }}" inputmode="decimal" dir="ltr" placeholder="46.6753" class="{{ $field }} num"></label>
  <label class="block text-sm font-semibold">الترتيب<input type="number" name="sort" min="0" max="999" value="{{ $b->sort ?? 0 }}" class="{{ $field }} num"></label>
</div>
<div class="sm:col-span-2 flex flex-wrap gap-x-5">
  <label class="flex items-center gap-2 text-sm min-h-[44px]"><input type="checkbox" name="pickup_enabled" value="1" class="w-5 h-5 accent-black" @checked($b->pickup_enabled ?? true)>يدعم الاستلام من الفرع</label>
  <label class="flex items-center gap-2 text-sm min-h-[44px]"><input type="checkbox" name="delivery_enabled" value="1" class="w-5 h-5 accent-black" @checked($b->delivery_enabled ?? true)>يدعم التوصيل</label>
  <label class="flex items-center gap-2 text-sm min-h-[44px]"><input type="checkbox" name="active" value="1" class="w-5 h-5 accent-black" @checked($b->active ?? true)>مفعّل</label>
</div>
