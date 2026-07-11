<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-COB-22 (COBRANZA-B03) es el primer bloque que despacha un Job encolado
 * (`RunBillingPeriodJob`). El panorama asumía que `jobs`/`job_batches`/`failed_jobs`
 * ya existían desde `API_BOOTSTRAP` — verificado: no existen (`QUEUE_CONNECTION=redis`
 * no necesita la tabla `jobs`, Redis guarda el payload directamente), pero
 * `config('queue.failed.driver')` sigue apuntando a `database-uuids`, que SÍ requiere
 * `failed_jobs` para registrar fallos permanentes. Sin esta tabla, un job que agota
 * sus reintentos fallaría al intentar loguearse a sí mismo. Se crea aquí, estructura
 * estándar de Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
