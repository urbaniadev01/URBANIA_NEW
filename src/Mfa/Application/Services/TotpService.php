<?php

declare(strict_types=1);

namespace Urbania\Mfa\Application\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;

final readonly class TotpService
{
    private const WINDOW = 1;

    private string $issuer;

    public function __construct()
    {
        $this->issuer = (string) config('app.name', 'Urbania');
    }

    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    public function verify(string $secret, string $code): bool
    {
        $totp = TOTP::createFromSecret($secret);

        return $totp->verify($code, null, self::WINDOW);
    }

    public function generateQrCodeBase64(string $secret, string $email): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($email);
        $totp->setIssuer($this->issuer);

        $provisioningUri = $totp->getProvisioningUri();

        $result = Builder::create()
            ->writer(new PngWriter)
            ->data($provisioningUri)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(250)
            ->margin(10)
            ->build();

        return $result->getDataUri();
    }

    public function getCurrentCode(string $secret): string
    {
        $totp = TOTP::createFromSecret($secret);

        return $totp->now();
    }
}
