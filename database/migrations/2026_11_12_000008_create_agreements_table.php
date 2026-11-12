<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agreements with customers, suppliers, contractors and employees — kept for the
 * record: the signed file, when it starts and (unless open-ended) when it ends, so
 * a renewal coming up is seen in time. `settings.agreement_warning_days` is how far
 * ahead an ending agreement is flagged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entity_id')->constrained();
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('agreement_warning_days')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('agreement_warning_days');
        });
        Schema::dropIfExists('agreements');
    }
};
