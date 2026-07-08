<?php

declare(strict_types=1);

namespace Urbania\Auth\Presentation;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Urbania\Auth\Application\UseCases\ForgotPasswordUseCase;
use Urbania\Auth\Application\UseCases\LoginUseCase;
use Urbania\Auth\Application\UseCases\LogoutUseCase;
use Urbania\Auth\Application\UseCases\RefreshTokenUseCase;
use Urbania\Auth\Application\UseCases\RegisterUserUseCase;
use Urbania\Auth\Application\UseCases\ResetPasswordUseCase;
use Urbania\Auth\Domain\Repositories\ContactRepositoryInterface;
use Urbania\Auth\Domain\Repositories\InvitationRepositoryInterface;
use Urbania\Auth\Domain\Repositories\OrganizationRepositoryInterface;
use Urbania\Auth\Domain\Repositories\PasswordResetTokenRepositoryInterface;
use Urbania\Auth\Domain\Repositories\RefreshTokenRepositoryInterface;
use Urbania\Auth\Domain\Repositories\UserRepositoryInterface;
use Urbania\Auth\Infrastructure\Repositories\EloquentContactRepository;
use Urbania\Auth\Infrastructure\Repositories\EloquentInvitationRepository;
use Urbania\Auth\Infrastructure\Repositories\EloquentOrganizationRepository;
use Urbania\Auth\Infrastructure\Repositories\EloquentPasswordResetTokenRepository;
use Urbania\Auth\Infrastructure\Repositories\EloquentRefreshTokenRepository;
use Urbania\Auth\Infrastructure\Repositories\EloquentUserRepository;
use Urbania\Mfa\Domain\Repositories\UserMfaRepositoryInterface;
use Urbania\Shared\JWT\JwtService;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        JsonResource::withoutWrapping();

        $this->app->singleton(OrganizationRepositoryInterface::class, EloquentOrganizationRepository::class);
        $this->app->singleton(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->singleton(ContactRepositoryInterface::class, EloquentContactRepository::class);
        $this->app->singleton(InvitationRepositoryInterface::class, EloquentInvitationRepository::class);
        $this->app->singleton(RefreshTokenRepositoryInterface::class, EloquentRefreshTokenRepository::class);
        $this->app->singleton(PasswordResetTokenRepositoryInterface::class, EloquentPasswordResetTokenRepository::class);

        $this->app->singleton(JwtService::class, fn (): JwtService => new JwtService);

        $this->app->singleton(RegisterUserUseCase::class, function (Application $app): RegisterUserUseCase {
            /** @var InvitationRepositoryInterface $invitationRepo */
            $invitationRepo = $app->make(InvitationRepositoryInterface::class);
            /** @var UserRepositoryInterface $userRepo */
            $userRepo = $app->make(UserRepositoryInterface::class);
            /** @var ContactRepositoryInterface $contactRepo */
            $contactRepo = $app->make(ContactRepositoryInterface::class);

            return new RegisterUserUseCase($invitationRepo, $userRepo, $contactRepo);
        });

        $this->app->singleton(LoginUseCase::class, function (Application $app): LoginUseCase {
            /** @var UserRepositoryInterface $userRepo */
            $userRepo = $app->make(UserRepositoryInterface::class);
            /** @var JwtService $jwtService */
            $jwtService = $app->make(JwtService::class);
            /** @var UserMfaRepositoryInterface|null $mfaRepo */
            $mfaRepo = $app->has(UserMfaRepositoryInterface::class)
                ? $app->make(UserMfaRepositoryInterface::class)
                : null;

            return new LoginUseCase($userRepo, $jwtService, $mfaRepo);
        });

        $this->app->singleton(RefreshTokenUseCase::class, function (Application $app): RefreshTokenUseCase {
            /** @var UserRepositoryInterface $userRepo */
            $userRepo = $app->make(UserRepositoryInterface::class);
            /** @var RefreshTokenRepositoryInterface $refreshTokenRepo */
            $refreshTokenRepo = $app->make(RefreshTokenRepositoryInterface::class);
            /** @var JwtService $jwtService */
            $jwtService = $app->make(JwtService::class);
            /** @var UserMfaRepositoryInterface|null $mfaRepo */
            $mfaRepo = $app->has(UserMfaRepositoryInterface::class)
                ? $app->make(UserMfaRepositoryInterface::class)
                : null;

            return new RefreshTokenUseCase($userRepo, $refreshTokenRepo, $jwtService, $mfaRepo);
        });

        $this->app->singleton(LogoutUseCase::class, function (Application $app): LogoutUseCase {
            /** @var RefreshTokenRepositoryInterface $refreshTokenRepo */
            $refreshTokenRepo = $app->make(RefreshTokenRepositoryInterface::class);
            /** @var JwtService $jwtService */
            $jwtService = $app->make(JwtService::class);

            return new LogoutUseCase($refreshTokenRepo, $jwtService);
        });

        $this->app->singleton(ForgotPasswordUseCase::class, function (Application $app): ForgotPasswordUseCase {
            /** @var UserRepositoryInterface $userRepo */
            $userRepo = $app->make(UserRepositoryInterface::class);
            /** @var PasswordResetTokenRepositoryInterface $passwordResetTokenRepo */
            $passwordResetTokenRepo = $app->make(PasswordResetTokenRepositoryInterface::class);

            return new ForgotPasswordUseCase($userRepo, $passwordResetTokenRepo);
        });

        $this->app->singleton(ResetPasswordUseCase::class, function (Application $app): ResetPasswordUseCase {
            /** @var PasswordResetTokenRepositoryInterface $passwordResetTokenRepo */
            $passwordResetTokenRepo = $app->make(PasswordResetTokenRepositoryInterface::class);
            /** @var UserRepositoryInterface $userRepo */
            $userRepo = $app->make(UserRepositoryInterface::class);

            return new ResetPasswordUseCase($passwordResetTokenRepo, $userRepo);
        });
    }

    public function boot(): void
    {
        //
    }
}
