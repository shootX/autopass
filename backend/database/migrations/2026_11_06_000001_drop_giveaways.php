<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Giveaways are gone (owner, 2026-11-06): a ticket bought with points, points bought with money, for a prize
     * reads as a lottery, which App Review allows only with a licence. By the owner's choice the bought tickets go
     * with them and the points spent are not returned; their rows in `transactions` stay. down() brings back the
     * empty tables only, not the tickets.
     */
    public function up(): void
    {
        Schema::dropIfExists('user_tickets');
        Schema::dropIfExists('tickets');
    }

    public function down(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->text('description');
            $table->string('photo', 128);
            $table->integer('price');
            $table->date('date_to');
            $table->integer('count')->default(0);
            $table->text('footer_text')->nullable();
            $table->string('footer_geo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tickets ADD CONSTRAINT tickets_price_not_negative CHECK (price >= 0) NOT VALID');
        }
    }
};
