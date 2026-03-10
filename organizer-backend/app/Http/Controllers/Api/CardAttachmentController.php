<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardAttachment;
use App\Models\User;
use App\Support\Audit;
use App\Support\BoardWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CardAttachmentController extends Controller
{
    private function disk(): string
    {
        return config('filesystems.attachments_disk', 'attachments_local');
    }

    private function baseLogContext(Request $request, ?CardAttachment $attachment = null, ?Card $card = null): array
    {
        return [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => optional($request->user())->id,
            'card_id' => $card?->id ?? $attachment?->card_id,
            'attachment_id' => $attachment?->id,
            'disk' => $this->disk(),
        ];
    }

    private function logInfo(string $message, array $context = []): void
    {
        Log::info('[CardAttachment] ' . $message, $context);
    }

    private function logWarning(string $message, array $context = []): void
    {
        Log::warning('[CardAttachment] ' . $message, $context);
    }

    private function logError(string $message, \Throwable $e, array $context = []): void
    {
        Log::error('[CardAttachment] ' . $message, array_merge($context, [
            'exception_message' => $e->getMessage(),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]));
    }

    public function index(Request $request, Card $card): JsonResponse
    {
        $context = $this->baseLogContext($request, card: $card);

        try {
            $card->loadMissing('attachments');

            $this->logInfo('index:ok', $context + [
                'attachments_count' => $card->attachments->count(),
            ]);

            return response()->json([
                'data' => $card->attachments
                    ->map(fn (CardAttachment $a) => $this->serialize($a))
                    ->values(),
            ]);
        } catch (\Throwable $e) {
            $this->logError('index:failed', $e, $context);

            return response()->json([
                'message' => 'No se pudieron obtener los adjuntos.',
            ], 500);
        }
    }

    public function store(Request $request, Card $card): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->canWriteCards(), 403);

        $board = BoardWriteGuard::forCard($card);

        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        $context = $this->baseLogContext($request, card: $card);

        if (! $request->hasFile('file')) {
            $this->logWarning('store:no-file', $context);

            return response()->json([
                'message' => 'No se recibió ningún archivo.',
                'errors' => [
                    'file' => ['No se recibió ningún archivo.'],
                ],
            ], 422);
        }

        /** @var UploadedFile|null $file */
        $file = $request->file('file');

        if (! $file) {
            $this->logWarning('store:invalid-file-object', $context);

            return response()->json([
                'message' => 'El archivo recibido es inválido.',
                'errors' => [
                    'file' => ['El archivo recibido es inválido.'],
                ],
            ], 422);
        }

        if (! $file->isValid()) {
            $phpError = $file->getError();

            $this->logWarning('store:upload-invalid', $context + [
                'php_upload_error_code' => $phpError,
                'original_name' => $file->getClientOriginalName(),
            ]);

            return response()->json([
                'message' => $this->uploadErrorMessage($phpError),
                'errors' => [
                    'file' => [$this->uploadErrorMessage($phpError)],
                ],
                'debug' => [
                    'php_upload_error_code' => $phpError,
                ],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ], [
            'file.required' => 'Debes seleccionar un archivo.',
            'file.file' => 'El archivo enviado no es válido.',
            'file.max' => 'El archivo supera el tamaño máximo permitido de 5MB.',
            'file.mimes' => 'Formato no permitido. Solo se aceptan PDF, Word, Excel, JPG y PNG.',
        ]);

        if ($validator->fails()) {
            $this->logWarning('store:validation-failed', $context + [
                'errors' => $validator->errors()->toArray(),
                'original_name' => $file->getClientOriginalName(),
            ]);

            return response()->json([
                'message' => $validator->errors()->first('file') ?: 'No se pudo validar el archivo.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $original = $file->getClientOriginalName();
        $mime = $file->getClientMimeType() ?: 'application/octet-stream';
        $size = (int) $file->getSize();

        $card->loadMissing('column');
        $boardId = (int) $card->column->board_id;

        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $dir = "attachments/boards/{$boardId}/cards/{$card->id}";
        $diskName = $this->disk();
        $disk = Storage::disk($diskName);

        $name = Str::uuid()->toString() . ($ext ? '.' . $ext : '');
        $storedPath = null;
        $thumbnailPath = null;

        $this->logInfo('store:start', $context + [
            'original_name' => $original,
            'mime_type' => $mime,
            'size' => $size,
            'dir' => $dir,
            'disk' => $diskName,
        ]);

        try {
            $storedPath = $disk->putFileAs($dir, $file, $name);

            if (! $storedPath) {
                $this->logWarning('store:file-not-saved', $context + [
                    'original_name' => $original,
                    'dir' => $dir,
                    'disk' => $diskName,
                ]);

                return response()->json([
                    'message' => 'No se pudo guardar el archivo en el almacenamiento.',
                    'errors' => [
                        'file' => ['No se pudo guardar el archivo en el almacenamiento.'],
                    ],
                ], 500);
            }

            $this->logInfo('store:file-saved', $context + [
                'stored_path' => $storedPath,
                'original_name' => $original,
            ]);

            if (str_starts_with($mime, 'image/')) {
                $thumbnailPath = $this->generateImageThumbnail(
                    file: $file,
                    dir: $dir . '/thumbs',
                    diskName: $diskName
                );

                $this->logInfo('store:thumbnail-result', $context + [
                    'stored_path' => $storedPath,
                    'thumbnail_path' => $thumbnailPath,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logError('store:failed', $e, $context + [
                'stored_path' => $storedPath,
                'dir' => $dir,
                'original_name' => $original,
                'mime_type' => $mime,
            ]);

            if ($storedPath && $disk->exists($storedPath)) {
                try {
                    $disk->delete($storedPath);

                    $this->logInfo('store:cleanup-deleted-stored-file', $context + [
                        'stored_path' => $storedPath,
                    ]);
                } catch (\Throwable $cleanupError) {
                    $this->logError('store:cleanup-failed', $cleanupError, $context + [
                        'stored_path' => $storedPath,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Ocurrió un error al guardar el archivo.',
                'errors' => [
                    'file' => ['Ocurrió un error al guardar el archivo.'],
                ],
            ], 500);
        }

        $attachment = CardAttachment::query()->create([
            'card_id' => $card->id,
            'uploaded_by' => $actor->id,
            'original_name' => $original,
            'stored_path' => $storedPath,
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => $mime,
            'size' => $size,
        ]);

        $this->logInfo('store:db-created', $context + [
            'attachment_id' => $attachment->id,
            'stored_path' => $storedPath,
            'thumbnail_path' => $thumbnailPath,
        ]);

        Audit::log($actor, 'attachment_uploaded', 'card', $card->id, [
            'attachment_id' => $attachment->id,
            'name' => $original,
        ]);

        return response()->json([
            'data' => $this->serialize($attachment),
        ], 201);
    }

    public function download(Request $request, CardAttachment $attachment)
    {
        $context = $this->baseLogContext($request, $attachment) + [
            'stored_path' => $attachment->stored_path,
            'thumbnail_path' => $attachment->thumbnail_path,
        ];

        try {
            $disk = Storage::disk($this->disk());
            $path = $attachment->stored_path;

            $this->logInfo('download:start', $context + [
                'resolved_path' => $path,
            ]);

            if (! $disk->exists($path)) {
                $this->logWarning('download:file-not-found', $context + [
                    'resolved_path' => $path,
                ]);

                return response()->json(['message' => 'File not found.'], 404);
            }

            $stream = $disk->readStream($path);

            if ($stream === false) {
                $this->logWarning('download:read-stream-false', $context + [
                    'resolved_path' => $path,
                ]);

                return response()->json(['message' => 'Unable to read file.'], 500);
            }

            $this->logInfo('download:stream-opened', $context + [
                'resolved_path' => $path,
                'is_resource' => is_resource($stream),
                'mime' => $attachment->mime_type,
            ]);

            return response()->streamDownload(function () use ($stream) {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, $attachment->original_name, [
                'Content-Type' => $attachment->mime_type,
                'Cache-Control' => 'private, max-age=3600',
            ]);
        } catch (\Throwable $e) {
            $this->logError('download:failed', $e, $context);

            return response()->json(['message' => 'Unable to download file.'], 500);
        }
    }

    public function preview(Request $request, CardAttachment $attachment)
    {
        $context = $this->baseLogContext($request, $attachment) + [
            'stored_path' => $attachment->stored_path,
            'thumbnail_path' => $attachment->thumbnail_path,
        ];

        try {
            $disk = Storage::disk($this->disk());
            $path = $attachment->stored_path;

            $this->logInfo('preview:start', $context + [
                'resolved_path' => $path,
            ]);

            if (! $disk->exists($path)) {
                $this->logWarning('preview:file-not-found', $context + [
                    'resolved_path' => $path,
                ]);

                return response()->json(['message' => 'File not found.'], 404);
            }

            $stream = $disk->readStream($path);

            if ($stream === false) {
                $this->logWarning('preview:read-stream-false', $context + [
                    'resolved_path' => $path,
                ]);

                return response()->json(['message' => 'Unable to read file.'], 500);
            }

            $this->logInfo('preview:stream-opened', $context + [
                'resolved_path' => $path,
                'is_resource' => is_resource($stream),
                'mime' => $attachment->mime_type,
            ]);

            return response()->stream(function () use ($stream) {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="' . $attachment->original_name . '"',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        } catch (\Throwable $e) {
            $this->logError('preview:failed', $e, $context);

            return response()->json(['message' => 'Unable to preview file.'], 500);
        }
    }

    public function thumb(Request $request, CardAttachment $attachment)
    {
        $context = $this->baseLogContext($request, $attachment) + [
            'stored_path' => $attachment->stored_path,
            'thumbnail_path' => $attachment->thumbnail_path,
            'app_url' => config('app.url'),
            'request_root' => $request->root(),
            'request_host' => $request->getHost(),
            'request_scheme' => $request->getScheme(),
        ];

        $this->logInfo('thumb:start', $context);

        try {
            $disk = Storage::disk($this->disk());
            $path = $attachment->thumbnail_path ?: $attachment->stored_path;

            $this->logInfo('thumb:path-resolved', $context + [
                'resolved_path' => $path,
                'disk_driver' => config('filesystems.disks.' . $this->disk() . '.driver'),
            ]);

            if (! $disk->exists($path)) {
                $this->logWarning('thumb:file-not-found', $context + [
                    'resolved_path' => $path,
                ]);

                return response()->json(['message' => 'File not found.'], 404);
            }

            $mime = $attachment->thumbnail_path ? 'image/jpeg' : $attachment->mime_type;
            $stream = $disk->readStream($path);

            if ($stream === false) {
                $this->logWarning('thumb:read-stream-false', $context + [
                    'resolved_path' => $path,
                    'mime' => $mime,
                ]);

                return response()->json(['message' => 'Unable to read file.'], 500);
            }

            $this->logInfo('thumb:stream-opened', $context + [
                'resolved_path' => $path,
                'mime' => $mime,
                'is_resource' => is_resource($stream),
            ]);

            return response()->stream(function () use ($stream) {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="thumb-' . $attachment->original_name . '"',
                'Cache-Control' => 'public, max-age=604800, immutable',
            ]);
        } catch (\Throwable $e) {
            $this->logError('thumb:failed', $e, $context);

            return response()->json(['message' => 'Unable to read thumbnail.'], 500);
        }
    }

    public function destroy(Request $request, CardAttachment $attachment): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->canDeleteCards(), 403);

        $attachment->loadMissing('card');
        $board = BoardWriteGuard::forCard($attachment->card);

        if (! $actor->isAdmin()) {
            $isMember = $actor->boards()->where('boards.id', $board->id)->exists();
            abort_unless($isMember, 403);
        }

        $context = $this->baseLogContext($request, $attachment) + [
            'stored_path' => $attachment->stored_path,
            'thumbnail_path' => $attachment->thumbnail_path,
        ];

        try {
            $disk = Storage::disk($this->disk());

            foreach ([$attachment->stored_path, $attachment->thumbnail_path] as $path) {
                if ($path && $disk->exists($path)) {
                    try {
                        $disk->delete($path);

                        $this->logInfo('destroy:file-deleted', $context + [
                            'deleted_path' => $path,
                        ]);
                    } catch (\Throwable $e) {
                        $this->logError('destroy:file-delete-failed', $e, $context + [
                            'deleted_path' => $path,
                        ]);
                    }
                }
            }

            $cardId = (int) $attachment->card_id;
            $id = (int) $attachment->id;

            $attachment->delete();

            $this->logInfo('destroy:db-deleted', $context + [
                'deleted_attachment_id' => $id,
            ]);

            Audit::log($actor, 'attachment_deleted', 'card', $cardId, [
                'attachment_id' => $id,
            ]);

            return response()->json(null, 204);
        } catch (\Throwable $e) {
            $this->logError('destroy:failed', $e, $context);

            return response()->json([
                'message' => 'No se pudo eliminar el adjunto.',
            ], 500);
        }
    }

    private function generateImageThumbnail(UploadedFile $file, string $dir, string $diskName): ?string
    {
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());

            $targetWidth = 1024;
            $targetHeight = 1024;

            $image = $image->scaleDown(width: $targetWidth, height: $targetHeight);

            $image = $image->cover(1024, 1024);
            $offsetX = (int) floor(($targetWidth - $image->width()) / 2);
            $offsetY = (int) floor(($targetHeight - $image->height()) / 2);

            $canvas->place($image, 'top-left', $offsetX, $offsetY);

            $thumbName = Str::uuid()->toString() . '.jpg';
            $thumbPath = $dir . '/' . $thumbName;

            $saved = Storage::disk($diskName)->put($thumbPath, (string) $canvas->toJpeg(76));

            if (! $saved) {
                Log::warning('[CardAttachment] thumbnail:not-saved', [
                    'disk' => $diskName,
                    'dir' => $dir,
                    'thumb_path' => $thumbPath,
                    'original_name' => $file->getClientOriginalName(),
                ]);

                return null;
            }

            Log::info('[CardAttachment] thumbnail:saved', [
                'disk' => $diskName,
                'dir' => $dir,
                'thumb_path' => $thumbPath,
                'original_name' => $file->getClientOriginalName(),
            ]);

            return $thumbPath;
        } catch (\Throwable $e) {
            Log::error('[CardAttachment] thumbnail:failed', [
                'disk' => $diskName,
                'dir' => $dir,
                'original_name' => $file->getClientOriginalName(),
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'El archivo supera el límite permitido por el servidor (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido por el formulario.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió de forma incompleta.',
            UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo.',
            default => 'El archivo falló al subirse.',
        };
    }

    private function serialize(CardAttachment $a): array
    {
        $isImage = str_starts_with((string) $a->mime_type, 'image/');

        $thumbUrl = $isImage ? url("/api/attachments/{$a->id}/thumb") : null;
        $previewUrl = $isImage ? url("/api/attachments/{$a->id}/preview") : null;
        $downloadUrl = url("/api/attachments/{$a->id}/download");

        Log::info('[CardAttachment] serialize:urls', [
            'attachment_id' => $a->id,
            'app_url' => config('app.url'),
            'request_root' => request()?->root(),
            'request_host' => request()?->getHost(),
            'request_scheme' => request()?->getScheme(),
            'thumb_url' => $thumbUrl,
            'preview_url' => $previewUrl,
            'download_url' => $downloadUrl,
        ]);

        return [
            'id' => $a->id,
            'card_id' => (int) $a->card_id,
            'original_name' => $a->original_name,
            'mime_type' => $a->mime_type,
            'size' => (int) $a->size,
            'created_at' => $a->created_at,
            'thumb_url' => $thumbUrl,
            'preview_url' => $previewUrl,
            'download_url' => $downloadUrl,
        ];
    }
}
