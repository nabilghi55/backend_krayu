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
            $table->dropForeign(['country_id']);  // Hapus foreign key constraint dulu jika ada
            $table->dropColumn('country_id');     // Hapus kolom country_id
        });
    }
    
    public function down()
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->foreignId('country_id')->constrained()->onDelete('cascade'); // Untuk rollback jika diperlukan
        });
    }
    
};
