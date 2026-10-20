<?php
declare(strict_types=1);
require_once __DIR__ . "/ContentValidation.php";
final class MediaValidation
{
    public static function upload(array $file): array
    {
        if (
            !isset($file["error"]) ||
            is_array($file["error"]) ||
            $file["error"] !== UPLOAD_ERR_OK
        ) {
            throw new InvalidArgumentException(
                "Upload failed. Check the host upload limit and choose an image up to 8 MB.",
            );
        }
        if (
            !is_string($file["tmp_name"] ?? null) ||
            !is_string($file["name"] ?? null) ||
            !is_uploaded_file($file["tmp_name"]) ||
            filesize($file["tmp_name"]) < 1 ||
            filesize($file["tmp_name"]) > 8 * 1024 * 1024
        ) {
            throw new InvalidArgumentException("Choose an image up to 8 MB.");
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
        $extensions = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp",
            "image/gif" => "gif",
        ];
        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException(
                "Only JPEG, PNG, WebP and GIF images are accepted.",
            );
        }
        $size = @getimagesize($file["tmp_name"]);
        if (
            !$size ||
            $size["mime"] !== $mime ||
            $size[0] > 10000 ||
            $size[1] > 10000 ||
            $size[0] * $size[1] > 40000000
        ) {
            throw new InvalidArgumentException(
                "Invalid image or dimensions above 10,000 pixels / 40 megapixels.",
            );
        }
        return [
            "extension" => $extensions[$mime],
            "mime" => $mime,
            "width" => $size[0],
            "height" => $size[1],
        ];
    }
    public static function details(array $data): array
    {
        return [
            "title" => ContentValidation::text($data, "title", 200, true),
            "alt_text" => ContentValidation::text($data, "alt_text", 500),
            "caption" => ContentValidation::text($data, "caption", 1000),
        ];
    }
}
