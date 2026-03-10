<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Setear owner_id desde el primer miembro en board_user (más antiguo).
        //    Si hay empate de created_at, elegimos el menor user_id para que sea determinista.
        DB::statement("
            UPDATE boards b
            SET owner_id = sub.user_id
            FROM (
                SELECT DISTINCT ON (bu.board_id)
                    bu.board_id,
                    bu.user_id
                FROM board_user bu
                ORDER BY bu.board_id, bu.created_at ASC, bu.user_id ASC
            ) sub
            WHERE b.id = sub.board_id
              AND b.owner_id IS NULL
        ");

        // 2) Asegurar que el owner también esté en board_user (miembro del tablero)
        DB::statement("
            INSERT INTO board_user (board_id, user_id, is_favorite, created_at, updated_at)
            SELECT b.id, b.owner_id, false, now(), now()
            FROM boards b
            WHERE b.owner_id IS NOT NULL
            ON CONFLICT (board_id, user_id) DO NOTHING
        ");
    }

    public function down(): void
    {
        // Backfill irreversible sin tener el valor anterior.
        // Si querés, acá podríamos setear owner_id a NULL, pero NO es recomendable en prod:
        // DB::statement('UPDATE boards SET owner_id = NULL');
    }
};
