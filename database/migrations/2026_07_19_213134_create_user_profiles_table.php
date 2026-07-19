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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->date('date_of_birth');
            $table->string('gender');
            $table->string('ethnicity_or_religion');
            $table->string('nic_number')->unique();
            $table->string('driving_license_number')->nullable();
            $table->string('passport_number')->nullable();
            $table->text('current_address');
            $table->text('permanent_address');
            $table->string('birth_certificate_path');
            $table->string('nic_front_path');
            $table->string('nic_back_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
