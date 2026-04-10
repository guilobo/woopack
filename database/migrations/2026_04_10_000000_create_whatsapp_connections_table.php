<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whats_app_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('business_id')->nullable();
            $table->string('waba_id')->nullable();
            $table->string('phone_number_id')->nullable();

            $table->string('display_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('quality_rating')->nullable();

            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->timestamps();

            $table->unique('user_id');
            $table->index(['waba_id', 'phone_number_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whats_app_connections');
    }
};
