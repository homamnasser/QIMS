<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_selfnumber_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('selfnumber', 40)->unique();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('mosque_id')->nullable()->constrained('mosques')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'deactivated_at']);
            $table->index(['mosque_id', 'assigned_at']);
        });

        $now = now();

        DB::table('students')
            ->whereNotNull('selfnumber')
            ->orderBy('id')
            ->get()
            ->each(function ($student) use ($now): void {
                $isActive = DB::table('student_circles')
                    ->where('student', $student->id)
                    ->exists();

                DB::table('student_selfnumber_reservations')->insert([
                    'selfnumber' => $student->selfnumber,
                    'student_id' => $student->id,
                    'mosque_id' => $student->mosque_id,
                    'assigned_at' => $student->created_at ?? $now,
                    'deactivated_at' => $isActive ? null : $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if (! $isActive) {
                    DB::table('students')->where('id', $student->id)->update([
                        'selfnumber' => null,
                        'mosque_id' => null,
                        'updated_at' => $now,
                    ]);
                }
            });

        DB::table('mosques')->orderBy('id')->get()->each(function ($mosque): void {
            $highestReservedSequence = (int) $mosque->next_student_sequence;

            DB::table('student_selfnumber_reservations')
                ->where('mosque_id', $mosque->id)
                ->pluck('selfnumber')
                ->each(function (string $selfnumber) use (&$highestReservedSequence): void {
                    $separatorPosition = strrpos($selfnumber, '-');
                    $sequence = $separatorPosition === false
                        ? null
                        : substr($selfnumber, $separatorPosition + 1);

                    if ($sequence !== null && ctype_digit($sequence)) {
                        $highestReservedSequence = max($highestReservedSequence, (int) $sequence);
                    }
                });

            if ($highestReservedSequence > (int) $mosque->next_student_sequence) {
                DB::table('mosques')
                    ->where('id', $mosque->id)
                    ->update(['next_student_sequence' => $highestReservedSequence]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_selfnumber_reservations');
    }
};
