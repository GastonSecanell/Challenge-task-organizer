<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Label;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    public function run(): void
    {
        $board = Board::query()->create([
            'name' => 'Oficina PDI',
        ]);

        $pending = BoardColumn::query()->create([
            'board_id' => $board->id,
            'name' => 'Pendiente',
            'position' => 100,
        ]);

        $inProgress = BoardColumn::query()->create([
            'board_id' => $board->id,
            'name' => 'En Progreso',
            'position' => 200,
        ]);

        $done = BoardColumn::query()->create([
            'board_id' => $board->id,
            'name' => 'Hecho',
            'position' => 300,
        ]);

        $cards = [
            [$pending, 'Revisar tickets nuevos', ''],
            [$pending, 'Preparar reunión semanal', ''],
            [$pending, 'Actualizar documentación interna', ''],
            [$pending, 'Ordenar backlog', ''],
            [$inProgress, 'Implementar endpoint /move', ''],
            [$inProgress, 'Diseñar UI tipo Trello', ''],
            [$inProgress, 'Conectar front con API', ''],
            [$done, 'Crear repo / proyecto base', ''],
            [$done, 'Configurar SQLite en dev', ''],
            [$done, 'Seeder inicial con datos reales', ''],
        ];

        $posByColumn = [
            $pending->id => 100,
            $inProgress->id => 100,
            $done->id => 100,
        ];

        foreach ($cards as [$col, $title, $desc]) {
            $position = $posByColumn[$col->id];
            $posByColumn[$col->id] += 100;

            Card::query()->create([
                'column_id' => $col->id,
                'title' => $title,
                'description' => $desc ?: null,
                'position' => $position,
            ]);
        }

        // Labels por proyecto (tipo Trello)
        $labels = [
            ['Mejora', '#34d399', 100],
            ['Frontend', '#60a5fa', 200],
            ['Backend', '#fb7185', 300],
            ['Base de Datos', '#fbbf24', 400],
            ['Requerimiento', '#a78bfa', 500],
        ];

        foreach ($labels as [$name, $color, $pos]) {
            Label::query()->create([
                'board_id' => $board->id,
                'name' => $name,
                'color' => $color,
                'position' => $pos,
            ]);
        }
    }
}

