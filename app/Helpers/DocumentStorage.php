<?php

namespace App\Helpers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Disk for uploaded documents (regulations, review documents, legal cases, category files, payment proofs).
 * PDF_DISK=public keeps files on the server; PDF_DISK=<r2DiskName()> uses Cloudflare R2.
 */
class DocumentStorage
{
    /** @var array<string, string> */
    private static array $localCopies = [];

    public static function diskName(): string
    {
        return (string) config('filesystems.pdf_disk', 'public');
    }

    /** R2 disk that documents are migrated to (config filesystems.r2_document_disk). */
    public static function r2DiskName(): string
    {
        return (string) config('filesystems.r2_document_disk', 'r2');
    }

    public static function usesR2(): bool
    {
        return self::diskName() === self::r2DiskName();
    }

    public static function disk(): FilesystemAdapter
    {
        return Storage::disk(self::diskName());
    }

    public static function isRemote(): bool
    {
        return config('filesystems.disks.'.self::diskName().'.driver') === 's3';
    }

    /** Browser URL. Remote disks get a 30-minute signed URL so the bucket stays private. */
    public static function url(string $path): string
    {
        return self::isRemote()
            ? self::disk()->temporaryUrl($path, now()->addMinutes(30))
            : self::disk()->url($path);
    }

    /**
     * Local filesystem path for parsers that need a real file.
     * Remote disks: downloaded once per request to a temp file, deleted at shutdown.
     * A missing remote file returns a path that does not exist, so callers' file_exists() checks still work.
     */
    public static function localPath(string $path): string
    {
        if (! self::isRemote()) {
            return self::disk()->path($path);
        }

        if (isset(self::$localCopies[$path]) && is_file(self::$localCopies[$path])) {
            return self::$localCopies[$path];
        }

        $in = self::disk()->exists($path) ? self::disk()->readStream($path) : null;
        if (! $in) {
            return sys_get_temp_dir().'/missing_'.md5($path);
        }

        $base = tempnam(sys_get_temp_dir(), 'doc_');
        $tmp = $base.'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf');
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        register_shutdown_function(function () use ($base, $tmp): void {
            @unlink($base);
            @unlink($tmp);
        });

        return self::$localCopies[$path] = $tmp;
    }
}
