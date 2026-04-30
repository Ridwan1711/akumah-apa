<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('subject_aliases');
    }

    public function down(): void
    {
        // Intentionally left empty for dev-only hard remove flow.
    }
};
