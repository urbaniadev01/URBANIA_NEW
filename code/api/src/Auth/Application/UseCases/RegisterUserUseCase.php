<?php

declare(strict_types=1);

namespace Urbania\Auth\Application\UseCases;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Urbania\Auth\Application\DTOs\RegisterUserRequestDto;
use Urbania\Auth\Domain\Exceptions\EmailAlreadyRegisteredException;
use Urbania\Auth\Domain\Exceptions\InvitationTokenInvalidException;
use Urbania\Auth\Domain\Repositories\ContactRepositoryInterface;
use Urbania\Auth\Domain\Repositories\InvitationRepositoryInterface;
use Urbania\Auth\Domain\Repositories\UserRepositoryInterface;

final readonly class RegisterUserUseCase
{
    public function __construct(
        private InvitationRepositoryInterface $invitationRepository,
        private UserRepositoryInterface $userRepository,
        private ContactRepositoryInterface $contactRepository,
    ) {}

    /**
     * Execute the user registration from an invitation.
     *
     * @return array{user: User, contact_name: string}
     *
     * @throws InvitationTokenInvalidException
     * @throws EmailAlreadyRegisteredException
     */
    public function execute(RegisterUserRequestDto $dto): array
    {
        $invitation = $this->invitationRepository->findValidByToken($dto->invitationToken);

        if ($invitation === null) {
            throw new InvitationTokenInvalidException;
        }

        if ($this->userRepository->existsByEmail($invitation->email)) {
            throw new EmailAlreadyRegisteredException;
        }

        return DB::transaction(function () use ($dto, $invitation) {
            $user = $this->userRepository->create([
                'organization_id' => $invitation->organization_id,
                'email' => $invitation->email,
                'password_hash' => Hash::make($dto->password),
                'estado' => 'active',
            ]);

            $contact = $this->contactRepository->create([
                'organization_id' => $invitation->organization_id,
                'user_id' => $user->id,
                'nombre' => $dto->name,
                'email' => $invitation->email,
                'telefono' => $dto->phone,
            ]);

            $this->invitationRepository->markConsumed($invitation);

            return [
                'user' => $user,
                'contact_name' => $contact->nombre,
            ];
        });
    }
}
