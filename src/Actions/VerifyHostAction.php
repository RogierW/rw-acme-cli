<?php

namespace Rogierw\RwAcmeCli\Actions;

use Spatie\SslCertificate\Exceptions\CouldNotDownloadCertificate;
use Spatie\SslCertificate\SslCertificate;

class VerifyHostAction
{
    public function execute(string $domain, SslCertificate $certificate): void
    {
        $domainSegments = explode('.', $domain);
        $hostSegments = explode('.', $certificate->getDomain());

        if ($hostSegments[0] !== '*' && count($domainSegments) !== count($hostSegments)) {
            throw CouldNotDownloadCertificate::noCertificateInstalled($domain);
        }

        foreach ($hostSegments as $index => $segment) {
            if ($segment === '*') {
                continue;
            }

            if ($domainSegments[$index] !== $segment) {
                throw CouldNotDownloadCertificate::noCertificateInstalled($domain);
            }
        }
    }
}
