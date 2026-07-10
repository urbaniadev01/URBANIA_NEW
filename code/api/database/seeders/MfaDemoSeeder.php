<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Mfa\Infrastructure\Models\EloquentUserMfa;

class MfaDemoSeeder extends Seeder
{
    /**
     * Seed a user with MFA pre-enabled for testing.
     *
     * Secret: JBSWY3DPEHPK3PXP (Base32 for "test-mfa-secret")
     * Email: test+mfa@urbania.test
     *
     * The recovery codes are pre-generated with bcrypt hashes of known values.
     * For tests that need to use recovery codes, they should use the codes
     * generated during the enrollment flow, not this seeder.
     */
    public function run(): void
    {
        $org = EloquentOrganization::create([
            'id' => (string) Str::orderedUuid(),
            'nombre' => 'MFA Test Org',
        ]);

        $user = User::create([
            'id' => (string) Str::orderedUuid(),
            'organization_id' => $org->id,
            'email' => 'test+mfa@urbania.test',
            'password_hash' => password_hash('Secret1pass', PASSWORD_BCRYPT),
            'estado' => 'active',
        ]);

        // Pre-hashed recovery codes (the plain values are: TEST0-XXXXX through TEST7-XXXXX)
        $recoveryCodes = [];
        $plainCodes = [
            'RECV0-ERY01',
            'RECV0-ERY02',
            'RECV0-ERY03',
            'RECV0-ERY04',
            'RECV0-ERY05',
            'RECV0-ERY06',
            'RECV0-ERY07',
            'RECV0-ERY08',
        ];

        foreach ($plainCodes as $code) {
            $recoveryCodes[] = [
                'hash' => password_hash($code, PASSWORD_BCRYPT, ['cost' => 12]),
                'used_at' => null,
            ];
        }

        EloquentUserMfa::create([
            'id' => (string) Str::orderedUuid(),
            'user_id' => $user->id,
            'totp_secret' => Crypt::encrypt('JBSWY3DPEHPK3PXP'),
            'recovery_codes' => $recoveryCodes,
            'enabled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('MFA demo user seeded: test+mfa@urbania.test / Secret1pass');
        $this->command->info('TOTP secret: JBSWY3DPEHPK3PXP');
        $this->command->info('Recovery codes: '.implode(', ', $plainCodes));
    }
}
