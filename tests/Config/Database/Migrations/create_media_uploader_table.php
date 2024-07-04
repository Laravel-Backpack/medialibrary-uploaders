<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
      /*   Schema::create('uploaders', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->json('repeatable')->nullable();
        }); */
    }

    public function down(): void
    {
        //Schema::dropIfExists('uploaders');
    }
};
