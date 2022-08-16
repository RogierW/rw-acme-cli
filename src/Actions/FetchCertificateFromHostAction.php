<?php

namespace Rogierw\RwAcmeCli\Actions;

use Carbon\Carbon;
use RuntimeException;
use Spatie\SslCertificate\SslCertificate;

class FetchCertificateFromHostAction
{
    public function execute(string $hostName, bool $verifyCertificate = true): SslCertificate
    {
        $certificate = SslCertificate::createForHostName($hostName, 30, $verifyCertificate);

        if (! $certificate->expirationDate() instanceof Carbon) {
            throw new RuntimeException('Invalid expiration date.');
        }

        return $certificate;
    }
}
