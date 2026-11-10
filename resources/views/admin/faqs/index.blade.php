@extends('layouts.admin')
@section('title', 'الأسئلة الشائعة')
@section('heading', 'الأسئلة الشائعة')
@section('content')
@php $field = 'mt-1 w-full rounded-xl border border-line px-3 bg-white'; @endphp
<div class="grid gap-6 xl:grid-cols-3">
  <section class="xl:col-span-1">
    <form method="post" action="{{ route('admin.faqs.store') }}" class="bg-white rounded-2xl border border-line p-5 space-y-3 xl:sticky xl:top-6">
      @csrf
      <h2 class="font-bold text-lg">سؤال جديد</h2>
      @include('admin.faqs._fields', ['f' => null])
      <button class="w-full h-11 rounded-xl bg-brand text-ink font-bold">إضافة</button>
      <p class="text-xs text-neutral-500">تظهر الأسئلة المفعّلة في شاشة الدعم بالتطبيق حسب الترتيب.</p>
    </form>
  </section>

  <section class="xl:col-span-2 space-y-3">
    @forelse ($faqs as $f)
      <article x-data="{ edit: false }" class="bg-white rounded-2xl border border-line">
        <div class="p-4 flex flex-wrap gap-3 items-start">
          <span class="num text-xs w-8 h-8 rounded-lg bg-paper grid place-items-center shrink-0">{{ $f->sort }}</span>
          <div class="flex-1 min-w-[220px]">
            <div class="flex flex-wrap items-center gap-2">
              <b>{{ $f->question }}</b>
              @if ($f->category)<span class="text-xs rounded-full bg-paper px-2 py-0.5">{{ $f->category }}</span>@endif
              @unless ($f->active)<span class="text-xs rounded-full bg-neutral-200 px-2 py-0.5">مخفي</span>@endunless
            </div>
            <p class="text-sm text-neutral-600 mt-1 whitespace-pre-line">{{ $f->answer }}</p>
          </div>
          <div class="flex gap-2">
            <button type="button" @click="edit = !edit" class="h-10 px-4 rounded-xl border border-line text-sm font-semibold" x-text="edit ? 'إغلاق' : 'تعديل'">تعديل</button>
            <form method="post" action="{{ route('admin.faqs.destroy', $f) }}" onsubmit="return confirm('حذف السؤال؟')">@csrf @method('DELETE')<button class="h-10 px-4 rounded-xl text-red-800 text-sm font-semibold">حذف</button></form>
          </div>
        </div>
        <form x-show="edit" x-cloak method="post" action="{{ route('admin.faqs.update', $f) }}" class="border-t border-line p-4 space-y-3 bg-paper/50">
          @csrf @method('PUT')
          @include('admin.faqs._fields', ['f' => $f])
          <button class="h-11 px-6 rounded-xl bg-ink text-white font-semibold">حفظ</button>
        </form>
      </article>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-line p-10 text-center text-neutral-500">لا توجد أسئلة بعد.</div>
    @endforelse
  </section>
</div>
@endsection
