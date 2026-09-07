<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One reusable file store for the whole system (plan §26.1).
 *
 * Every module that needs to attach files — drawings, BQ, memos, documents,
 * safety — goes through here rather than reinventing upload handling six times.
 * Each caller passes its own allowed extensions and size limit; everything else
 * (randomised storage names, folder-structure preservation, duplicate detection,
 * path-traversal defence, disk choice) is handled once, here.
 *
 * Disk-agnostic: files are written through the Storage facade to whatever disk
 * is passed (default: the app's configured filesystem). Moving drawings to S3 /
 * Spaces later is a config change, not a code change (plan 4.3, E4).
 */
class FileUploadService
{
    /**
     * Attach an uploaded file to any model.
     *
     * @param  array{
     *     disk?: string,
     *     folder_path?: ?string,
     *     allowed_extensions?: array<int, string>,
     *     max_size_kb?: int,
     *     directory?: string,
     *     skip_duplicates?: bool
     * }  $options
     */
    public function attach(UploadedFile $file, Model $attachable, ?int $uploadedBy = null, array $options = []): ?Attachment
    {
        $disk = $options['disk'] ?? config('filesystems.default', 'local');
        $extension = strtolower($file->getClientOriginalExtension());

        $this->guardExtension($extension, $options['allowed_extensions'] ?? []);
        $this->guardSize($file, $options['max_size_kb'] ?? null);

        $folderPath = $this->sanitiseFolderPath($options['folder_path'] ?? null);
        $checksum = hash_file('sha256', $file->getRealPath()) ?: null;

        // Skip a byte-identical file already attached to the same record, when
        // asked — folder re-uploads are common and this avoids silent dupes.
        if (($options['skip_duplicates'] ?? false) && $checksum) {
            // A duplicate is the same bytes in the same place with the same name
            // — re-uploading a folder. The same file legitimately filed under a
            // different folder or name is NOT a duplicate and is kept.
            $existing = Attachment::where('attachable_type', $attachable->getMorphClass())
                ->where('attachable_id', $attachable->getKey())
                ->where('checksum', $checksum)
                ->where('folder_path', $folderPath)
                ->where('original_name', $file->getClientOriginalName())
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Stored under a randomised name — never the user's original filename,
        // which is kept in the database only (plan 4.4d).
        $directory = trim($options['directory'] ?? 'attachments', '/');
        $storedPath = $file->storeAs(
            $directory,
            Str::uuid()->toString().($extension ? '.'.$extension : ''),
            $disk,
        );

        if ($storedPath === false) {
            throw new RuntimeException('Failed to store the uploaded file.');
        }

        return Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'folder_path' => $folderPath,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'disk' => $disk,
            'mime_type' => $file->getClientMimeType(),
            'extension' => $extension ?: null,
            'size_bytes' => $file->getSize() ?: 0,
            'checksum' => $checksum,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Remove an attachment and its file. The DB row goes even if the file is
     * already missing, so a half-deleted state cannot strand a record.
     */
    public function remove(Attachment $attachment): void
    {
        try {
            Storage::disk($attachment->disk)->delete($attachment->stored_path);
        } finally {
            $attachment->delete();
        }
    }

    private function guardExtension(string $extension, array $allowed): void
    {
        if ($allowed === []) {
            return;
        }

        $allowed = array_map('strtolower', $allowed);

        if (! in_array($extension, $allowed, true)) {
            abort(422, "Files of type .{$extension} are not allowed here. Allowed: ".implode(', ', $allowed));
        }
    }

    private function guardSize(UploadedFile $file, ?int $maxKb): void
    {
        if ($maxKb === null) {
            return;
        }

        if (($file->getSize() ?: 0) > $maxKb * 1024) {
            $mb = round($maxKb / 1024, 1);
            abort(422, "File is too large. Maximum {$mb} MB.");
        }
    }

    /**
     * Make a folder path from a browser upload safe to store and display.
     *
     * webkitRelativePath is attacker-influenced, so strip any "..", leading
     * slashes and drive letters — never let it escape into an absolute or
     * parent path. The value is only ever metadata; files themselves are stored
     * under randomised names regardless (plan 4.4d path-traversal note).
     */
    private function sanitiseFolderPath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $segments = array_filter(
            explode('/', $path),
            fn ($s) => $s !== '' && $s !== '.' && $s !== '..' && ! preg_match('/^[A-Za-z]:$/', $s),
        );

        $clean = implode('/', array_map(fn ($s) => trim($s), $segments));

        return $clean === '' ? null : Str::limit($clean, 500, '');
    }
}
