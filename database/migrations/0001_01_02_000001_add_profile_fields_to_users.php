<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->index()->after('password');
            $table->string('api_token', 80)->nullable()->unique()->index()->after('role');
            $table->string('avatar_url')->nullable()->after('api_token');
            $table->string('timezone')->default('UTC')->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropUnique(['api_token']);
            $table->dropColumn(['role', 'api_token', 'avatar_url', 'timezone']);
        });
    }
};
