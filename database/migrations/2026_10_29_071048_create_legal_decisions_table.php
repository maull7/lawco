<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('legal_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision_number')->index();
            $table->string('title')->nullable();
            $table->string('court_name')->nullable()->index(); // e.g. Mahkamah Agung / Pengadilan Negeri
            $table->string('classification')->nullable()->index(); // e.g. Perdata, Pidana Khusus
            $table->string('verdict')->nullable(); // Amar Putusan (e.g. Tolak, Kabul, Lepas)
            $table->date('decision_date')->nullable()->index();
            $table->year('year')->nullable()->index();
            $table->text('summary')->nullable();
            $table->string('file_url')->nullable(); // URL PDF asli
            $table->string('file_path')->nullable(); // Path PDF lokal jika didownload
            $table->string('source_url')->nullable()->unique(); // URL halaman detail MA
            $table->string('source')->default('mahkamah_agung')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_decisions');
    }
};
