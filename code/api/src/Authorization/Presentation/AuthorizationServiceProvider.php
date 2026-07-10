<?php

declare(strict_types=1);

namespace Urbania\Authorization\Presentation;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Urbania\Authorization\Application\Services\PermissionResolver;
use Urbania\Authorization\Application\UseCases\CheckPermissionUseCase;
use Urbania\Authorization\Domain\Repositories\PermissionRepositoryInterface;
use Urbania\Authorization\Domain\Repositories\RoleAssignmentRepositoryInterface;
use Urbania\Authorization\Domain\Repositories\RoleRepositoryInterface;
use Urbania\Authorization\Infrastructure\Repositories\EloquentPermissionRepository;
use Urbania\Authorization\Infrastructure\Repositories\EloquentRoleAssignmentRepository;
use Urbania\Authorization\Infrastructure\Repositories\EloquentRoleRepository;
use Urbania\Shared\JWT\JwtGuard;
use Urbania\Shared\JWT\JwtService;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoleRepositoryInterface::class, EloquentRoleRepository::class);
        $this->app->singleton(PermissionRepositoryInterface::class, EloquentPermissionRepository::class);
        $this->app->singleton(RoleAssignmentRepositoryInterface::class, EloquentRoleAssignmentRepository::class);

        $this->app->singleton(PermissionResolver::class, function (Application $app): PermissionResolver {
            /** @var RoleAssignmentRepositoryInterface $roleAssignmentRepo */
            $roleAssignmentRepo = $app->make(RoleAssignmentRepositoryInterface::class);

            return new PermissionResolver($roleAssignmentRepo);
        });

        $this->app->singleton(CheckPermissionUseCase::class, function (Application $app): CheckPermissionUseCase {
            /** @var PermissionResolver $permissionResolver */
            $permissionResolver = $app->make(PermissionResolver::class);

            return new CheckPermissionUseCase($permissionResolver);
        });
    }

    public function boot(): void
    {
        // Register the JWT guard for the 'api' guard via Auth::extend()
        Auth::extend('jwt', function (Application $app, string $name, array $config): JwtGuard {
            /** @var JwtService $jwtService */
            $jwtService = $app->make(JwtService::class);

            return new JwtGuard($jwtService);
        });
    }
}
