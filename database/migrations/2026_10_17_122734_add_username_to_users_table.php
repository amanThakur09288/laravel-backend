<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the username column (skipped if it already exists)
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 50)->nullable()->unique()->after('name');
            });
        }

        // 2. Give every existing user a username, made from the part of the
        //    email before the @ (admin@gmail.com -> admin)
        $used = DB::table('users')->whereNotNull('username')->pluck('username')->all();

        $users = DB::table('users')
            ->whereNull('username')
            ->orderBy('id')
            ->get(['id', 'email']);

        foreach ($users as $user) {
            $base = substr(Str::slug(Str::before($user->email, '@'), ''), 0, 40) ?: 'user';
            $username = $base;
            $counter = 2;

            while (in_array($username, $used, true)) {
                $username = $base . $counter++;
            }

            $used[] = $username;

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};