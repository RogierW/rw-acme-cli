<?php

namespace Rogierw\RwAcmeCli;

use Rogierw\RwAcme\Api;
use Rogierw\RwAcmeCli\Actions\CreateAccountAction;
use Rogierw\RwAcmeCli\Commands\AccountDetailsCommand;
use Rogierw\RwAcmeCli\Commands\CreateAccountCommand;
use Rogierw\RwAcmeCli\Commands\RenewCertificateCommand;
use Rogierw\RwAcmeCli\Commands\OrderOrderCertificateCommand;
use Symfony\Component\Console\Application;

class ConsoleApplication extends Application
{
    protected array $container = [];

    public function __construct()
    {
        parent::__construct('RW Acme CLI', '1.0.0');

        $this->add(new CreateAccountCommand);
        $this->add(new AccountDetailsCommand);
        $this->add(new OrderOrderCertificateCommand);
        $this->add(new RenewCertificateCommand);
    }

    public function bootstrap(): void
    {
        $client = new Api(getenv('EMAIL'), account_path());

        if (!$client->account()->exists()) {
            (new CreateAccountAction)->execute(getenv('EMAIL'));
        }

        $this->container['acmeClient'] = $client;
    }

    public function resolve(string $item)
    {
        if (array_key_exists($item, $this->container)) {
            return $this->container[$item];
        }

        return null;
    }

    public function getLongVersion()
    {
        return parent::getLongVersion().' by <comment>RogierW</comment>';
    }
}