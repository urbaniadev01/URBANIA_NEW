<?php

declare(strict_types=1);

namespace Urbania\Mfa\Presentation;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Urbania\Mfa\Application\Services\RecoveryCodeService;
use Urbania\Mfa\Application\Services\TotpService;
use Urbania\Mfa\Application\UseCases\ConfirmMfaUseCase;
use Urbania\Mfa\Application\UseCases\DisableMfaUseCase;
use Urbania\Mfa\Application\UseCases\EnrollMfaUseCase;
use Urbania\Mfa\Application\UseCases\RegenerateRecoveryCodesUseCase;
use Urbania\Mfa\Application\UseCases\VerifyMfaUseCase;
use Urbania\Mfa\Domain\Repositories\UserMfaRepositoryInterface;
use Urbania\Mfa\Infrastructure\Repositories\EloquentUserMfaRepository;
use Urbania\Shared\JWT\JwtService;

final class MfaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserMfaRepositoryInterface::class, EloquentUserMfaRepository::class);

        $this->app->singleton(TotpService::class, fn (): TotpService => new TotpService);
        $this->app->singleton(RecoveryCodeService::class, fn (): RecoveryCodeService => new RecoveryCodeService);

        $this->app->singleton(EnrollMfaUseCase::class, function (Application $app): EnrollMfaUseCase {
            /** @var UserMfaRepositoryInterface $mfaRepo */
            $mfaRepo = $app->make(UserMfaRepositoryInterface::class);
            /** @var TotpService $totpService */
            $totpService = $app->make(TotpService::class);
            /** @var RecoveryCodeService $recoveryCodeService */
            $recoveryCodeService = $app->make(RecoveryCodeService::class);

            return new EnrollMfaUseCase($mfaRepo, $totpService, $recoveryCodeService);
        });

        $this->app->singleton(ConfirmMfaUseCase::class, function (Application $app): ConfirmMfaUseCase {
            /** @var UserMfaRepositoryInterface $mfaRepo */
            $mfaRepo = $app->make(UserMfaRepositoryInterface::class);
            /** @var TotpService $totpService */
            $totpService = $app->make(TotpService::class);

            return new ConfirmMfaUseCase($mfaRepo, $totpService);
        });

        $this->app->singleton(VerifyMfaUseCase::class, function (Application $app): VerifyMfaUseCase {
            /** @var UserMfaRepositoryInterface $mfaRepo */
            $mfaRepo = $app->make(UserMfaRepositoryInterface::class);
            /** @var TotpService $totpService */
            $totpService = $app->make(TotpService::class);
            /** @var RecoveryCodeService $recoveryCodeService */
            $recoveryCodeService = $app->make(RecoveryCodeService::class);
            /** @var JwtService $jwtService */
            $jwtService = $app->make(JwtService::class);

            return new VerifyMfaUseCase($mfaRepo, $totpService, $recoveryCodeService, $jwtService);
        });

        $this->app->singleton(DisableMfaUseCase::class, function (Application $app): DisableMfaUseCase {
            /** @var UserMfaRepositoryInterface $mfaRepo */
            $mfaRepo = $app->make(UserMfaRepositoryInterface::class);
            /** @var TotpService $totpService */
            $totpService = $app->make(TotpService::class);

            return new DisableMfaUseCase($mfaRepo, $totpService);
        });

        $this->app->singleton(RegenerateRecoveryCodesUseCase::class, function (Application $app): RegenerateRecoveryCodesUseCase {
            /** @var UserMfaRepositoryInterface $mfaRepo */
            $mfaRepo = $app->make(UserMfaRepositoryInterface::class);
            /** @var TotpService $totpService */
            $totpService = $app->make(TotpService::class);
            /** @var RecoveryCodeService $recoveryCodeService */
            $recoveryCodeService = $app->make(RecoveryCodeService::class);

            return new RegenerateRecoveryCodesUseCase($mfaRepo, $totpService, $recoveryCodeService);
        });
    }

    public function boot(): void
    {
        //
    }
}
