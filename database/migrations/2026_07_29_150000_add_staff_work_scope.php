<?php

use App\Enums\StaffWorkScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('work_scope', 20)
                ->default(StaffWorkScope::Institute->value)
                ->after('password')
                ->index();
            $table->foreignId('mosque_id')
                ->nullable()
                ->after('work_scope')
                ->constrained('mosques')
                ->restrictOnDelete();
            $table->index(['work_scope', 'mosque_id']);
        });

        Schema::table('surveys', function (Blueprint $table): void {
            // Null keeps legacy and institute-wide surveys institute-scoped.
            $table->foreignId('mosque_id')
                ->nullable()
                ->after('created_by')
                ->constrained('mosques')
                ->restrictOnDelete();
            $table->index(['mosque_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->dropIndex(['mosque_id', 'status']);
            $table->dropConstrainedForeignId('mosque_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['work_scope', 'mosque_id']);
            $table->dropConstrainedForeignId('mosque_id');
            $table->dropIndex(['work_scope']);
            $table->dropColumn('work_scope');
        });
    }
};
