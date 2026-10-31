<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
public function up(): void
{
    Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
    $table->unsignedInteger('dish_id'); // id del plato en el servicio restaurants
    $table->string('dish_name', 100); // "foto" del plato al momento de pedir
    $table->decimal('unit_price', 10, 2);
    $table->unsignedInteger('quantity');
    $table->decimal('subtotal', 10, 2);
    $table->timestamps();
});
}
public function down(): void
{
Schema::dropIfExists('order_items');
}
};