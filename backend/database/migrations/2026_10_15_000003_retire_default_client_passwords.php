<?php

use App\Services\Security\RetireDefaultPasswords;
use Illuminate\Database\Migrations\Migration;

// QA D1: the first pass covered staff only; this covers client accounts (e.g. demo seed clients on client123).
// Idempotent: accounts already locked are no longer on a known password.
return new class extends Migration
{
    public function up(): void
    {
        app(RetireDefaultPasswords::class)->handle();
    }

    public function down(): void
    {
        // Nothing to undo: original passwords are not recoverable by design.
    }
};
