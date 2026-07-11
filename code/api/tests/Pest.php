<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Urbania\Shared\JWT\JwtService;

uses(TestCase::class)->in('Feature', 'Integration', 'Security', 'Unit');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * Helper: get the test JWT private key (in-memory, no file I/O).
 */
function testJwtPrivateKey(): string
{
    $pair = JwtService::generateTestKeyPair();

    return $pair['private'];
}

/**
 * Helper: get the test JWT public key (in-memory, no file I/O).
 */
function testJwtPublicKey(): string
{
    $pair = JwtService::generateTestKeyPair();

    return $pair['public'];
}

/**
 * Switch the default DB connection to a database dedicated to a given
 * "raw migration" test suite (tests that call `migrate:fresh`/`migrate:rollback`
 * directly instead of using RefreshDatabase). Those suites don't get the automatic
 * per-parallel-worker database that RefreshDatabase provides, so two such suites
 * running in different parallel processes would otherwise race on the same physical
 * database. Mirrors the naming scheme Laravel's own TestDatabases trait uses
 * (`{database}_test_{token}`), scoped further by $suite so multiple raw-migration
 * suites never collide with each other either.
 *
 * Re-applies the switch on every call (no "already switched" caching) because other
 * test files sharing this same OS process (ParaTest reuses processes across files)
 * may use RefreshDatabase in between, which repoints the shared connection's config
 * to its own database via the same mechanism — a one-time switch would go stale.
 */
function useIsolatedMigrationTestDatabase(string $suite): void
{
    $connection = config('database.default');
    $originalDatabase = config("database.connections.{$connection}.database");

    // If we're already pointed at a `_mig_` database from a previous call in this
    // process, use that as the base name so it doesn't get suffixed again.
    if (preg_match('/^(.*)_mig_[a-z0-9_]+$/', (string) $originalDatabase, $matches) === 1) {
        $originalDatabase = $matches[1];
    }

    $token = ParallelTesting::token() ?: '0';
    $testDatabase = "{$originalDatabase}_mig_{$suite}_{$token}";

    DB::purge($connection);
    config()->set("database.connections.{$connection}.database", $testDatabase);
    DB::reconnect($connection);

    try {
        Schema::hasTable('dummy');
    } catch (Throwable) {
        DB::purge($connection);
        config()->set("database.connections.{$connection}.database", $originalDatabase);
        DB::reconnect($connection);

        Schema::dropDatabaseIfExists($testDatabase);
        Schema::createDatabase($testDatabase);

        config()->set("database.connections.{$connection}.database", $testDatabase);
        DB::purge($connection);
        DB::reconnect($connection);
    }
}
