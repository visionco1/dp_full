@extends('shop.layout')
@section('title', 'تتبّع الطلب')
@section('content')
<form method="post" action="{{ route('shop.track.find') }}" class="max-w-md mx-auto bg-white rounded-2xl border border-line p-6 space-y-3">@csrf
  <h1 class="font-kufi text-xl font-extrabold">تتبّع طلبك</h1>
  <label class="block text-sm">رقم الطلب<input name="number" value="{{ old('number') }}" required dir="ltr" placeholder="DM-261009-0318" class="mt-1 w-full h-12 rounded-xl border px-3 font-mono"></label>
  <label class="block text-sm">رقم الجوال المستخدم في الطلب<input name="phone" value="{{ old('phone') }}" required inputmode="tel" dir="ltr" class="mt-1 w-full h-12 rounded-xl border px-3"></label>
  <button class="w-full h-12 rounded-xl bg-brand text-ink font-bold">تتبّع</button>
</form>
@endsection
