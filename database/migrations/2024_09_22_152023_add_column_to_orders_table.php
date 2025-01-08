<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->foreignId('affiliate_link_id')->nullable()->constrained();
            $table->decimal('discount_amount', 10);
            $table->decimal('commission_amount', 10);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->dropColumn('affiliate_link_id');
            $table->dropColumn('discount_amount');
            $table->dropColumn('commission_amount');
        });
    }
};
