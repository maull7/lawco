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
        Schema::create('downloaded_legal_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_number')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('court_name')->nullable();
            $table->string('classification')->nullable()->index();
            $table->string('year', 10)->nullable();
            $table->string('tgl_register', 30)->nullable();
            $table->string('tgl_putus', 30)->nullable();
            $table->string('tgl_upload', 30)->nullable();
            $table->text('summary')->nullable();
            $table->text('source_url')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->default('application/pdf');
            $table->string('download_type')->default('manual'); // 'manual', 'bulk', 'auto'
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downloaded_legal_decisions');
    }
};
