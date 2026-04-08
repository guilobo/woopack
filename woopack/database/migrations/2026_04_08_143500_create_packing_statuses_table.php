<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('woo_order_id')->unique();
            $table->timestamp('packed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_statuses');
    }
};
