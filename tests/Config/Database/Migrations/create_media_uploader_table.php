<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('media_uploaders', function (Blueprint $table) {
            $table->id();
            $table->json('repeatable')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploaders');
    }
};