<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fp_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('fp_sessions')->cascadeOnDelete();
            $table->enum('type', ['task', 'header'])->default('task');
            $table->string('title', 500);
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('estimated_pomodoros')->default(1);
            $table->unsignedTinyInteger('completed_pomodoros')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fp_tasks');
    }
};
