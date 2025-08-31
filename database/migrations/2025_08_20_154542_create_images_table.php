<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('path');
            $table->enum('type', ['real', 'fake']);
            $table->enum('split', ['train', 'test']);
            $table->integer('split_ratio');
            $table->enum('prediction', ['real', 'fake'])->nullable();
            $table->float('confidence', 8, 6)->nullable();
            $table->timestamps();

            $table->index(['split', 'type']);
            $table->index('split_ratio');
        });

        Schema::create('training_results', function (Blueprint $table) {
            $table->id();
            $table->float('accuracy', 8, 6)->default(0);
            $table->float('precision', 8, 6)->default(0);
            $table->float('recall', 8, 6)->default(0);
            $table->float('f1_score', 8, 6)->default(0);
            $table->float('auc_roc', 8, 6)->default(0);
            $table->json('confusion_matrix')->nullable();
            $table->integer('split_ratio')->default(80);
            $table->timestamps();

            $table->index('split_ratio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
        Schema::dropIfExists('training_results');
    }
};