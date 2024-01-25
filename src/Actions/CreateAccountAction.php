<?php

namespace Rogierw\RwAcmeCli\Actions;

use Rogierw\RwAcme\Api;
use Rogierw\RwAcme\Support\LocalFileAccount;
use Rogierw\RwAcmeCli\Support\DotEnv;
use Rogierw\RwAcmeCli\Support\File;

class CreateAccountAction
{
    public function execute(string $email): void
    {
        $localAccount = new LocalFileAccount(account_path(), $email);

        $client = new Api(localAccount: $localAccount);

        $account = $client->account()->create();

        File::write(
            account_path('info'),
            vsprintf('%s;%s;%s;%s;%s;', [
                $account->id,
                $account->status,
                $account->contact[0],
                $account->initialIp,
                $account->createdAt,
            ])
        );

        if ($email !== getenv('EMAIL')) {
            DotEnv::update(['EMAIL' => $email]);
        }
    }
}
