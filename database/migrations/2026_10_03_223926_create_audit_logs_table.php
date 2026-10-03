<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who changed which row. Separate from asset_movements on purpose: the ledger is for
 * everyday users and reports, this table is evidence for admins and auditors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Null when the change came from the console (seeder, scheduled job).
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->morphs('auditable');
            $table->string('event', 16);
            // Only the fields that changed: {"cost": {"from": "12000.00", "to": "15000.00"}}
            $table->json('diff')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['actor_id', 'created_at']);
        });

        // Evidence must not be editable, same as the ledger.
        foreach (['update', 'delete'] as $event) {
            DB::statement("CREATE TRIGGER audit_logs_no_{$event} BEFORE ".strtoupper($event).' ON audit_logs
                BEGIN SELECT RAISE(ABORT, \'audit_logs is append-only\'); END');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
