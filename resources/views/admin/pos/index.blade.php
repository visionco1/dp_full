@extends('layouts.admin')
@section('title', 'نقطة البيع')
@section('heading', 'نقطة البيع — المعرض')
@section('content')
<div x-data="pos()" x-init="$refs.q.focus()" class="grid gap-4 xl:grid-cols-5">
  <section class="xl:col-span-3 bg-white rounded-2xl border border-line p-5 space-y-4" aria-labelledby="pos-search">
    <h2 id="pos-search" class="font-bold">بحث سريع بالرقم أو الاسم</h2>
    <div class="flex gap-2">
      <input x-ref="q" x-model="q" @input.debounce.200ms="search()" @keydown.enter.prevent="addFirst()" type="search" dir="auto" placeholder="رقم OEM أو اسم القطعة — اضغط Enter لإضافة أول نتيجة"
             class="flex-1 h-14 rounded-xl border-2 border-ink px-4 text-lg focus:outline-none focus:ring-2 focus:ring-brand" aria-label="بحث عن قطعة">
      <button type="button" @click="scan()" class="h-14 px-4 rounded-xl border border-neutral-300 text-sm font-semibold" title="يستقبل قارئ الباركود كأنه لوحة مفاتيح">مسح باركود</button>
    </div>
    <p class="text-xs text-neutral-500" x-show="scanning" x-cloak>جاهز للمسح — وجّه القارئ للباركود وسيُضاف الصنف تلقائياً.</p>
    <ul class="divide-y divide-line" role="listbox" aria-label="نتائج البحث">
      <template x-for="r in results" :key="r.id">
        <li class="py-3 flex items-center gap-3">
          <div class="flex-1 min-w-0">
            <div class="font-mono text-xs text-neutral-500" dir="ltr" x-text="r.sku"></div>
            <div class="font-semibold" x-text="r.name + (r.side ? (r.side==='R' ? ' يمين' : ' يسار') : '')"></div>
            <div class="text-xs text-neutral-600" x-text="r.grade"></div>
          </div>
          <div class="text-left">
            <div class="num font-bold" x-text="fmt(r.price)"></div>
            <div class="text-xs" :class="r.available>0 ? 'text-emerald-700' : 'text-red-700'" x-text="r.available>0 ? 'متاح ' + r.available : 'نفد'"></div>
          </div>
          <button type="button" @click="add(r)" :disabled="r.available<=0" class="h-11 px-4 rounded-xl bg-brand text-ink font-bold disabled:opacity-40">إضافة</button>
        </li>
      </template>
    </ul>
    <p x-show="q && !results.length && !loading" x-cloak class="text-sm text-neutral-500">لا توجد نتائج لـ «<span x-text="q"></span>».</p>
  </section>

  <section class="xl:col-span-2 bg-white rounded-2xl border border-line p-5 space-y-4 self-start" aria-labelledby="pos-cart">
    <h2 id="pos-cart" class="font-bold">الفاتورة</h2>
    <p x-show="!lines.length" class="text-sm text-neutral-500">أضف أصنافاً من نتائج البحث.</p>
    <template x-for="l in lines" :key="l.id">
      <div class="flex items-center gap-2 text-sm">
        <div class="flex-1 min-w-0"><div class="font-semibold truncate" x-text="l.name"></div><div class="font-mono text-xs text-neutral-500" dir="ltr" x-text="l.sku"></div></div>
        <button type="button" class="w-9 h-9 rounded-lg border" @click="dec(l)" aria-label="إنقاص">−</button>
        <span class="num w-6 text-center" x-text="l.qty"></span>
        <button type="button" class="w-9 h-9 rounded-lg border" @click="inc(l)" aria-label="زيادة">+</button>
        <span class="num w-20 text-left font-semibold" x-text="fmt(l.price*l.qty)"></span>
      </div>
    </template>
    <div class="border-t border-line pt-3 space-y-1 text-sm" x-show="lines.length" x-cloak>
      <div class="flex justify-between"><span class="text-neutral-600">ضريبة القيمة المضافة المضمّنة (15%)</span><span class="num" x-text="fmt(vat())"></span></div>
      <div class="flex justify-between text-lg font-bold"><span>الإجمالي</span><span class="num" x-text="fmt(total())"></span></div>
    </div>

    <div x-show="lines.length" x-cloak class="space-y-3">
      <div role="radiogroup" aria-label="طريقة الدفع" class="grid grid-cols-2 gap-2">
        <button type="button" role="radio" :aria-checked="pay==='cash'" @click="pay='cash'" :class="pay==='cash' ? 'border-ink bg-orange-50 border-2' : 'border'" class="h-12 rounded-xl font-semibold">نقداً</button>
        <button type="button" role="radio" :aria-checked="pay==='card'" @click="pay='card'" :class="pay==='card' ? 'border-ink bg-orange-50 border-2' : 'border'" class="h-12 rounded-xl font-semibold">بطاقة / مدى</button>
      </div>
      <div x-show="pay==='cash'" class="space-y-2">
        <div class="flex gap-2 flex-wrap">
          <template x-for="a in quick()" :key="a"><button type="button" @click="tendered=a" class="h-10 px-3 rounded-lg border num text-sm" x-text="a"></button></template>
        </div>
        <label class="block text-sm">المبلغ المستلم<input type="number" step="0.01" x-model.number="tendered" class="mt-1 w-full h-11 rounded-xl border px-3 num"></label>
        <div class="flex justify-between text-sm"><span>الباقي للعميل</span><span class="num font-bold" x-text="fmt(Math.max(0, (tendered||0) - total()))"></span></div>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <input x-model="cname" placeholder="اسم العميل (اختياري)" class="h-11 rounded-xl border px-3 text-sm">
        <input x-model="cphone" placeholder="جوال العميل (اختياري)" dir="ltr" class="h-11 rounded-xl border px-3 text-sm text-left">
      </div>
      <p x-show="error" x-cloak class="text-sm text-red-700" role="alert" x-text="error"></p>
      <button type="button" @click="checkout()" :disabled="busy || (pay==='cash' && (tendered||0) < total())" class="w-full h-14 rounded-2xl bg-ink text-white font-extrabold disabled:opacity-40" x-text="busy ? 'جارٍ التسجيل…' : 'إتمام البيع'"></button>
    </div>

    <div x-show="done" x-cloak class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 space-y-2 text-sm" role="status">
      <div class="font-bold text-emerald-900">تم تسجيل البيع <span class="font-mono" dir="ltr" x-text="done?.order.number"></span></div>
      <div>خُصمت الكميات من المخزن المركزي وتحدّثت في التطبيق والموقع.</div>
      <template x-for="s in (done?.stock||[])" :key="s.sku"><div x-show="s.online_unavailable" class="text-red-800">«<span x-text="s.name"></span>» أصبحت غير متوفرة أونلاين.</div></template>
      <div x-show="done && done.change>0">الباقي للعميل: <b class="num" x-text="fmt(done?.change)"></b> ر.س</div>
      <div class="flex gap-2"><a :href="done?.receipt_url" target="_blank" class="h-10 px-4 rounded-lg bg-ink text-white inline-flex items-center">طباعة الإيصال</a><button type="button" @click="reset()" class="h-10 px-4 rounded-lg border">بيع جديد</button></div>
    </div>
  </section>
</div>

@push('scripts')
<script>
function pos() {
  return {
    q: '', results: [], lines: [], pay: 'cash', tendered: null, cname: '', cphone: '', busy: false, loading: false, error: '', done: null, scanning: false,
    fmt(v) { return Number(v || 0).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    total() { return this.lines.reduce((s, l) => s + l.price * l.qty, 0); },
    vat() { return this.total() * 15 / 115; },
    quick() { const t = Math.ceil(this.total()); return [...new Set([t, Math.ceil(t / 50) * 50, Math.ceil(t / 100) * 100, Math.ceil(t / 500) * 500])]; },
    async search() {
      if (!this.q.trim()) { this.results = []; return; }
      this.loading = true;
      const r = await fetch(@json(route('admin.pos.search')) + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
      this.results = (await r.json()).results; this.loading = false;
    },
    scan() { this.scanning = true; this.$refs.q.focus(); },
    async addFirst() {
      await this.search();
      const exact = this.results.find(r => r.sku.toLowerCase() === this.q.trim().toLowerCase()) || this.results[0];
      if (exact && exact.available > 0) { this.add(exact); this.q = ''; this.results = []; this.scanning = false; }
    },
    add(r) {
      const l = this.lines.find(x => x.id === r.id);
      if (l) { this.inc(l); return; }
      this.lines.push({ id: r.id, sku: r.sku, name: r.name + (r.side ? (r.side === 'R' ? ' يمين' : ' يسار') : ''), price: r.price, qty: 1, max: r.available });
    },
    inc(l) { if (l.qty < l.max) l.qty++; else this.error = 'المتاح من «' + l.name + '» ' + l.max + ' فقط'; },
    dec(l) { l.qty--; if (l.qty <= 0) this.lines = this.lines.filter(x => x !== l); },
    async checkout() {
      this.busy = true; this.error = '';
      const r = await fetch(@json(route('admin.pos.checkout')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ items: this.lines.map(l => ({ part_id: l.id, qty: l.qty })), payment: this.pay, tendered: this.tendered, customer_name: this.cname, customer_phone: this.cphone })
      });
      const j = await r.json(); this.busy = false;
      if (!r.ok) { this.error = j.message || 'تعذّر إتمام البيع'; return; }
      this.done = j; this.lines = [];
    },
    reset() { this.done = null; this.tendered = null; this.cname = ''; this.cphone = ''; this.$refs.q.focus(); }
  };
}
</script>
@endpush
@endsection
