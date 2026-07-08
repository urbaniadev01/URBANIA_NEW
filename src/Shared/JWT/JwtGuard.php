<?php

declare(strict_types=1);

namespace Urbania\Shared\JWT;

use App\Models\User;
use Firebase\JWT\ExpiredException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;

final class JwtGuard implements Guard
{
    private ?Authenticatable $user = null;

    public function __construct(
        private readonly JwtService $jwtService,
    ) {}

    /**
     * Attempt to authenticate the request using the Bearer token.
     */
    public function attempt(): bool
    {
        $token = $this->extractBearerToken();

        if ($token === null) {
            return false;
        }

        try {
            $decoded = $this->jwtService->verify($token);
        } catch (ExpiredException) {
            return false;
        } catch (\Throwable) {
            return false;
        }

        if (! isset($decoded->sub) || ! is_string($decoded->sub)) {
            return false;
        }

        if (! isset($decoded->type) || $decoded->type !== 'access') {
            return false;
        }

        /** @var User|null $user */
        $user = User::find($decoded->sub);

        if ($user === null) {
            return false;
        }

        if ($user->estado !== 'active') {
            return false;
        }

        $this->user = $user;

        return true;
    }

    public function check(): bool
    {
        return $this->attempt();
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->user === null) {
            $this->attempt();
        }

        return $this->user;
    }

    public function id(): ?string
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        return (string) $user->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return $this->attempt();
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Extract the Bearer token from the Authorization header.
     */
    private function extractBearerToken(): ?string
    {
        $header = request()->header('Authorization');

        if (! is_string($header) || $header === '') {
            return null;
        }

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
