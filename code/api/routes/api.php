<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Urbania\Auth\Infrastructure\Http\Controllers\AuthController;
use Urbania\Auth\Infrastructure\Http\Controllers\PasswordResetController;
use Urbania\Authorization\Infrastructure\Http\Controllers\AdminController;
use Urbania\Billing\Infrastructure\Http\Controllers\BillingPeriodController;
use Urbania\Billing\Infrastructure\Http\Controllers\BillingRunController;
use Urbania\Billing\Infrastructure\Http\Controllers\BillingSummaryController;
use Urbania\Billing\Infrastructure\Http\Controllers\ChargeConceptController;
use Urbania\Directorio\Infrastructure\Http\Controllers\ContactController;
use Urbania\Directorio\Infrastructure\Http\Controllers\MeContactController;
use Urbania\Directorio\Infrastructure\Http\Controllers\OccupantTypeController;
use Urbania\Directorio\Infrastructure\Http\Controllers\PropertyOccupantController;
use Urbania\Mfa\Infrastructure\Http\Controllers\MfaController;
use Urbania\Properties\Infrastructure\Http\Controllers\CondominiumController;
use Urbania\Properties\Infrastructure\Http\Controllers\CondominiumTreeController;
use Urbania\Properties\Infrastructure\Http\Controllers\PropertyCoefficientController;
use Urbania\Properties\Infrastructure\Http\Controllers\PropertyController;
use Urbania\Properties\Infrastructure\Http\Controllers\PropertyStatusController;
use Urbania\Properties\Infrastructure\Http\Controllers\PropertyTypeController;
use Urbania\Properties\Infrastructure\Http\Controllers\TowerController;

// API v1 routes — endpoints for each bounded context are registered here
// as the corresponding feature blocks are implemented (AUTH-B01 onwards).

Route::prefix('v1')->group(function () {
    // Auth bounded context
    Route::prefix('auth')->group(function () {
        // AUTH-B01: Register by invitation
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:10,1');

        // AUTH-B02: Login
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        // AUTH-B03: Refresh token
        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->middleware('throttle:10,1');

        // AUTH-B04: Logout
        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware('throttle:10,1');

        // AUTH-B08: MFA enrollment
        Route::post('/mfa/enroll', [MfaController::class, 'enroll'])
            ->middleware(['auth:api', 'throttle:3,60']);

        // AUTH-B08: MFA enrollment confirmation
        Route::post('/mfa/confirm', [MfaController::class, 'confirm'])
            ->middleware(['auth:api', 'throttle:10,1']);

        // AUTH-B08: MFA verification during login (mfa_token)
        Route::post('/mfa/verify', [MfaController::class, 'verify'])
            ->middleware('throttle:5,1');

        // AUTH-B08: MFA disable
        Route::post('/mfa/disable', [MfaController::class, 'disable'])
            ->middleware(['auth:api', 'throttle:5,1']);

        // AUTH-B08: MFA recovery codes regeneration
        Route::post('/mfa/recovery', [MfaController::class, 'recovery'])
            ->middleware(['auth:api', 'throttle:5,1']);

        // AUTH-B09: Forgot password
        Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
            ->middleware('throttle:10,1');

        // AUTH-B09: Reset password
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
            ->middleware('throttle:10,1');

        // AUTH-B15: Current user info
        Route::get('/me', [AuthController::class, 'me'])
            ->middleware(['auth:api', 'throttle:30,1']);
    });

    // Authorization bounded context — AUTH-B05
    // Example protected endpoint: admin dashboard, scoped to an organization
    Route::get('/organizations/{organization}/admin', [AdminController::class, 'index'])
        ->middleware(['auth:api', 'require_permission:admin.access,organization']);

    // Properties bounded context — PROPIEDADES-B02
    Route::prefix('property-types')->middleware('auth:api')->group(function () {
        Route::get('/', [PropertyTypeController::class, 'index']);
        Route::post('/', [PropertyTypeController::class, 'store']);
        Route::get('/{property_type}', [PropertyTypeController::class, 'show']);
        Route::patch('/{property_type}', [PropertyTypeController::class, 'update']);
        Route::delete('/{property_type}', [PropertyTypeController::class, 'destroy']);
    });

    Route::prefix('property-statuses')->middleware('auth:api')->group(function () {
        Route::get('/', [PropertyStatusController::class, 'index']);
        Route::post('/', [PropertyStatusController::class, 'store']);
        Route::get('/{property_status}', [PropertyStatusController::class, 'show']);
        Route::patch('/{property_status}', [PropertyStatusController::class, 'update']);
        Route::delete('/{property_status}', [PropertyStatusController::class, 'destroy']);
    });

    // Properties bounded context — PROPIEDADES-B03
    // Condominiums (top-level resource)
    Route::prefix('condominiums')->middleware('auth:api')->group(function () {
        Route::get('/', [CondominiumController::class, 'index']);
        Route::post('/', [CondominiumController::class, 'store']);
        Route::get('/{condominium}', [CondominiumController::class, 'show']);
        Route::patch('/{condominium}', [CondominiumController::class, 'update']);
        Route::delete('/{condominium}', [CondominiumController::class, 'destroy']);

        // Towers nested under a condominium (R-01 hierarchy)
        Route::get('/{condominium}/towers', [TowerController::class, 'index']);
        Route::post('/{condominium}/towers', [TowerController::class, 'store']);
    });

    // Towers (non-nested endpoints for show/update/destroy)
    Route::prefix('towers')->middleware('auth:api')->group(function () {
        Route::get('/{tower}', [TowerController::class, 'show']);
        Route::patch('/{tower}', [TowerController::class, 'update']);
        Route::delete('/{tower}', [TowerController::class, 'destroy']);
    });

    // Properties bounded context — PROPIEDADES-B04
    // Properties nested under condominiums (index, store)
    Route::prefix('condominiums')->middleware('auth:api')->group(function () {
        Route::get('/{condominium}/properties', [PropertyController::class, 'index']);
        Route::post('/{condominium}/properties', [PropertyController::class, 'store']);
    });

    // Properties (non-nested endpoints for show/update/destroy)
    Route::prefix('properties')->middleware('auth:api')->group(function () {
        Route::get('/{property}', [PropertyController::class, 'show']);
        Route::patch('/{property}', [PropertyController::class, 'update']);
        Route::delete('/{property}', [PropertyController::class, 'destroy']);
    });

    // Properties bounded context — PROPIEDADES-B05
    // Property coefficients (nested under property)
    Route::prefix('properties')->middleware('auth:api')->group(function () {
        Route::get('/{property}/coefficients', [PropertyCoefficientController::class, 'index']);
    });

    // Condominium coefficients (bulk PATCH) + tree
    Route::prefix('condominiums')->middleware('auth:api')->group(function () {
        Route::patch('/{condominium}/coefficients', [PropertyCoefficientController::class, 'patch']);
        Route::get('/{condominium}/tree', [CondominiumTreeController::class, 'tree']);
    });

    // Directorio bounded context — DIRECTORIO-B02
    Route::prefix('occupant-types')->middleware('auth:api')->group(function () {
        Route::get('/', [OccupantTypeController::class, 'index']);
        Route::post('/', [OccupantTypeController::class, 'store']);
        Route::get('/{occupant_type}', [OccupantTypeController::class, 'show']);
        Route::patch('/{occupant_type}', [OccupantTypeController::class, 'update']);
        Route::delete('/{occupant_type}', [OccupantTypeController::class, 'destroy']);
    });

    // Directorio bounded context — DIRECTORIO-B03
    Route::prefix('contacts')->middleware('auth:api')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::post('/', [ContactController::class, 'store']);
        Route::get('/{contact}', [ContactController::class, 'show']);
        Route::patch('/{contact}', [ContactController::class, 'update']);
        Route::delete('/{contact}', [ContactController::class, 'destroy']);
        Route::get('/{contact}/properties', [ContactController::class, 'properties']);
    });

    // Self-service — DIRECTORIO-B03 (R-DIR-04)
    Route::prefix('me')->middleware('auth:api')->group(function () {
        Route::get('/contact', [MeContactController::class, 'show']);
        Route::patch('/contact', [MeContactController::class, 'update']);
    });

    // Directorio bounded context — DIRECTORIO-B04
    Route::prefix('properties')->middleware('auth:api')->group(function () {
        Route::get('/{property}/occupants', [PropertyOccupantController::class, 'index']);
        Route::post('/{property}/occupants', [PropertyOccupantController::class, 'store']);
    });

    Route::prefix('property-occupants')->middleware('auth:api')->group(function () {
        Route::patch('/{property_occupant}', [PropertyOccupantController::class, 'update']);
        Route::delete('/{property_occupant}', [PropertyOccupantController::class, 'destroy']);
    });

    // Billing bounded context — COBRANZA-B02
    // Charge concepts nested under condominiums (index, store)
    Route::prefix('condominiums')->middleware('auth:api')->group(function () {
        Route::get('/{condominium}/charge-concepts', [ChargeConceptController::class, 'index']);
        Route::post('/{condominium}/charge-concepts', [ChargeConceptController::class, 'store']);
    });

    // Charge concepts (non-nested endpoints for show/update/destroy)
    Route::prefix('charge-concepts')->middleware('auth:api')->group(function () {
        Route::get('/{charge_concept}', [ChargeConceptController::class, 'show']);
        Route::patch('/{charge_concept}', [ChargeConceptController::class, 'update']);
        Route::delete('/{charge_concept}', [ChargeConceptController::class, 'destroy']);
    });

    // Billing bounded context — COBRANZA-B03
    // Billing periods + cartera, nested under condominiums.
    // La ruta `/billing-periods/active/summary` va ANTES que las de `/billing-periods/{id}`
    // de abajo por precedencia — `active` no es un UUID, pero declararla primero deja
    // explícito que es un segmento literal, no un parámetro.
    Route::prefix('condominiums')->middleware('auth:api')->group(function () {
        Route::get('/{condominium}/billing-periods/active/summary', [BillingSummaryController::class, 'active']);
        Route::get('/{condominium}/billing-periods', [BillingPeriodController::class, 'index']);
        Route::post('/{condominium}/billing-periods', [BillingPeriodController::class, 'store']);
    });

    // Billing periods (non-nested) + sus corridas de facturación
    Route::prefix('billing-periods')->middleware('auth:api')->group(function () {
        Route::get('/{billing_period}', [BillingPeriodController::class, 'show']);
        Route::patch('/{billing_period}', [BillingPeriodController::class, 'update']);
        Route::get('/{billing_period}/summary', [BillingSummaryController::class, 'show']);
        Route::get('/{billing_period}/billing-runs', [BillingRunController::class, 'index']);

        // R-COB-22: 202 + polling. Throttle defensivo — es la acción de mayor impacto
        // del feature (genera todas las facturas de un periodo).
        Route::post('/{billing_period}/billing-runs', [BillingRunController::class, 'store'])
            ->middleware('throttle:10,1');
    });

    // Billing runs (endpoint de polling, R-COB-22)
    Route::prefix('billing-runs')->middleware('auth:api')->group(function () {
        Route::get('/{billing_run}', [BillingRunController::class, 'show']);
    });
});
