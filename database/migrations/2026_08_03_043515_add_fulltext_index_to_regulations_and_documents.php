<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Parser ngram hanya ada di MySQL (dan tidak di MariaDB). Deteksi runtime
        // agar migration tetap jalan di keduanya: MySQL memakai ngram, MariaDB
        // memakai FULLTEXT biasa.
        $supportsNgram = DB::selectOne(
            "SELECT 1 AS ok FROM information_schema.PLUGINS WHERE PLUGIN_NAME = 'ngram' AND PLUGIN_STATUS = 'ACTIVE' LIMIT 1"
        )?->ok;
        $parser = $supportsNgram ? ' WITH PARSER ngram' : '';

        DB::statement("ALTER TABLE regulations ADD FULLTEXT INDEX regulations_parsed_text_fulltext(parsed_text){$parser}");
        DB::statement("ALTER TABLE regulation_documents ADD FULLTEXT INDEX regulation_documents_parsed_text_fulltext(parsed_text){$parser}");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE regulations DROP INDEX regulations_parsed_text_fulltext');
        DB::statement('ALTER TABLE regulation_documents DROP INDEX regulation_documents_parsed_text_fulltext');
    }
};
