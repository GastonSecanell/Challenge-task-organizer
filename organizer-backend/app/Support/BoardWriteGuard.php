<?php

namespace App\Support;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;

final class BoardWriteGuard
{
    public static function abortIfArchived(Board $board): void
    {
        if ($board->archived_at !== null) {
            abort(409, 'Board is archived (read-only).');
        }
    }

    public static function forBoardId(int $boardId): Board
    {
        $board = Board::query()->select(['id', 'archived_at'])->findOrFail($boardId);
        self::abortIfArchived($board);

        return $board;
    }

    public static function forColumn(BoardColumn $column): Board
    {
        $column->loadMissing('board:id,archived_at');
        /** @var Board $board */
        $board = $column->board;
        self::abortIfArchived($board);

        return $board;
    }

    public static function forCard(Card $card): Board
    {
        $card->loadMissing('column.board:id,archived_at');
        /** @var Board $board */
        $board = $card->column->board;
        self::abortIfArchived($board);

        return $board;
    }
}

