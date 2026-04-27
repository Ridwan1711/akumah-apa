<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission_name');
            $table->string('scope_key');
            $table->string('scope_value');
            $table->timestamps();

            $table->index(['user_id', 'permission_name']);
            $table->unique(['user_id', 'permission_name', 'scope_key', 'scope_value'], 'permission_scopes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_scopes');
    }
};

