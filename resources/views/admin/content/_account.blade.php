@php $field = 'mt-1 w-full h-11 rounded-xl border border-line px-3 bg-white'; @endphp
<label class="block text-sm font-semibold">اسم البنك<input name="bank_name" value="{{ $a->bank_name ?? '' }}" required maxlength="80" placeholder="مصرف الإنماء" class="{{ $field }}"></label>
<label class="block text-sm font-semibold">اسم المستفيد<input name="beneficiary_name" value="{{ $a->beneficiary_name ?? '' }}" required maxlength="120" class="{{ $field }}"></label>
<label class="block text-sm font-semibold">الآيبان<input name="iban" value="{{ $a->iban ?? '' }}" required dir="ltr" placeholder="SA0000000000000000000000" class="{{ $field }} font-mono"></label>
<div class="grid grid-cols-2 gap-2">
  <label class="block text-sm font-semibold">رقم الحساب<input name="account_number" value="{{ $a->account_number ?? '' }}" maxlength="30" dir="ltr" class="{{ $field }} font-mono"></label>
  <label class="block text-sm font-semibold">الترتيب<input type="number" name="sort" min="0" max="999" value="{{ $a->sort ?? 0 }}" class="{{ $field }} num"></label>
</div>
<label class="flex items-center gap-2 text-sm min-h-[40px]"><input type="checkbox" name="active" value="1" class="w-5 h-5 accent-black" @checked($a->active ?? true)>ظاهر للعملاء</label>
