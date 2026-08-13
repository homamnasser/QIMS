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
        Schema::create('memorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student')->constrained('students')->onDelete('cascade');
            $table->foreignId('giver')->constrained('users')->onDelete('cascade');

            // بيانات التسميع (رقم الصفحة)
            $table->integer('page_number');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->unique(['student', 'page_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memorizations');
    }
};
