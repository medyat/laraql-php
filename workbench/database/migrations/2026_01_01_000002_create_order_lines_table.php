<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->string('product_code');
            $table->integer('qty');
            $table->integer('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
