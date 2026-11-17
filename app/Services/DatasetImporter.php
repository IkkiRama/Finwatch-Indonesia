<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\Indicator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class DatasetImporter
{
    public const COLUMNS = ['indicator_code', 'label', 'period_date', 'value', 'unit', 'frequency', 'scope', 'source_url', 'release_date', 'retrieved_at', 'dataset_version', 'methodology_note', 'sector', 'segment'];

    public function parse(string $path, string $extension): array
    {
        if (filesize($path) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Ukuran maksimum 10 MB.']);
        }
        $rows = [];
        if ($extension === 'csv') {
            $handle = fopen($path, 'rb');
            while (($row = fgetcsv($handle, 65536, ',', '"', '')) !== false) {
                if (count($rows) > 5000 || count($row) > 20) {
                    fclose($handle);
                    throw ValidationException::withMessages(['file' => 'Maksimal 5.000 baris dan 20 kolom.']);
                }
                if (array_filter($row, fn ($cell) => $cell !== null && $cell !== '') !== []) {
                    $rows[] = $row;
                }
            }
            fclose($handle);
        } elseif ($extension === 'xlsx') {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true || $zip->locateName('[Content_Types].xml') === false) {
                throw ValidationException::withMessages(['file' => 'Berkas XLSX tidak valid.']);
            }
            $bytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $bytes += $entry['size'];
                if ($bytes > 30 * 1024 * 1024 || preg_match('/vbaProject|embeddings|externalLinks/i', $entry['name'])) {
                    $zip->close();
                    throw ValidationException::withMessages(['file' => 'XLSX terlalu besar atau berisi konten aktif/tautan eksternal.']);
                }
            }
            $zip->close();
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $info = $reader->listWorksheetInfo($path);
            if (! $info || $info[0]['totalRows'] > 5001 || $info[0]['totalColumns'] > 20) {
                throw ValidationException::withMessages(['file' => 'Gunakan satu tabel, maksimal 5.000 baris dan 20 kolom.']);
            }
            $reader->setLoadSheetsOnly($info[0]['worksheetName']);
            $book = $reader->load($path);
            $sheet = $book->getSheet(0);
            foreach ($sheet->getRowIterator() as $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $cells[] = $cell->getValue();
                }
                if (array_filter($cells, fn ($cell) => $cell !== null && $cell !== '') !== []) {
                    $rows[] = $cells;
                }
            }
            $book->disconnectWorksheets();
        } else {
            throw ValidationException::withMessages(['file' => 'Hanya CSV dan XLSX yang didukung.']);
        }
        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'Header dan minimal satu baris diperlukan.']);
        }
        $header = array_map(fn ($value) => trim(ltrim((string) $value, "\xEF\xBB\xBF")), array_shift($rows));
        if (count($header) !== count(array_unique($header)) || array_diff(array_slice(self::COLUMNS, 0, 11), $header)) {
            throw ValidationException::withMessages(['file' => 'Kolom wajib tidak lengkap atau ada header ganda. Unduh template terlebih dahulu.']);
        }

        return array_map(fn ($row) => array_combine($header, array_slice(array_pad($row, count($header), ''), 0, count($header))), $rows);
    }

    public function stage(Dataset $dataset, array $rows, string $rawPath, ?User $actor): DatasetVersion
    {
        $issues = [];
        $clean = [];
        $seen = [];
        $incoming = [];
        $definitions = Indicator::all()->keyBy('code');
        foreach ($rows as $index => $row) {
            $row = array_intersect_key($row, array_flip(self::COLUMNS));
            $row += ['scope' => 'Indonesia', 'methodology_note' => '', 'sector' => 'banking', 'segment' => 'aggregate'];
            $validator = Validator::make($row, [
                'indicator_code' => 'required|string|regex:/^[a-z][a-z0-9_]{1,63}$/', 'label' => 'required|string|max:200',
                'period_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'value' => ['required', 'regex:/^-?\d{1,15}(\.\d{1,8})?$/'],
                'unit' => 'required|string|max:64', 'frequency' => 'required|in:monthly,quarterly,annual,ytd,snapshot', 'scope' => 'required|string|max:100',
                'source_url' => 'required|url:https|max:2000', 'release_date' => 'required|date_format:Y-m-d|before_or_equal:today',
                'retrieved_at' => 'required|date|before_or_equal:now', 'dataset_version' => 'required|string|max:64',
                'methodology_note' => 'nullable|string|max:3000', 'sector' => 'required|in:banking,inclusion,consumer,macro', 'segment' => 'required|string|max:100',
            ]);
            if ($validator->fails()) {
                $issues[] = ['row' => $index + 2, 'messages' => $validator->errors()->all()];

                continue;
            }
            try {
                app(OfficialDownload::class)->validateUrl($row['source_url']);
            } catch (ValidationException) {
                $issues[] = ['row' => $index + 2, 'messages' => ['Domain provenance harus sumber resmi yang diizinkan.']];

                continue;
            }
            $definition = $definitions->get($row['indicator_code']);
            if ($definition && ($definition->unit !== $row['unit'] || $definition->frequency !== $row['frequency'] || $definition->sector !== $row['sector'])) {
                $issues[] = ['row' => $index + 2, 'messages' => ['Definisi satuan/frekuensi/sektor berbeda dari indikator terdaftar.']];

                continue;
            }
            $signature = $row['unit'].'|'.$row['frequency'].'|'.$row['sector'];
            if (isset($incoming[$row['indicator_code']]) && $incoming[$row['indicator_code']] !== $signature) {
                $issues[] = ['row' => $index + 2, 'messages' => ['Satu kode indikator tidak boleh memiliki definisi berbeda dalam berkas yang sama.']];

                continue;
            }
            $incoming[$row['indicator_code']] = $signature;
            if (($row['unit'] === '%' && ((float) $row['value'] < 0 || (float) $row['value'] > 100)) || $row['release_date'] < $row['period_date']) {
                $issues[] = ['row' => $index + 2, 'messages' => ['Persentase di luar 0–100 atau tanggal rilis mendahului observasi.']];

                continue;
            }
            $key = implode('|', [$row['indicator_code'], $row['period_date'], $row['scope'], $row['segment']]);
            if (isset($seen[$key])) {
                $issues[] = ['row' => $index + 2, 'messages' => ['Observasi duplikat dalam satu versi.']];

                continue;
            }
            $seen[$key] = true;
            foreach ($row as $field => $value) {
                if (is_string($value)) {
                    $row[$field] = trim(strip_tags($value));
                }
            }
            $clean[] = $row;
        }
        $encoded = json_encode($rows, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 3 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Payload normalisasi melampaui 3 MB. Bagi berkas menjadi dataset/versi yang lebih kecil.']);
        }
        $checksum = hash('sha256', $encoded);

        return DB::transaction(function () use ($dataset, $clean, $issues, $checksum, $rawPath, $actor): DatasetVersion {
            $locked = Dataset::whereKey($dataset->id)->lockForUpdate()->firstOrFail();
            $existing = DatasetVersion::where('dataset_id', $locked->id)->where('checksum', $checksum)->first();
            if ($existing) {
                return $existing;
            }
            $version = DatasetVersion::create(['dataset_id' => $locked->id, 'number' => ((int) $locked->versions()->max('number')) + 1, 'status' => $issues ? 'invalid' : 'needs_review', 'checksum' => $checksum, 'raw_path' => $rawPath, 'rows' => $clean, 'issues' => $issues, 'created_by' => $actor?->id]);
            AuditService::record('data.staged', $actor, ['dataset_id' => $locked->id, 'version_id' => $version->id, 'count' => count($clean), 'status' => $version->status]);

            return $version;
        });
    }

    public function publish(DatasetVersion $version, ?User $actor): DatasetVersion
    {
        return DB::transaction(function () use ($version, $actor): DatasetVersion {
            $dataset = Dataset::whereKey($version->dataset_id)->lockForUpdate()->firstOrFail();
            $version = DatasetVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($version->issues || ! $version->rows || $version->status === 'invalid') {
                throw ValidationException::withMessages(['version' => 'Perbaiki seluruh masalah validasi sebelum publikasi.']);
            }
            if ($dataset->current_version_id === $version->id) {
                return $version;
            }
            if (! $version->published_at) {
                foreach ($version->rows as $row) {
                    Indicator::firstOrCreate(['code' => $row['indicator_code']], ['label' => $row['label'], 'unit' => $row['unit'], 'frequency' => $row['frequency'], 'sector' => $row['sector'], 'change_type' => $row['unit'] === '%' ? 'ratio' : 'level', 'definition' => $row['label'], 'methodology_note' => $row['methodology_note']]);
                    $observation = array_intersect_key($row, array_flip(['indicator_code', 'period_date', 'value', 'unit', 'frequency', 'scope', 'source_url', 'release_date', 'retrieved_at', 'dataset_version', 'methodology_note', 'sector', 'segment']));
                    $observation['retrieved_at'] = CarbonImmutable::parse($row['retrieved_at'])->setTimezone(config('app.timezone'));
                    DB::table('indicator_observations')->insert($observation + ['dataset_id' => $dataset->id, 'version_id' => $version->id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $dataset->versions()->where('status', 'published')->update(['status' => 'superseded']);
            $version->forceFill(['status' => 'published', 'reviewed_by' => $actor?->id, 'published_at' => $version->published_at ?? now()])->save();
            $dataset->forceFill(['current_version_id' => $version->id, 'status' => 'published', 'published_at' => now()])->save();
            AuditService::record('data.published', $actor, ['dataset_id' => $dataset->id, 'version_id' => $version->id, 'count' => count($version->rows)]);

            return $version;
        });
    }
}
