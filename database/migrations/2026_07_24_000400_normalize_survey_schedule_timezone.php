<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $scheduleTimezone = (string) config('surveys.schedule_timezone', 'Asia/Damascus');

        DB::table('surveys')
            ->select(['id', 'starts_at', 'ends_at'])
            ->where(function ($query): void {
                $query->whereNotNull('starts_at')->orWhereNotNull('ends_at');
            })
            ->orderBy('id')
            ->get()
            ->each(function ($survey) use ($scheduleTimezone): void {
                $updates = [];

                foreach (['starts_at', 'ends_at'] as $field) {
                    if ($survey->{$field} === null) {
                        continue;
                    }

                    $updates[$field] = CarbonImmutable::parse(
                        (string) $survey->{$field},
                        $scheduleTimezone,
                    )->utc()->format('Y-m-d H:i:s');
                }

                if ($updates !== []) {
                    DB::table('surveys')->where('id', $survey->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        // This one-time data normalization cannot be reversed safely because
        // schedules created after it are already stored correctly in UTC.
    }
};
