<?php

namespace Rogierw\RwAcmeCli\Support;

class File
{
    public static function read(string $file): string
    {
        $fp = fopen($file, 'r');

        $contents = fread($fp, filesize($file));

        fclose($fp);

        return $contents;
    }

    public static function write(string $file, string $content): void
    {
        $fp = fopen($file, 'w');

        fwrite($fp, $content);

        fclose($fp);
    }
}
