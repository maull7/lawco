<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Only creates the empty table; data is copied by `regulations:copy-parsed-text`
     * so the deploy migration stays fast. regulations.parsed_text is kept as-is.
     */
    public function up(): void
    {
        Schema::create('regulation_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('parsed_text');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regulation_texts');
    }
};
