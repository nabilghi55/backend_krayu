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
            // Hapus hanya kolom 'country_id', tanpa menghapus foreign key yang tidak ada
            $table->dropColumn('country_id');
        });
    }
    
    public function down()
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            // Kembalikan kolom 'country_id' jika migrasi di-rollback
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
        });
    }
    
    
};
