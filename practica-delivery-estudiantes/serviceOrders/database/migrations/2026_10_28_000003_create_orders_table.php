<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
        $table->id();
        // Referencias a OTROS microservicios: se guardan como datos, no como FK
        $table->string('user_id', 40); // _id del cliente en MongoDB (serviciousers)
        $table->string('user_name', 100); // copia desnormalizada para no consultar users
        $table->unsignedInteger('restaurant_id'); // id en MySQL (servicio restaurants)
        $table->string('restaurant_name', 100);
        $table->string('delivery_address', 200);
        $table->string('notes', 255)->nullable();
        $table->string('status', 20)->default('pendiente');
        $table->decimal('total', 10, 2)->default(0);
        $table->timestamps();
        $table->index(['user_id', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};