<?php

namespace Rogierw\RwAcmeCli\Actions;

use Rogierw\RwAcme\Api;
use Rogierw\RwAcmeCli\Support\DotEnv;
use Rogierw\RwAcmeCli\Support\File;

class CreateAccountAction
{
    public function execute(string $email): void
    {
        $client = new Api($email, account_path());
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
