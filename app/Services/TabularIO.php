<?php

namespace App\Services;

use App\Support\Phone;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** قراءة/كتابة CSV و XLSX (openspout) */
class TabularIO
{
    /** أسماء الأعمدة المقبولة → مفتاح داخلي */
    private const ALIASES = [
        'sku' => ['sku', 'part_number', 'part', 'oem', 'رقم القطعة', 'رقم_القطعة', 'الرقم'],
        'on_hand' => ['on_hand', 'qty', 'quantity', 'stock', 'الكمية', 'المخزون', 'الرصيد'],
        'price' => ['price', 'السعر', 'سعر البيع'],
        'cost' => ['cost', 'التكلفة', 'سعر التكلفة'],
    ];

    /** @return array{rows: array<int,array<string,string>>, missing: array<int,string>} */
    public function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $raw = $ext === 'xlsx' ? $this->readXlsx($file->getRealPath()) : $this->readCsv($file->getRealPath());
        if (!$raw) {
            return ['rows' => [], 'missing' => ['sku', 'on_hand']];
        }
        $header = array_map(fn ($h) => $this->key((string) $h), array_shift($raw));
        $missing = array_values(array_diff(['sku', 'on_hand'], $header));
        $rows = [];
        foreach ($raw as $i => $line) {
            if (!array_filter($line, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $assoc = [];
            foreach ($header as $c => $k) {
                if ($k) {
                    $assoc[$k] = trim((string) ($line[$c] ?? ''));
                }
            }
            $assoc['_line'] = $i + 2;
            $rows[] = $assoc;
        }
        return ['rows' => $rows, 'missing' => $missing];
    }

    private function key(string $h): ?string
    {
        $h = mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)));
        foreach (self::ALIASES as $k => $names) {
            if (in_array($h, array_map('mb_strtolower', $names), true)) {
                return $k;
            }
        }
        return null;
    }

    private function readCsv(string $path): array
    {
        $out = [];
        $fh = fopen($path, 'r');
        $first = fgets($fh);
        rewind($fh);
        $delim = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        while (($line = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            $out[] = array_map(fn ($v) => Phone::latinDigits((string) $v), $line);
        }
        fclose($fh);
        return $out;
    }

    private function readXlsx(string $path): array
    {
        $out = [];
        $reader = new XlsxReader();
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $out[] = array_map(fn ($v) => Phone::latinDigits($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) $v), $row->toArray());
            }
            break;
        }
        $reader->close();
        return $out;
    }

    /** يرجّع ملف التنزيل بصيغة xlsx أو csv (CSV مع BOM ليفتح عربي في Excel) */
    public function download(string $name, array $headers, iterable $rows, string $format = 'xlsx'): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dp');
        if ($format === 'csv') {
            $fh = fopen($tmp, 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, $headers, ',', '"', '\\');
            foreach ($rows as $r) {
                fputcsv($fh, array_values($r), ',', '"', '\\');
            }
            fclose($fh);
            return response()->download($tmp, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])->deleteFileAfterSend();
        }
        $w = new XlsxWriter();
        $w->openToFile($tmp);
        $w->addRow(Row::fromValues($headers));
        foreach ($rows as $r) {
            $w->addRow(Row::fromValues(array_values($r)));
        }
        $w->close();
        return response()->download($tmp, $name.'.xlsx')->deleteFileAfterSend();
    }
}
