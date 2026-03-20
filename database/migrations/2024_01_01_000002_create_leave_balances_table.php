<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->year('year');
            $table->tinyInteger('month');
            $table->decimal('casual_leave', 4, 1)->default(0);
            $table->decimal('sick_leave', 4, 1)->default(0);
            $table->decimal('annual_leave', 4, 1)->default(0);
            $table->decimal('used_casual', 4, 1)->default(0);
            $table->decimal('used_sick', 4, 1)->default(0);
            $table->decimal('used_annual', 4, 1)->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'year', 'month']);
            $table->index(['user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
