{{-- صف قسم (رئيسي أو فرعي) مع نموذج تعديله. المتغيرات: $c, $roots, $field, $sub --}}
<div x-data="{ edit: false }" class="{{ $sub ? 'border-t border-line bg-paper/40' : '' }}">
  <div class="p-4 flex flex-wrap gap-4 items-center {{ $sub ? 'pr-10' : '' }}">
    @if ($sub)<span class="text-neutral-400 -mr-6" aria-hidden="true">↳</span>@endif
    <div class="{{ $sub ? 'w-10 h-10' : 'w-14 h-14' }} rounded-xl overflow-hidden bg-paper grid place-items-center text-neutral-400 shrink-0">
      @if ($c->image)<img src="{{ $c->imageUrl() }}" alt="" class="w-full h-full object-cover">@else<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16M4 12h16M4 19h10"/></svg>@endif
    </div>
    <div class="flex-1 min-w-[180px]">
      <b class="{{ $sub ? 'font-semibold' : '' }}">{{ $c->name_ar }}</b> @if ($c->name_en)<span class="text-sm text-neutral-500" dir="ltr">· {{ $c->name_en }}</span>@endif
      <div class="text-xs text-neutral-500 mt-1">
        <span class="font-mono" dir="ltr">{{ $c->slug }}</span> · ترتيب <span class="num">{{ $c->sort }}</span> ·
        <a href="{{ route('admin.catalog.index', ['category' => $c->id]) }}" class="num underline-offset-2 hover:underline">{{ $c->parts_count }} قطعة{{ !$sub && $c->children->isNotEmpty() ? ' مباشرة' : '' }}</a>
        @if (!$sub && $c->children->isNotEmpty()) · <span class="num">{{ $c->children->count() }}</span> قسم فرعي @endif
      </div>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('shop.index', ['category' => $c->slug]) }}" target="_blank" class="h-10 px-3 inline-flex items-center rounded-xl text-sm text-neutral-600">عرض ↗</a>
      <button type="button" @click="edit = !edit" class="h-10 px-4 rounded-xl border border-line text-sm font-semibold bg-white" x-text="edit ? 'إغلاق' : 'تعديل'">تعديل</button>
      @if ($c->parts_count === 0 && ($sub || $c->children->isEmpty()))
        <form method="post" action="{{ route('admin.catalog.categories.destroy', $c) }}" onsubmit="return confirm('حذف القسم؟')">@csrf @method('DELETE')<button class="h-10 px-4 rounded-xl text-red-800 text-sm font-semibold">حذف</button></form>
      @endif
    </div>
  </div>
  <form x-show="edit" x-cloak method="post" action="{{ route('admin.catalog.categories.update', $c) }}" enctype="multipart/form-data" class="border-t border-line p-4 grid sm:grid-cols-2 gap-3 bg-paper/60">
    @csrf @method('PUT')
    <label class="block text-sm font-semibold">الاسم بالعربي<input name="name_ar" value="{{ $c->name_ar }}" required maxlength="60" class="{{ $field }}"></label>
    <label class="block text-sm font-semibold">الاسم بالإنجليزي<input name="name_en" value="{{ $c->name_en }}" maxlength="60" dir="ltr" class="{{ $field }}"></label>
    <label class="block text-sm font-semibold">يتبع القسم الرئيسي
      <select name="parent_id" class="{{ $field }}" @disabled(!$sub && $c->children->isNotEmpty())>
        <option value="">— قسم رئيسي —</option>
        @foreach ($roots as $r)@continue($r->id === $c->id)<option value="{{ $r->id }}" @selected($c->parent_id === $r->id)>{{ $r->name_ar }}</option>@endforeach
      </select>
      @if (!$sub && $c->children->isNotEmpty())<span class="text-xs font-normal text-neutral-500">قسم فيه أقسام فرعية يبقى رئيسياً</span>@endif
    </label>
    <label class="block text-sm font-semibold">المعرّف (slug)<input name="slug" value="{{ $c->slug }}" maxlength="60" dir="ltr" class="{{ $field }} font-mono text-sm"></label>
    <label class="block text-sm font-semibold">الترتيب<input type="number" name="sort" min="0" max="999" value="{{ $c->sort }}" class="{{ $field }} num"></label>
    <label class="block text-sm font-semibold">تغيير الصورة<input type="file" name="image" accept="image/*" class="mt-1 w-full text-sm"></label>
    @if ($c->image)<label class="flex items-center gap-2 text-sm min-h-[44px]"><input type="checkbox" name="remove_image" value="1" class="w-5 h-5 accent-black">حذف الصورة الحالية</label>@endif
    <p class="sm:col-span-2 text-xs text-amber-800">تغيير المعرّف يغيّر روابط القسم في الموقع والبانرات التي تشير إليه.</p>
    <div class="sm:col-span-2"><button class="h-11 px-6 rounded-xl bg-ink text-white font-semibold">حفظ</button></div>
  </form>
</div>
