<?php

namespace Rogierw\RwAcmeCli;

use Rogierw\RwAcme\Api;
use Rogierw\RwAcmeCli\Actions\CreateAccountAction;
use Rogierw\RwAcmeCli\Commands\AccountDetailsCommand;
use Rogierw\RwAcmeCli\Commands\CreateAccountCommand;
use Rogierw\RwAcmeCli\Commands\OrderCertificateCommand;
use Rogierw\RwAcmeCli\Commands\RenewCertificateCommand;
use Symfony\Component\Console\Application;

class ConsoleApplication extends Application
{
    protected array $container = [];

    public function __construct()
    {
        parent::__construct('RW Acme CLI', '1.0.0');

        $this->add(new CreateAccountCommand());
        $this->add(new AccountDetailsCommand());
        $this->add(new OrderCertificateCommand());
        $this->add(new RenewCertificateCommand());
    }

    public function bootstrap(): void
    {
        $this->createRequiredDirectories();

        $client = new Api(getenv('EMAIL'), account_path());


        if (! $client->account()->exists()) {
            (new CreateAccountAction())->execute(getenv('EMAIL'));
        }

        $this->container['acmeClient'] = $client;
    }

    public function resolve(string $item): mixed
    {
        if (array_key_exists($item, $this->container)) {
            return $this->container[$item];
        }

        return null;
    }

    public function getLongVersion(): string
    {
        return parent::getLongVersion().' by <comment>RogierW</comment>';
    }

    private function createRequiredDirectories(): void
    {
        $dirs = [
            '__account' => storage_path('__account'),
            'cache' => storage_path('cache'),
            'pem_files' => storage_path('pem_files'),
        ];

        foreach ($dirs as $path) {
            if (! is_dir($path)) {
                @mkdir($path);
            }
        }
    }
}
