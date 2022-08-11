<?php

function base_path(string $path = ''): string
{
    return getenv('APP_BASE_PATH') . ($path != '' ? DIRECTORY_SEPARATOR . $path : '');
}

function storage_path(string $path = ''): string
{
    $storagePath = getenv('STORAGE_PATH');

    if (str_starts_with($storagePath, DIRECTORY_SEPARATOR)) {
        return $storagePath . ($path != '' ? DIRECTORY_SEPARATOR . $path : '');
    }

    return base_path($storagePath . ($path != '' ? DIRECTORY_SEPARATOR . $path : ''));
}

function account_path(string $path = ''): string
{
    return storage_path('__account' . ($path != '' ? DIRECTORY_SEPARATOR . $path : ''));
}