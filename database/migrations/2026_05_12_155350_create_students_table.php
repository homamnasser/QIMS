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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('username')->unique();
            $table->string('phone_number')->nullable();
            $table->date('birth_date');
            $table->string('academic_class');
            $table->enum('reading_level', ['level_1', 'level_2', 'level_3']);
            $table->string('father_name');
            $table->enum('parent_social_state', ['married', 'divorced', 'widowed']);
            $table->string('father_phone');
            $table->string('password');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
