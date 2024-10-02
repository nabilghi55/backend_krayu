<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
    
    public function down()
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });
    }
    
};
