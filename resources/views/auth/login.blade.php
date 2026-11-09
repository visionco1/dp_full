<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دخول الموظفين — دفع المحركات</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Noto+Kufi+Arabic:wght@700;800&display=swap">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:"IBM Plex Sans Arabic",Tahoma,sans-serif}</style>
</head>
<body class="min-h-screen bg-[#111] flex items-center justify-center p-4">
  <form method="post" action="{{ route('admin.login') }}" class="w-full max-w-md bg-white rounded-3xl p-8 space-y-5 shadow-2xl">
    @csrf
    <div>
      <div class="font-extrabold text-2xl" style="font-family:'Noto Kufi Arabic'">دخول الموظفين</div>
      <p class="text-sm text-neutral-600 mt-1">لوحة تحكم دفع المحركات ونقطة البيع</p>
    </div>
    @if ($errors->any())
      <div role="alert" class="rounded-xl bg-red-50 border border-red-200 text-red-900 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif
    <label class="block">
      <span class="text-sm font-semibold">البريد الإلكتروني</span>
      <input name="email" type="email" value="{{ old('email') }}" required autofocus dir="ltr" class="mt-1 w-full h-12 rounded-xl border border-neutral-300 px-4 text-left focus:outline-none focus:ring-2 focus:ring-[#FF6B00]">
    </label>
    <label class="block">
      <span class="text-sm font-semibold">كلمة المرور</span>
      <input name="password" type="password" required dir="ltr" class="mt-1 w-full h-12 rounded-xl border border-neutral-300 px-4 text-left focus:outline-none focus:ring-2 focus:ring-[#FF6B00]">
    </label>
    <label class="flex items-center gap-2 text-sm min-h-[44px]"><input type="checkbox" name="remember" class="w-5 h-5 accent-black"> تذكّرني على هذا الجهاز</label>
    <button class="w-full h-14 rounded-2xl bg-[#FF6B00] text-black font-extrabold text-base">دخول</button>
    @if (app()->environment('local'))
      <p class="text-xs text-neutral-500 text-center" dir="ltr">demo: admin@store.example / password</p>
    @endif
  </form>
</body>
</html>
