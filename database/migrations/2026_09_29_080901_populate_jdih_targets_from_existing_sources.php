<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TARGETS = [
        ['source' => 'jdih_komdigi', 'name' => 'JDIH Komdigi', 'target_url' => 'https://jdih.komdigi.go.id'],
        ['source' => 'jdih_kemenhub', 'name' => 'JDIH Kemenhub', 'target_url' => 'https://jdih.kemenhub.go.id'],
        ['source' => 'jdih_kemenkumham', 'name' => 'JDIH Kementerian Hukum dan HAM', 'target_url' => 'https://jdih.kementrianhukumdanham.com'],
        ['source' => 'jdih_kemenham', 'name' => 'JDIH Kementerian Hak Asasi Manusia', 'target_url' => 'https://jdih.kemenham.go.id'],
        ['source' => 'jdih_kemenimipas', 'name' => 'JDIH Kementerian Imigrasi dan Pemasyarakatan', 'target_url' => 'https://jdih.kemenimipas.go.id'],
        ['source' => 'jdih_bkpm', 'name' => 'JDIH Kementerian Investasi dan Hilirisasi/BKPM', 'target_url' => 'https://jdih.bkpm.go.id'],
        ['source' => 'jdih_kkp', 'name' => 'JDIH Kementerian Kelautan dan Perikanan', 'target_url' => 'https://jdih.kkp.go.id'],
        ['source' => 'jdih_kemkes', 'name' => 'JDIH Kementerian Kesehatan', 'target_url' => 'https://jdih.kemkes.go.id'],
        ['source' => 'jdih_kemenkeu', 'name' => 'JDIH Kementerian Keuangan', 'target_url' => 'https://jdih.kemenkeu.go.id'],
        ['source' => 'jdih_kop', 'name' => 'JDIH Kementerian Koperasi', 'target_url' => 'https://jdih.kop.go.id'],
        ['source' => 'jdih_kemenlh', 'name' => 'JDIH Kementerian Lingkungan Hidup', 'target_url' => 'https://jdih.kemenlh.go.id'],
        ['source' => 'jdih_panrb', 'name' => 'JDIH Kementerian PANRB', 'target_url' => 'https://jdih.menpan.go.id'],
        ['source' => 'jdih_bumn', 'name' => 'JDIH Kementerian BUMN', 'target_url' => 'https://jdih.bumn.go.id'],
        ['source' => 'jdih_ekraf', 'name' => 'JDIH Kementerian Ekonomi Kreatif', 'target_url' => 'https://jdih.ekraf.go.id'],
        ['source' => 'jdih_atrbpn', 'name' => 'JDIH Kementerian Agraria dan Tata Ruang/BPN', 'target_url' => 'https://jdih.atrbpn.go.id'],
        ['source' => 'jdih_kemenpora', 'name' => 'JDIH Kementerian Pemuda dan Olahraga', 'target_url' => 'https://jdih.kemenpora.go.id'],
        ['source' => 'jdih_pu', 'name' => 'JDIH Kementerian Pekerjaan Umum', 'target_url' => 'https://jdih.pu.go.id'],
        ['source' => 'jdih_kemendikdasmen', 'name' => 'JDIH Kementerian Pendidikan Dasar dan Menengah', 'target_url' => 'https://jdih.kemendikdasmen.go.id'],
        ['source' => 'jdih_kemdiktisaintek', 'name' => 'JDIH Kementerian Pendidikan Tinggi, Sains, dan Teknologi', 'target_url' => 'https://jdih.kemdiktisaintek.go.id'],
        ['source' => 'jdih_kemenperin', 'name' => 'JDIH Kementerian Perindustrian', 'target_url' => 'https://jdih.kemenperin.go.id'],
        ['source' => 'jdih_pkp', 'name' => 'JDIH Kementerian Perumahan dan Kawasan Permukiman', 'target_url' => 'https://jdih.pkp.go.id'],
        ['source' => 'peraturan_bpk', 'name' => 'Peraturan BPK (Badan Pemeriksa Keuangan)', 'target_url' => 'https://peraturan.bpk.go.id'],
        ['source' => 'jdih_kemendesa', 'name' => 'JDIH Kementerian Desa dan Pembangunan Daerah Tertinggal', 'target_url' => 'https://jdih.kemendesa.go.id'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TARGETS as $target) {
            DB::table('jdih_targets')->updateOrInsert(
                ['source' => $target['source']],
                [...$target, 'is_active' => true, 'updated_at' => now()],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('jdih_targets')->whereIn('source', array_column(self::TARGETS, 'source'))->delete();
    }
};
