<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fp_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('fp_sessions')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('color', 7)->default('#3b82f6');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fp_projects');
    }
};
