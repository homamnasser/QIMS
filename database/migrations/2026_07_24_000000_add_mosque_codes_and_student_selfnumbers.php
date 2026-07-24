<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mosques', function (Blueprint $table) {
            $table->string('mosque_code', 12)->nullable()->unique()->after('name');
            $table->unsignedBigInteger('next_student_sequence')->default(0)->after('mosque_code');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('mosque_id')
                ->nullable()
                ->after('id')
                ->constrained('mosques')
                ->restrictOnDelete();
            $table->string('selfnumber', 40)->nullable()->unique()->after('mosque_id');
        });

        $seenAssignments = [];
        DB::table('student_circles')->orderBy('id')->get()->each(function ($assignment) use (&$seenAssignments): void {
            $key = $assignment->student.':'.$assignment->circle;
            if (isset($seenAssignments[$key])) {
                DB::table('student_circles')->where('id', $assignment->id)->delete();

                return;
            }
            $seenAssignments[$key] = true;
        });

        Schema::table('student_circles', function (Blueprint $table) {
            $table->unique(['student', 'circle']);
        });

        $sequenceLength = max(1, (int) config('surveys.selfnumber_sequence_length', 6));

        DB::table('mosques')->orderBy('id')->get()->each(function ($mosque): void {
            DB::table('mosques')
                ->where('id', $mosque->id)
                ->update(['mosque_code' => 'M'.str_pad((string) $mosque->id, 6, '0', STR_PAD_LEFT)]);
        });

        DB::table('mosques')->orderBy('id')->get()->each(function ($mosque) use ($sequenceLength): void {
            $studentIds = DB::table('student_circles')
                ->join('circles', 'circles.id', '=', 'student_circles.circle')
                ->join('courses', 'courses.id', '=', 'circles.course_id')
                ->where('courses.mosque_id', $mosque->id)
                ->orderBy('student_circles.id')
                ->distinct()
                ->pluck('student_circles.student');

            $sequence = 0;
            foreach ($studentIds as $studentId) {
                $alreadyAssigned = DB::table('students')
                    ->where('id', $studentId)
                    ->whereNotNull('selfnumber')
                    ->exists();

                if ($alreadyAssigned) {
                    continue;
                }

                $sequence++;
                DB::table('students')->where('id', $studentId)->update([
                    'mosque_id' => $mosque->id,
                    'selfnumber' => $mosque->mosque_code.'-'.str_pad(
                        (string) $sequence,
                        $sequenceLength,
                        '0',
                        STR_PAD_LEFT,
                    ),
                ]);
            }

            DB::table('mosques')
                ->where('id', $mosque->id)
                ->update(['next_student_sequence' => $sequence]);
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE mosques MODIFY mosque_code VARCHAR(12) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE mosques ALTER COLUMN mosque_code SET NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('student_circles', function (Blueprint $table) {
            $table->dropUnique('student_circles_student_circle_unique');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_selfnumber_unique');
            $table->dropConstrainedForeignId('mosque_id');
            $table->dropColumn('selfnumber');
        });

        Schema::table('mosques', function (Blueprint $table) {
            $table->dropUnique('mosques_mosque_code_unique');
            $table->dropColumn(['mosque_code', 'next_student_sequence']);
        });
    }
};
