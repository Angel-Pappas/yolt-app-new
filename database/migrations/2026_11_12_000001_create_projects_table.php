<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project: an agreed piece of work for a customer. It holds no money of its own —
 * its installments are ordinary income transactions pointing at it (`project_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entity_id')->constrained();
            $table->string('name');
            $table->decimal('agreed_total', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_excluded')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
