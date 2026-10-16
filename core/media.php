<?php
// Media library: uploads are validated by content, images are resized and converted to WebP.

const PB_UPLOAD_MAX_BYTES = 10 * 1024 * 1024;
const PB_IMAGE_MAX_WIDTH = 1920;
const PB_THUMB_WIDTH = 480;
/** The copy in between: a phone (about 400 px, two or three pixels each) takes this one instead of the 1920 px original. */
const PB_MID_WIDTH = 960;
/** Larger images would need more memory than shared hosting gives PHP (about 4 bytes per pixel). */
const PB_IMAGE_MAX_PIXELS = 50_000_000;
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

/** Where the middle copy of a picture lives (path = "2026/10/abc.webp" → "2026/10/abc-960.webp"); only pictures made since 1.8.0 (or "redone", see pb_media_make_mid) have it. */
function pb_media_mid_path(string $path): string
{
    return (string) preg_replace('~\.(\w+)$~', '-' . PB_MID_WIDTH . '.$1', $path);
}

/**
 * The srcset of a picture: the copies it has, smallest first, each with its width ("thumb 480w, mid 960w, full 1920w"). '' when it has no smaller copy.
 * Only an <img> that asks for it (PbValue::img() with $sizes) gets it, so the HTML a theme already prints stays as it was.
 */
function pb_media_srcset(array $media): string
{
    $width = (int) ($media['width'] ?? 0);
    $set = [];
    if (($media['thumb_path'] ?? '') !== '' && $width > PB_THUMB_WIDTH) {
        $set[] = pb_url('content/uploads/' . $media['thumb_path']) . ' ' . PB_THUMB_WIDTH . 'w';
    }
    $mid = pb_media_mid_path((string) ($media['path'] ?? ''));
    if ($width > PB_MID_WIDTH && is_file(pb_uploads_dir() . "/$mid")) {
        $set[] = pb_url("content/uploads/$mid") . ' ' . PB_MID_WIDTH . 'w';
    }
    return $set ? implode(', ', [...$set, pb_media_url($media) . " {$width}w"]) : '';
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
    if (!is_file($source)) {
        throw new InvalidArgumentException(sprintf(__('O arquivo "%s" não foi encontrado.'), $originalName));
    }
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
    [$width, $height] = @getimagesize($source) ?: [0, 0];
    if ($width * $height > PB_IMAGE_MAX_PIXELS) {
        throw new InvalidArgumentException(__('A imagem tem pixels demais para a hospedagem processar. Reduza o tamanho (por exemplo, para 4000 pixels de largura) e envie de novo.'));
    }
    $image = pb_image_open($source, $mime);

    $ext = function_exists('imagewebp') ? 'webp' : ($mime === 'image/jpeg' ? 'jpg' : 'png');
    $full = pb_image_resize($image, PB_IMAGE_MAX_WIDTH);
    pb_image_save($full, "$base.$ext", $ext);
    pb_image_save(pb_image_resize($image, PB_THUMB_WIDTH), "$base-thumb.$ext", $ext);
    if (imagesx($full) > PB_MID_WIDTH) {
        pb_image_save(pb_image_resize($image, PB_MID_WIDTH), "$base-" . PB_MID_WIDTH . ".$ext", $ext);
    }
    return ["$base.$ext", "$base-thumb.$ext", imagesx($full), imagesy($full)];
}

/** Opens an image file for GD: upright, in true colour and with its transparency. Throws with a message the user can read. */
function pb_image_open(string $source, string $mime): GdImage
{
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
    return $image;
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
    foreach ([$media['path'], $media['thumb_path'], $media['path'] !== '' && $media['mime'] !== 'application/pdf' ? pb_media_mid_path($media['path']) : ''] as $path) {
        if ($path !== '' && is_file(pb_uploads_dir() . "/$path")) {
            unlink(pb_uploads_dir() . "/$path");
        }
    }
    pb_db()->prepare('DELETE FROM ' . pb_table('media') . ' WHERE id = ?')->execute([$id]);
}

/** The pictures of the library that are wide enough to have a middle copy and do not have it yet (the ones sent before 1.8.0). */
function pb_media_without_mid(): array
{
    return array_values(array_filter(pb_media_list(true), fn($m) => (int) $m['width'] > PB_MID_WIDTH && $m['path'] !== ''
        && !is_file(pb_uploads_dir() . '/' . pb_media_mid_path($m['path']))));
}

/** Makes the middle copy of up to $max pictures that lack it. Returns how many it made. */
function pb_media_make_mids(int $max): int
{
    $done = 0;
    foreach (array_slice(pb_media_without_mid(), 0, $max) as $media) {
        $done += pb_media_make_mid($media) ? 1 : 0;
    }
    return $done;
}

/** Makes the middle copy of one picture from its full-size file. Returns false when it cannot (the file is gone, or the picture is too big for the host). */
function pb_media_make_mid(array $media): bool
{
    $source = pb_uploads_dir() . '/' . $media['path'];
    if (!is_file($source) || (int) $media['width'] <= PB_MID_WIDTH) {
        return false;
    }
    try {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($source);
        [$width, $height] = @getimagesize($source) ?: [0, 0];
        if (!in_array($mime, PB_IMAGE_TYPES, true) || $width * $height > PB_IMAGE_MAX_PIXELS) {
            return false;
        }
        @ini_set('memory_limit', '256M');
        $image = pb_image_open($source, $mime);
        pb_image_save(pb_image_resize($image, PB_MID_WIDTH), pb_media_mid_path($media['path']), (string) pathinfo($media['path'], PATHINFO_EXTENSION));
        return true;
    } catch (Throwable $e) {
        error_log('PageBrick: no middle copy of ' . $media['path'] . ': ' . $e->getMessage());
        return false;
    }
}
