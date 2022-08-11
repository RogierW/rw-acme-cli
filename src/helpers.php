<?php

function base_path(string $path = ''): string
{
    return getenv('APP_BASE_PATH') . '/' . $path;
}

function tmp_path(string $path = ''): string
{
    return base_path('storage/tmp/' . $path);
}

function account_path(string $path = ''): string
{
    return base_path(getenv('ACCOUNT_PATH') . $path);
}

function pem_path(string $path = ''): string
{
    return base_path('storage/pem_files/' . $path);
}
