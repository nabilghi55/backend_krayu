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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('mobile');
            $table->unsignedBigInteger('country');
            $table->string('address');
            $table->string('apartment')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('zip');
            $table->timestamps();
    
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
