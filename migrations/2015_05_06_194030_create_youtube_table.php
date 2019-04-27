<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateYoutubeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('youtube', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('email')->unique();
            $table->string('key')->unique();
            $table->string('client_id')->unique();
            $table->string('client_secret')->unique();
            $table->text('access_token');
            $table->text('stat')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('youtube');
    }
}