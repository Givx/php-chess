<?php

namespace Chess;

use Chess\Board;
use Chess\Exception\MovetextException;
use Chess\PGN\Symbol;

/**
 * Player.
 *
 * Allows to play a chess game in PGN format.
 *
 * @author Jordi Bassagañas
 * @license GPL
 */
class Player
{
    protected $board;

    protected $moves;

    public function __construct(string $movetext, Board $board = null)
    {
        $board ? $this->board = $board : $this->board = new Board();

        $this->moves = $this->extract($this->filter($movetext));
    }

    public function getBoard(): Board
    {
        return $this->board;
    }

    public function getMoves(): array
    {
        return $this->moves;
    }

    public function play(): Player
    {
        foreach ($this->getMoves() as $move) {
            if (!$this->getBoard()->play('w', $move[0])) {
                throw new MovetextException;
            }
            if (isset($move[1])) {
                if (!$this->getBoard()->play('b', $move[1])) {
                    throw new MovetextException;
                }
            }
        }

        return $this;
    }

    protected function extract(string $movetext): array
    {
        $moves = [];
        $pairs = preg_split('/[0-9]+\./', $movetext, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($pairs as $pair) {
            $moves[] = explode(' ', trim($pair));
        }

        return $moves;
    }

    protected function filter(string $movetext): string
    {
        return str_replace(
            [
                Symbol::RESULT_WHITE_WINS,
                Symbol::RESULT_BLACK_WINS,
                Symbol::RESULT_DRAW,
                Symbol::RESULT_UNKNOWN,
            ],
            '',
            $movetext
        );
    }
}
