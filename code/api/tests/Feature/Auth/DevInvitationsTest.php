<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Urbania\Auth\Infrastructure\Models\EloquentInvitation;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------
// CASE 9: GET /dev/invitations/last?email=... → 200 (local/testing)
// ---------------------------------------------------------------
test('dev endpoint returns latest valid invitation token for email', function () {
    $org = new EloquentOrganization(['nombre' => 'Test Org']);
    $org->save();

    // Create an older invitation
    $oldInvitation = new EloquentInvitation([
        'organization_id' => $org->id,
        'email' => 'dev@urbania.test',
        'token' => 'old-token-12345',
        'estado' => 'vigente',
        'expira_en' => now()->addDays(7),
    ]);
    $oldInvitation->save();
    $oldInvitation->created_at = now()->subHour();
    $oldInvitation->saveQuietly();

    // Create the latest invitation
    $latestInvitation = new EloquentInvitation([
        'organization_id' => $org->id,
        'email' => 'dev@urbania.test',
        'token' => 'latest-token-67890',
        'estado' => 'vigente',
        'expira_en' => now()->addDays(7),
    ]);
    $latestInvitation->save();

    $response = getJson('/dev/invitations/last?email=dev@urbania.test');

    $response->assertOk()
        ->assertJsonPath('invitation_token', 'latest-token-67890');
});

test('dev endpoint returns 404 when no valid invitation exists', function () {
    $response = getJson('/dev/invitations/last?email=nonexistent@urbania.test');

    $response->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

test('dev endpoint returns 422 when email parameter missing', function () {
    $response = getJson('/dev/invitations/last');

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

// ---------------------------------------------------------------
// CASE 10: GET /dev/invitations/last → 404 in production
// ---------------------------------------------------------------
test('dev routes are not loaded in production via RouteServiceProvider guard', function () {
    // In the current 'testing' environment, dev routes ARE loaded
    // because RouteServiceProvider::boot() loads them conditionally.
    // We verify the route exists now (testing env).
    $routes = Route::getRoutes();
    $devRouteFound = false;

    foreach ($routes as $route) {
        if (str_starts_with($route->uri(), 'dev/')) {
            $devRouteFound = true;
            break;
        }
    }

    expect($devRouteFound)->toBeTrue('Dev routes must be loaded in testing environment');

    // Verify the RouteServiceProvider contains the guard that prevents
    // dev route loading in production. This is the mechanism that ensures
    // a real 404 in production (not a 403 or any other response).
    $providerContent = file_get_contents(app_path('Providers/RouteServiceProvider.php'));

    expect($providerContent)
        ->toContain("app()->environment('local', 'testing')")
        ->toContain('routes/dev.php');

    // In production, the condition app()->environment('local', 'testing')
    // evaluates to false, so routes/dev.php is never loaded.
    // Any request to /dev/* would get a real 404 from Laravel's router
    // (same as any non-existent route), not a 403 authorization error.
    // This is the correct behavior confirmed by API_ARCHITECTURE.md §9.
});
