<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserAvatarController extends Controller
{
    private function disk(): string
    {
        return config('filesystems.avatars_disk', 'attachments_local');
    }

    public function show(Request $request, User $user)
    {
        $path = $user->avatar_path;
        $disk = Storage::disk($this->disk());

        if (! $path || ! $disk->exists($path)) {
            return response()->json([
                'message' => 'Avatar no encontrado.',
            ], 404);
        }

        $mime = $disk->mimeType($path) ?: 'image/jpeg';
        $stream = $disk->readStream($path);

        if ($stream === false) {
            return response()->json([
                'message' => 'No se pudo leer el avatar.',
            ], 500);
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function thumb(Request $request, User $user)
    {
        $path = $user->avatar_thumb_path ?: $user->avatar_path;
        $disk = Storage::disk($this->disk());

        if (! $path || ! $disk->exists($path)) {
            return response()->json([
                'message' => 'Avatar no encontrado.',
            ], 404);
        }

        $mime = $disk->mimeType($path) ?: 'image/jpeg';
        $stream = $disk->readStream($path);

        if ($stream === false) {
            return response()->json([
                'message' => 'No se pudo leer la miniatura del avatar.',
            ], 500);
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $isSelf = (int) $actor->id === (int) $user->id;
        abort_unless($actor->isAdmin() || $isSelf, 403);

        if (! $request->hasFile('file')) {
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
            return response()->json([
                'message' => 'El archivo recibido es inválido.',
                'errors' => [
                    'file' => ['El archivo recibido es inválido.'],
                ],
            ], 422);
        }

        if (! $file->isValid()) {
            $phpError = $file->getError();

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
            'file' => [
                'required',
                'file',
                'image',
                'max:3072',
                'mimes:jpg,jpeg,png,webp',
                'dimensions:min_width=128,min_height=128,max_width=4000,max_height=4000',
            ],
        ], [
            'file.required' => 'Debes seleccionar una imagen.',
            'file.file' => 'El archivo enviado no es válido.',
            'file.image' => 'El archivo debe ser una imagen.',
            'file.max' => 'La imagen supera el tamaño máximo permitido de 3MB.',
            'file.mimes' => 'Formato no permitido. Solo se aceptan JPG, PNG y WEBP.',
            'file.dimensions' => 'La imagen debe tener al menos 128x128 y como máximo 4000x4000 píxeles.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first('file') ?: 'No se pudo validar la imagen.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $disk = Storage::disk($this->disk());

        $oldPath = $user->avatar_path;
        $oldThumbPath = $user->avatar_thumb_path;

        $dir = "avatars/users/{$user->id}";
        $baseName = Str::uuid()->toString();

        $storedPath = $dir . '/' . $baseName . '.jpg';
        $thumbPath = $dir . '/' . $baseName . '_thumb.jpg';

        try {
            $manager = new ImageManager(new Driver());

            $image = $manager->read($file->getRealPath());

            $width = $image->width();
            $height = $image->height();
            $square = min($width, $height);

            $offsetX = (int) floor(($width - $square) / 2);
            $offsetY = (int) floor(($height - $square) / 2);

            $baseImage = $image->crop($square, $square, $offsetX, $offsetY);

            $mainImage = clone $baseImage;
            $mainImage = $mainImage->resize(256, 256);
            $mainEncoded = $mainImage->toJpeg(82);

            $thumbImage = clone $baseImage;
            $thumbImage = $thumbImage->resize(64, 64);
            $thumbEncoded = $thumbImage->toJpeg(78);

            $savedMain = $disk->put($storedPath, (string) $mainEncoded);
            $savedThumb = $disk->put($thumbPath, (string) $thumbEncoded);

            if (! $savedMain || ! $savedThumb) {
                return response()->json([
                    'message' => 'No se pudo guardar el avatar.',
                    'errors' => [
                        'file' => ['No se pudo guardar el avatar.'],
                    ],
                ], 500);
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Ocurrió un error al procesar la imagen del avatar.',
                'errors' => [
                    'file' => ['Ocurrió un error al procesar la imagen del avatar.'],
                ],
            ], 500);
        }

        $user->update([
            'avatar_path' => $storedPath,
            'avatar_thumb_path' => $thumbPath,
        ]);

        foreach ([$oldPath, $oldThumbPath] as $path) {
            if ($path && $path !== $storedPath && $path !== $thumbPath && $disk->exists($path)) {
                try {
                    $disk->delete($path);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        Audit::log($actor, 'user_avatar_updated', 'user', (int) $user->id);

        return response()->json([
            'data' => [
                'user_id' => (int) $user->id,
                'avatar_url' => "/api/users/{$user->id}/avatar",
                'avatar_thumb_url' => "/api/users/{$user->id}/avatar/thumb",
            ],
        ], 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $isSelf = (int) $actor->id === (int) $user->id;
        abort_unless($actor->isAdmin() || $isSelf, 403);

        $disk = Storage::disk($this->disk());
        $oldPath = $user->avatar_path;
        $oldThumbPath = $user->avatar_thumb_path;

        foreach ([$oldPath, $oldThumbPath] as $path) {
            if ($path && $disk->exists($path)) {
                try {
                    $disk->delete($path);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $user->update([
            'avatar_path' => null,
            'avatar_thumb_path' => null,
        ]);

        Audit::log($actor, 'user_avatar_deleted', 'user', (int) $user->id);

        return response()->json(null, 204);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'La imagen supera el límite permitido por el servidor (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño máximo permitido por el formulario.',
            UPLOAD_ERR_PARTIAL => 'La imagen se subió de forma incompleta.',
            UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo.',
            default => 'La imagen falló al subirse.',
        };
    }
}
