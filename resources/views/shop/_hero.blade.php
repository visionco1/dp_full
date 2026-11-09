{{-- سلايدر البانرات الرئيسي --}}
@php $slides = ($banners ?? collect())->get('hero', collect()); @endphp
@if ($slides->isNotEmpty())
<section class="max-w-6xl mx-auto w-full px-4 pt-6" aria-roledescription="carousel" aria-label="العروض"
  x-data="{ i: 0, n: {{ $slides->count() }}, t: null, go(k) { this.i = (k + this.n) % this.n }, play() { clearInterval(this.t); if (this.n > 1) this.t = setInterval(() => this.go(this.i + 1), 6000) } }"
  x-init="play()" @mouseenter="clearInterval(t)" @mouseleave="play()">
  <div class="relative rounded-3xl overflow-hidden aspect-[16/9] sm:aspect-[16/6] bg-ink">
    @foreach ($slides as $k => $s)
      <div x-show="i === {{ $k }}" @if ($k) x-cloak @endif
           x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
           class="absolute inset-0" style="background: {{ $s->bg_color }}" aria-roledescription="slide" aria-label="{{ $k + 1 }} من {{ $slides->count() }}">
        @if ($s->image)<img src="{{ $s->imageUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover" @if ($k) loading="lazy" @endif>@endif
        <div class="absolute inset-0 bg-gradient-to-l from-black/70 via-black/30 to-transparent"></div>
        <div class="relative h-full flex flex-col justify-center gap-3 px-6 sm:px-12 max-w-xl text-white">
          <h2 class="font-kufi font-extrabold text-2xl sm:text-4xl leading-tight">{{ $s->title }}</h2>
          @if ($s->subtitle)<p class="text-sm sm:text-lg text-white/90">{{ $s->subtitle }}</p>@endif
          @if ($s->link_url)
            <a href="{{ route('shop.banner', $s) }}" class="self-start mt-1 h-11 sm:h-12 px-6 inline-flex items-center rounded-xl bg-brand text-ink font-bold hover:brightness-105">{{ $s->button_text ?: 'تسوّق الآن' }}</a>
          @endif
        </div>
      </div>
    @endforeach

    @if ($slides->count() > 1)
      <button type="button" @click="go(i - 1); play()" class="absolute right-3 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/85 text-ink grid place-items-center hover:bg-white" aria-label="السابق">›</button>
      <button type="button" @click="go(i + 1); play()" class="absolute left-3 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/85 text-ink grid place-items-center hover:bg-white" aria-label="التالي">‹</button>
      <div class="absolute bottom-3 inset-x-0 flex justify-center gap-2">
        @foreach ($slides as $k => $s)
          <button type="button" @click="go({{ $k }}); play()" class="h-2.5 rounded-full transition-all" :class="i === {{ $k }} ? 'w-8 bg-brand' : 'w-2.5 bg-white/70'" aria-label="الشريحة {{ $k + 1 }}"></button>
        @endforeach
      </div>
    @endif
  </div>
</section>
@endif
