{{-- إعلان واحد: $ad (Banner) و $variant = strip | side --}}
@php $tall = ($variant ?? 'strip') === 'side'; @endphp
<{{ $ad->link_url ? 'a' : 'div' }} @if ($ad->link_url) href="{{ route('shop.banner', $ad) }}" @endif
  class="group relative block overflow-hidden rounded-2xl text-white {{ $tall ? 'aspect-[3/4]' : 'aspect-[16/7] sm:aspect-[16/4]' }}" style="background: {{ $ad->bg_color }}">
  @if ($ad->image)<img src="{{ $ad->imageUrl() }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500">@endif
  <div class="absolute inset-0 {{ $tall ? 'bg-gradient-to-t' : 'bg-gradient-to-l' }} from-black/70 via-black/20 to-transparent"></div>
  <div class="relative h-full flex flex-col {{ $tall ? 'justify-end' : 'justify-center max-w-md' }} gap-2 p-5 sm:p-8">
    <span class="self-start text-[11px] rounded-full bg-white/20 backdrop-blur px-2 py-0.5">إعلان</span>
    <b class="font-kufi text-xl sm:text-2xl leading-snug">{{ $ad->title }}</b>
    @if ($ad->subtitle)<span class="text-sm text-white/90">{{ $ad->subtitle }}</span>@endif
    @if ($ad->link_url)<span class="self-start mt-1 h-10 px-4 inline-flex items-center rounded-xl bg-brand text-ink font-bold text-sm">{{ $ad->button_text ?: 'اكتشف العرض' }}</span>@endif
  </div>
</{{ $ad->link_url ? 'a' : 'div' }}>
