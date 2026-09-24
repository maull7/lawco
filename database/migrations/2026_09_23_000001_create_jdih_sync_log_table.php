<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log sinkronisasi JDIH -> Lawco.
     *
     * Bukan bagian dari struktur `regulations` — tabel terpisah yang hanya
     * mencatat pasangan (jdih_source + jdih_document_id) yang sudah dibuat
     * regulasinya di Lawco, agar `php artisan jdih:sync` idempotent dan
     * tidak pernah membuat duplikat.
     */
    public function up(): void
    {
        Schema::create('jdih_sync_log', function (Blueprint $table) {
            $table->id();
            $table->string('jdih_source', 64);
            $table->string('jdih_document_id', 512);
            $table->string('jdih_checksum', 64)->nullable();
            $table->unsignedBigInteger('lawco_regulation_id');
            $table->string('file_path', 255)->default('');
            $table->timestamps();

            $table->foreign('lawco_regulation_id')->references('id')->on('regulations')->cascadeOnDelete();
            $table->unique(['jdih_source', 'jdih_document_id'], 'jdih_sync_log_source_doc_unique');
            $table->unique('jdih_checksum', 'jdih_sync_log_checksum_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jdih_sync_log');
    }
};