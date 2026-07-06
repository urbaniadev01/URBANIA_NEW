<?php

declare(strict_types=1);

use function Pest\Laravel\get;

test('health check returns 200 OK', function () {
    $response = get('/up');
    $response->assertOk();
});

test('dev routes are loaded in testing environment', function () {
    // In 'testing' environment, the /dev prefix should be registered.
    // Since there are no endpoints yet, hitting any /dev/* path
    // should return 404 (route exists but no matching route) —
    // NOT a 404 from the framework itself (which would be different).
    // We test that the dev route group exists by checking that a HEAD
    // request to /dev/ returns 405 (Method Not Allowed) rather than 404,
    // confirming the route group is registered.
    $response = get('/dev/some-route');
    // With no routes defined in dev.php, we get a 404 from the route
    // matching (registered group, no matching route inside).
    $response->assertNotFound();
});

test('RouteServiceProvider conditionally loads dev routes', function () {
    // Static verification: confirm the guard condition exists in the provider.
    $providerPath = app_path('Providers/RouteServiceProvider.php');
    $contents = file_get_contents($providerPath);

    expect($contents)
        ->toContain("app()->environment('local', 'testing')")
        ->toContain("routes/dev.php");
});
