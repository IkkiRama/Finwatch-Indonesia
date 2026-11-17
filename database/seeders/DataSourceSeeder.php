<?php

namespace Database\Seeders;

use App\Models\DataSource;
use Illuminate\Database\Seeder;

class DataSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['OJK', 'Otoritas Jasa Keuangan', 'https://data.ojk.go.id/SJKPublic', 'Statistik sektor jasa keuangan. Ketersediaan unduhan resmi dan lisensi perlu diperiksa sebelum integrasi.'],
            ['BI', 'Bank Indonesia', 'https://www.bi.go.id/id/statistik/ekonomi-keuangan/seki/default.aspx', 'Statistik Ekonomi dan Keuangan Indonesia. Adapter belum diimplementasikan.'],
            ['BPS', 'Badan Pusat Statistik', 'https://webapi.bps.go.id/developer/', 'Referensi layanan data BPS. Akses API memerlukan konfigurasi dan verifikasi dataset.'],
            ['SNLIK', 'Survei Literasi & Inklusi Keuangan', 'https://www.ojk.go.id', 'Referensi publikasi survei. Perbedaan metodologi antarperiode harus dicatat.'],
            ['PEPK', 'Pelindungan Konsumen', 'https://www.ojk.go.id', 'Referensi publikasi edukasi dan pelindungan konsumen. Berkas dan periode belum diverifikasi.'],
        ];
        foreach ($sources as [$code, $name, $url, $description]) {
            DataSource::firstOrCreate(['code' => $code], compact('name', 'url', 'description') + ['method' => 'manual', 'status' => 'awaiting_verification']);
        }
    }
}
