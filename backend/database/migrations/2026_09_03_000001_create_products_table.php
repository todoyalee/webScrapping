<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('image_url', 2048)->nullable();
            // Kept so a product can be de-duplicated and traced back to its
            // origin; scrapes upsert on this column. Capped at 512 chars so the
            // unique index stays within the InnoDB key-length limit.
            $table->string('source_url', 512)->nullable();
            $table->timestamps();

            $table->unique('source_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
