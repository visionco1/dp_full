<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>@yield('title')</title>
<style>
  @page { size: A4; margin: 12mm; }
  * { box-sizing: border-box; }
  body { font-family: "IBM Plex Sans Arabic", Tahoma, Arial, sans-serif; color:#111; margin:0; font-size:13px; }
  .page { page-break-after: always; padding: 4px; }
  .page:last-child { page-break-after: auto; }
  table { width:100%; border-collapse: collapse; } th,td { border:1px solid #bbb; padding:6px 8px; } th { background:#f1f1f1; }
  .num { font-variant-numeric: tabular-nums; direction:ltr; unicode-bidi: embed; }
  .hdr { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #111; padding-bottom:8px; margin-bottom:12px; }
  .muted { color:#555; } .box { border:1px solid #111; padding:10px; margin-bottom:10px; }
  .bar { font-family: monospace; letter-spacing: 3px; font-size: 22px; text-align:center; direction:ltr; }
  @media print { .noprint { display:none } }
</style></head><body>
<div class="noprint" style="padding:8px;background:#fff7d6"><button onclick="print()">طباعة</button></div>
@yield('body')
@if (!empty($auto))<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>@endif
</body></html>
