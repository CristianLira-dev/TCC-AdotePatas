<?php

declare(strict_types=1);

function appValidateUploadedFile(array $file, array $allowedMimeToExtension, int $maximumBytes): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('O arquivo não foi enviado corretamente.');
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('O arquivo recebido é inválido.');
    }

    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maximumBytes) {
        throw new RuntimeException('O arquivo ultrapassa o tamanho permitido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!isset($allowedMimeToExtension[$mime])) {
        throw new RuntimeException('O conteúdo do arquivo não corresponde a um formato permitido.');
    }

    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('A imagem enviada está corrompida ou é inválida.');
    }

    return (string) $allowedMimeToExtension[$mime];
}

function appStoreUploadedFile(
    array $file,
    string $relativeDirectory,
    string $prefix,
    array $allowedMimeToExtension,
    int $maximumBytes
): string {
    $extension = appValidateUploadedFile($file, $allowedMimeToExtension, $maximumBytes);
    $directory = __DIR__ . '/../' . trim($relativeDirectory, '/');

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível preparar o armazenamento do arquivo.');
    }

    $fileName = $prefix . bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $directory . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Não foi possível salvar o arquivo enviado.');
    }

    @chmod($destination, 0644);
    return trim($relativeDirectory, '/') . '/' . $fileName;
}

function appImageMimeMap(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function appVaccinationDocumentMimeMap(): array
{
    return appImageMimeMap() + ['application/pdf' => 'pdf'];
}
