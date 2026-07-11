<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Http\Concerns;

use Illuminate\Http\Request;
use Urbania\Authorization\Application\Services\PermissionResolver;

/**
 * R-COB-02: el usuario necesita el permiso dado con scope `organization` (igual al
 * tenant del condominio) o `condominium` (igual al condominio exacto). Scope
 * `tower`/`unit` nunca basta para datos financieros — a diferencia de
 * PropertyController, que sí concede lectura con scope `tower`.
 *
 * No se usa el middleware `require_permission` genérico porque su
 * CheckPermissionUseCase exige coincidencia exacta de scope_type — no expande un
 * scope `organization` a `condominium` pese a lo que su propio docblock afirma.
 * Mismo motivo por el que PropertyController resuelve su scope a mano. Extraído
 * como trait en COBRANZA-B03 porque ChargeConceptController (COBRANZA-B02) ya
 * repetía esta misma lógica.
 */
trait HasBillingPermission
{
    private function hasBillingPermission(Request $request, string $permission, string $condominiumId): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        /** @var PermissionResolver $resolver */
        $resolver = app(PermissionResolver::class);
        $entries = $resolver->resolve((string) $user->id);

        foreach ($entries as $entry) {
            if ($entry['permission'] !== $permission) {
                continue;
            }

            if ($entry['scope_type'] === 'organization' && $entry['scope_id'] === $user->organization_id) {
                return true;
            }

            if ($entry['scope_type'] === 'condominium' && $entry['scope_id'] === $condominiumId) {
                return true;
            }
        }

        return false;
    }
}
