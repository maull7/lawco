<?php

namespace App\Services;

use App\Models\JdihDocumentReview;
use App\Models\RegulationType;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveJdihDocumentReview
{
    /** @param array<string, mixed> $data */
    public function save(array $data, int $userId): JdihDocumentReview
    {
        $document = DB::connection('jdih')->table('regulations')->where('source', $data['source'])
            ->where('document_id', $data['document_id'])->first(['regulation_type']);
        if ($document === null) {
            throw ValidationException::withMessages(['document_id' => 'Dokumen tidak ditemukan di sumber JDIH.']);
        }
        if (DB::table('jdih_sync_log')->where('jdih_source', $data['source'])->where('jdih_document_id', $data['document_id'])->exists()) {
            throw ValidationException::withMessages(['document_id' => 'Dokumen sudah masuk Lawco.']);
        }

        return Cache::lock('jdih-review-save', 30)->block(3, function () use ($data, $userId): JdihDocumentReview {
            return DB::transaction(function () use ($data, $userId): JdihDocumentReview {
                if ($data['type_mode'] === 'new') {
                    $name = trim($data['new_type_name']);
                    if ($name === '') {
                        throw ValidationException::withMessages(['new_type_name' => 'Nama jenis regulasi wajib diisi.']);
                    }
                    $type = RegulationType::query()->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->first();
                    if ($type !== null && ! $type->is_active) {
                        throw ValidationException::withMessages(['new_type_name' => 'Jenis ini nonaktif. Aktifkan dahulu di master Jenis Regulasi.']);
                    }
                    if ($type === null) {
                        $type = RegulationType::create(['name' => $name, 'level' => $data['level'] ?? 4]);
                        UserActivityLog::log('created', RegulationType::class, $type->id, 'Menambahkan jenis regulasi dari pemeriksaan JDIH: '.$name);
                    }
                } else {
                    $type = RegulationType::query()->where('is_active', true)->findOrFail($data['regulation_type_id']);
                }
                $review = JdihDocumentReview::updateOrCreate(
                    ['source' => $data['source'], 'document_id' => $data['document_id']],
                    ['regulation_type_id' => $type->id, 'reviewed_by' => $userId],
                );
                UserActivityLog::log('updated', JdihDocumentReview::class, $review->id, 'Menentukan jenis dokumen '.$review->source.'/'.$review->document_id.' sebagai '.$type->name);

                return $review;
            });
        });
    }
}
