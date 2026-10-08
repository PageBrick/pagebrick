<?php
// Media library: uploads are validated by content, images are resized and converted to WebP.

const PB_UPLOAD_MAX_BYTES = 10 * 1024 * 1024;
const PB_IMAGE_MAX_WIDTH = 1920;
const PB_THUMB_WIDTH = 480;
const PB_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function pb_uploads_dir(): string
{
    return $GLOBALS['pb_config']['uploads_dir'] ?? PB_ROOT . '/content/uploads';
}

function pb_media_url(array $media, string $size = 'full'): string
{
    $path = $size === 'thumb' && $media['thumb_path'] !== '' ? $media['thumb_path'] : $media['path'];
    return pb_url('content/uploads/' . $path);
}

function pb_media_find(int $id): ?array
{
    $st = pb_db()->prepare('SELECT * FROM ' . pb_table('media') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

// ponytail: loads the whole library; add paging when sites reach thousands of files.
function pb_media_list(bool $imagesOnly = false): array
{
    return pb_db()->query('SELECT * FROM ' . pb_table('media') . ($imagesOnly ? " WHERE mime LIKE 'image/%'" : '') . ' ORDER BY id DESC')->fetchAll();
}

/** Handles one entry of $_FILES. Returns the media id; throws with a message the user can read. */
function pb_media_upload(array $file): int
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(__('O arquivo passa do limite da hospedagem (%s).'), ini_get('upload_max_filesize')),
            UPLOAD_ERR_NO_FILE => __('Escolha um arquivo.'),
            default => __('O envio falhou. Tente de novo.'),
        });
    }
    return pb_media_store($file['tmp_name'], (string) ($file['name'] ?? 'arquivo'));
}

/** Stores a file already on disk in the library. The type is detected from the content, never from the name. */
function pb_media_store(string $source, string $originalName): int
{
    if (filesize($source) > PB_UPLOAD_MAX_BYTES) {
        throw new InvalidArgumentException(__('O arquivo passa de 10 MB.'));
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
    if ($mime !== 'application/pdf' && !in_array($mime, PB_IMAGE_TYPES, true)) {
        throw new InvalidArgumentException(__('Tipo de arquivo não aceito. Envie JPG, PNG, WebP, GIF ou PDF.'));
    }

    $folder = date('Y/m');
    if (!is_dir(pb_uploads_dir() . "/$folder") && !mkdir(pb_uploads_dir() . "/$folder", 0755, true)) {
        throw new RuntimeException('Cannot create ' . pb_uploads_dir() . "/$folder");
    }
    $base = $folder . '/' . bin2hex(random_bytes(8)); // random name: nothing from the user reaches the file system

    if ($mime === 'application/pdf') {
        if (!copy($source, pb_uploads_dir() . "/$base.pdf")) {
            throw new RuntimeException("Cannot write $base.pdf");
        }
        [$path, $thumb, $width, $height] = ["$base.pdf", '', 0, 0];
    } else {
        [$path, $thumb, $width, $height] = pb_media_process_image($source, $mime, $base);
    }

    $name = pb_limit(basename(str_replace('\\', '/', $originalName)), 255);
    pb_db()->prepare('INSERT INTO ' . pb_table('media') . ' (path, thumb_path, original_name, mime, width, height, size, alt) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$path, $thumb, $name, $mime, $width, $height, filesize(pb_uploads_dir() . "/$path"), '']);
    return (int) pb_db()->lastInsertId();
}

/** Writes a resized full image and a thumbnail. Returns [path, thumb path, width, height]. */
function pb_media_process_image(string $source, string $mime, string $base): array
{
    // ponytail: GD keeps the whole bitmap in memory (~4 bytes/pixel); a 12 MP phone photo needs ~50 MB.
    @ini_set('memory_limit', '256M');
    $image = @imagecreatefromstring((string) file_get_contents($source));
    if (!$image) {
        throw new InvalidArgumentException(__('Não consegui ler esta imagem. Tente salvar como JPG ou PNG.'));
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        // Phones store photos sideways plus an "orientation" note; GD ignores the note.
        $image = match ((int) (@exif_read_data($source)['Orientation'] ?? 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
    imagepalettetotruecolor($image);
    imagealphablending($image, false);
    imagesavealpha($image, true);

    $ext = function_exists('imagewebp') ? 'webp' : ($mime === 'image/jpeg' ? 'jpg' : 'png');
    $full = pb_image_resize($image, PB_IMAGE_MAX_WIDTH);
    pb_image_save($full, "$base.$ext", $ext);
    pb_image_save(pb_image_resize($image, PB_THUMB_WIDTH), "$base-thumb.$ext", $ext);
    return ["$base.$ext", "$base-thumb.$ext", imagesx($full), imagesy($full)];
}

function pb_image_resize(GdImage $image, int $maxWidth): GdImage
{
    [$width, $height] = [imagesx($image), imagesy($image)];
    if ($width <= $maxWidth) {
        return $image;
    }
    $newHeight = max(1, (int) round($height * $maxWidth / $width));
    $resized = imagecreatetruecolor($maxWidth, $newHeight);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
    return $resized;
}

function pb_image_save(GdImage $image, string $path, string $ext): void
{
    $file = pb_uploads_dir() . "/$path";
    $ok = match ($ext) {
        'webp' => imagewebp($image, $file, 82),
        'png' => imagepng($image, $file, 6),
        default => imagejpeg($image, $file, 82),
    };
    if (!$ok) {
        throw new RuntimeException("Cannot write $file");
    }
}

function pb_media_set_alt(int $id, string $alt): void
{
    pb_db()->prepare('UPDATE ' . pb_table('media') . ' SET alt = ? WHERE id = ?')->execute([pb_limit(trim($alt), 255), $id]);
}

function pb_media_delete(int $id): void
{
    $media = pb_media_find($id);
    if (!$media) {
        return;
    }
    foreach ([$media['path'], $media['thumb_path']] as $path) {
        if ($path !== '' && is_file(pb_uploads_dir() . "/$path")) {
            unlink(pb_uploads_dir() . "/$path");
        }
    }
    pb_db()->prepare('DELETE FROM ' . pb_table('media') . ' WHERE id = ?')->execute([$id]);
}
