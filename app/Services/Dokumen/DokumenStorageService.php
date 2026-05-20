<?php

namespace App\Services\Dokumen;

use App\Models\Dokumen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DokumenStorageService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array{type: class-string|null, id: int|null, label: string|null}  $relation
     */
    public function store(array $data, UploadedFile $file, User $uploadedBy, array $relation): Dokumen
    {
        $disk = (string) config('filesystems.documents_disk', 'local');
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $directory = 'dokumen/'.$data['jenis'].'/'.now()->format('Y/m');
        $storedName = (string) Str::uuid().'.'.$extension;
        $hash = hash_file('sha256', $file->getRealPath());

        $path = Storage::disk($disk)->putFileAs($directory, $file, $storedName);

        if (! $path) {
            throw new RuntimeException('File dokumen gagal disimpan.');
        }

        try {
            return DB::transaction(function () use ($data, $file, $uploadedBy, $relation, $disk, $path, $hash) {
                $dokumen = Dokumen::create([
                    ...collect($data)->except(['file', 'related_type', 'related_id'])->all(),
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize() ?: 0,
                    'file_hash' => $hash,
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                    'uploaded_by' => $uploadedBy->id,
                    'metadata' => [
                        'stored_filename' => basename($path),
                        'extension' => $file->getClientOriginalExtension(),
                    ],
                ]);

                if ($relation['type'] && $relation['id']) {
                    $dokumen->relations()->create([
                        'related_type' => $relation['type'],
                        'related_id' => $relation['id'],
                        'label' => $relation['label'],
                        'created_by' => $uploadedBy->id,
                    ]);
                }

                return $dokumen;
            });
        } catch (\Throwable $throwable) {
            Storage::disk($disk)->delete($path);

            throw $throwable;
        }
    }
}
