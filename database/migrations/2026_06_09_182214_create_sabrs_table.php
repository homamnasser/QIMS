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
        Schema::create('sabrs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student')->constrained('students')->onDelete('cascade');
            $table->foreignId('giver')->constrained('users')->onDelete('cascade');
            $table->foreignId('course')->constrained('courses')->onDelete('cascade');

            $table->string('value')->nullable();
            $table->string('type');
            $table->date('date');

            $table->json('parts');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sabrs');
    }
};
