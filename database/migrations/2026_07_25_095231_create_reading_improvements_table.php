<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reading_improvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student')->constrained('students')->onDelete('cascade');
            $table->foreignId('course')->constrained('courses')->onDelete('cascade');

            $table->enum('type', [
                'significant_improvement', // تحسن معتبر
                'slight_improvement',      // تحسن بسيط
                'no_improvement',          // عدم تحسن
                'decline'                  // تراجع
            ]);

            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_improvements');
    }
};
