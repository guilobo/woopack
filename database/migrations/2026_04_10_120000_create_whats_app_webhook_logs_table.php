<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whats_app_webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number_id')->nullable()->index();
            $table->string('event_type')->default('unknown')->index();
            $table->string('forward_status')->default('pending')->index();
            $table->string('forward_url')->nullable();
            $table->timestamp('forwarded_at')->nullable();
            $table->text('forward_response_body')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whats_app_webhook_logs');
    }
};
