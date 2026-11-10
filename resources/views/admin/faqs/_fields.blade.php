@php $field = 'mt-1 w-full rounded-xl border border-line px-3 bg-white'; @endphp
<label class="block text-sm font-semibold">السؤال<input name="question" value="{{ $f->question ?? '' }}" required maxlength="250" class="{{ $field }} h-11"></label>
<label class="block text-sm font-semibold">الإجابة<textarea name="answer" required maxlength="3000" rows="4" class="{{ $field }} py-2">{{ $f->answer ?? '' }}</textarea></label>
<div class="grid grid-cols-2 gap-3">
  <label class="block text-sm font-semibold">التصنيف<input name="category" value="{{ $f->category ?? '' }}" maxlength="40" placeholder="الطلبات" class="{{ $field }} h-11"></label>
  <label class="block text-sm font-semibold">الترتيب<input type="number" name="sort" min="0" max="999" value="{{ $f->sort ?? 0 }}" class="{{ $field }} h-11 num"></label>
</div>
<label class="flex items-center gap-2 text-sm min-h-[44px]"><input type="checkbox" name="active" value="1" class="w-5 h-5 accent-black" @checked($f->active ?? true)>ظاهر في التطبيق</label>
