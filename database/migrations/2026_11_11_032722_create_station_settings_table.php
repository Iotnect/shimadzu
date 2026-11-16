<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('station_settings', function (Blueprint $table) {
            $table->id();
            $table->string('sensor', 64);
            $table->string('station', 32);
            $table->date('effective_from');
            $table->decimal('standard_minutes', 12, 4)->nullable();
            $table->decimal('working_minutes', 10, 4);
            $table->text('notes');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name');
            $table->timestamps();
            $table->index(['sensor', 'station', 'effective_from', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('station_settings');
    }
};
