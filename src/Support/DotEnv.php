<?php

namespace Rogierw\RwAcmeCli\Support;

use InvalidArgumentException;
use RuntimeException;

class DotEnv
{
    protected string $basePath;
    protected string $envFile;

    public function __construct(string $basePath, string $envFile)
    {
        if (! file_exists($envFile)) {
            throw new InvalidArgumentException(sprintf('%s does not exist', $envFile));
        }

        $this->basePath = $basePath;
        $this->envFile = $envFile;
    }

    public function load(): void
    {
        if (! is_readable($this->envFile)) {
            throw new RuntimeException(sprintf('%s file is not readable', $this->envFile));
        }

        $lines = file($this->envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines[] = sprintf('APP_BASE_PATH=%s', $this->basePath);

        $cache = [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);

            $name = trim($name);
            $value = trim($value);

            if (! array_key_exists($name, $_SERVER) && ! array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));

                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                $cache[$name] = $value;
            }
        }

        $cachePath = storage_path('cache');

        if (is_dir($cachePath)) {
            File::write($cachePath . DIRECTORY_SEPARATOR . 'env.cache.json', json_encode($cache));
        }
    }

    public static function update(array $attributes): void
    {
        $config = json_decode(
            File::read(storage_path('cache' . DIRECTORY_SEPARATOR . 'env.cache.json')),
            true
        );

        unset($config['APP_BASE_PATH']);

        $envContent = '';
        foreach (array_merge($config, $attributes) as $name => $value) {
            $envContent .= sprintf("%s=%s" . PHP_EOL, $name, $value);
        }

        File::write(base_path('.env'), $envContent);
    }
}
