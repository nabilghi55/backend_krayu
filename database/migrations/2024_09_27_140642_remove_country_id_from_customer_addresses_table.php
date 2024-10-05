<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            // Mengecek apakah foreign key 'country_id' ada sebelum menghapus
            if (DB::getSchemaBuilder()->hasColumn('customer_addresses', 'country_id')) {
                $table->dropForeign(['country_id']);  // Hapus foreign key jika ada
                $table->dropColumn('country_id');     // Hapus kolom country_id
            }
        });
    }
    
    public function down()
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            // Mengembalikan kolom country_id dan menambahkan kembali foreign key
            if (!DB::getSchemaBuilder()->hasColumn('customer_addresses', 'country_id')) {
                $table->foreignId('country_id')->constrained()->onDelete('cascade'); 
            }
        });
    }
    
};
