<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_statuses', function (Blueprint $table) {
            $table->dropUnique('packing_statuses_woo_order_id_unique');
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unique(['user_id', 'woo_order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('packing_statuses', function (Blueprint $table) {
            $table->dropUnique('packing_statuses_user_id_woo_order_id_unique');
            $table->dropConstrainedForeignId('user_id');
            $table->unique('woo_order_id');
        });
    }
};
