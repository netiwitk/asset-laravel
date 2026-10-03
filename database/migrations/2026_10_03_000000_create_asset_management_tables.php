<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The rules here live in the database, not only in PHP, so a bug in the app
 * cannot break them. Raw SQL is used where Blueprint has no API:
 * partial indexes, and triggers (SQLite cannot add CHECK constraints later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->index()->constrained('departments');
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained();
            $table->string('role', 16)->default('staff');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('useful_life_years')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 64);
            $table->string('name');
            $table->foreignId('category_id')->constrained();
            $table->foreignId('department_id')->constrained();
            $table->foreignId('custodian_id')->nullable()->constrained('users');
            // Two status axes: "damaged + available" (waiting for repair) needs both.
            $table->string('condition', 24)->default('usable');
            $table->string('availability', 24)->default('available');
            $table->string('serial_no')->nullable();
            $table->date('acquired_on')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->string('location_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial unique: a soft-deleted asset's tag can be reused, and a second import does not crash.
        DB::statement('CREATE UNIQUE INDEX assets_tag_active_unique ON assets (asset_tag) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX assets_department_availability_index ON assets (department_id, availability) WHERE deleted_at IS NULL');

        foreach (['insert', 'update'] as $event) {
            DB::statement("CREATE TRIGGER assets_disposed_must_be_available_{$event} BEFORE ".strtoupper($event)." ON assets
                WHEN NEW.condition = 'disposed' AND NEW.availability <> 'available'
                BEGIN SELECT RAISE(ABORT, 'A disposed asset must be available'); END");
        }

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('borrower_id')->constrained('users');
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->string('status', 24)->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->date('due_on')->nullable();
            $table->date('returned_on')->nullable();
            $table->text('purpose')->nullable();
            $table->timestamps();
            $table->index('borrower_id');
            $table->index('requester_id');
            $table->index('status');
        });

        // One active loan per asset, guaranteed even if the app code has a race.
        DB::statement("CREATE UNIQUE INDEX loans_one_active_per_asset_unique ON loans (asset_id) WHERE status IN ('approved', 'handed_over')");

        Schema::create('repair_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('vendor')->nullable();
            $table->date('sent_on');
            $table->date('expected_return_on')->nullable();
            $table->date('finished_on')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->text('result_note')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'sent_on']);
            $table->index('finished_on');
        });

        // Ledger: every status change writes one row here, in the same transaction as the asset update.
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('type', 32);
            $table->string('from_condition', 24)->nullable();
            $table->string('to_condition', 24)->nullable();
            $table->string('from_availability', 24)->nullable();
            $table->string('to_availability', 24)->nullable();
            $table->foreignId('from_department_id')->nullable()->constrained('departments');
            $table->foreignId('to_department_id')->nullable()->constrained('departments');
            $table->foreignId('loan_id')->nullable()->constrained();
            $table->foreignId('repair_order_id')->nullable()->constrained();
            $table->timestamp('occurred_at');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['asset_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        // Append-only: a wrong entry is fixed by adding an "adjust" row, never by editing history.
        foreach (['update', 'delete'] as $event) {
            DB::statement("CREATE TRIGGER asset_movements_no_{$event} BEFORE ".strtoupper($event)." ON asset_movements
                BEGIN SELECT RAISE(ABORT, 'asset_movements is append-only'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('repair_orders');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('categories');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['role', 'is_active']);
        });
        Schema::dropIfExists('departments');
    }
};
