<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woo_commerce_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('store_url');
            $table->text('consumer_key');
            $table->text('consumer_secret');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woo_commerce_connections');
    }
};
