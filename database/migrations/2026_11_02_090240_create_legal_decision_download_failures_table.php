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
        Schema::create('legal_decision_download_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_url', 1000);
            $table->string('url_hash', 32)->index();
            $table->string('decision_number')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(1);
            $table->timestamp('last_failed_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_decision_download_failures');
    }
};
