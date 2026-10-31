<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicadores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedSmallInteger('cuadro')->nullable();
            $table->string('fuente', 10)->default('BCCR');
            $table->string('grupo', 20)->index();
            $table->string('nombre', 120);
            $table->string('nombre_corto', 40);
            $table->string('icono', 30)->nullable();
            $table->string('unidad', 10)->default('índice');
            $table->string('frecuencia', 10)->default('mensual');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicador_id')->constrained('indicadores')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('valor', 18, 6);
            $table->unique(['indicador_id', 'fecha']);
        });

        Schema::create('sincronizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('fuente', 10);
            $table->string('detalle', 120);
            $table->unsignedInteger('registros')->default(0);
            $table->boolean('exito')->default(true);
            $table->string('mensaje', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sincronizaciones');
        Schema::dropIfExists('observaciones');
        Schema::dropIfExists('indicadores');
    }
};
