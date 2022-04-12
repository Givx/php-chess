<?php

namespace Chess;

use Chess\CastleRule;
use Chess\Evaluation\DefenseEvaluation;
use Chess\Evaluation\PressureEvaluation;
use Chess\Evaluation\SpaceEvaluation;
use Chess\Evaluation\SqEvaluation;
use Chess\Exception\BoardException;
use Chess\PGN\Convert;
use Chess\PGN\Move;
use Chess\PGN\Validate;
use Chess\PGN\SAN\Castle;
use Chess\PGN\SAN\Color;
use Chess\PGN\SAN\Piece;
use Chess\Piece\AbstractPiece;
use Chess\Piece\Bishop;
use Chess\Piece\King;
use Chess\Piece\Knight;
use Chess\Piece\Pawn;
use Chess\Piece\Queen;
use Chess\Piece\Rook;
use Chess\Piece\RookType;

/**
 * Board
 *
 * Chess board representation that allows to play a game of chess in Portable
 * Game Notation (PGN) format. This class is the cornerstone that allows to build
 * multiple features on top of it: FEN string generation, ASCII representation,
 * PNG image creation, position evaluation, etc.
 *
 * @author Jordi Bassagañas
 * @license GPL
 */
final class Board extends \SplObjectStorage
{
    use BoardObserverPieceTrait;

    /**
     * Current player's turn.
     *
     * @var string
     */
    private string $turn = '';

    /**
     * Captured pieces.
     *
     * @var array
     */
    private array $captures = [
        Color::W => [],
        Color::B => [],
    ];

    /**
     * History.
     *
     * @var array
     */
    private array $history = [];

    /**
     * Castle status.
     *
     * @var array
     */
    private array $castle;

    /**
     * Observers.
     *
     * @var array
     */
    private array $observers;

    /**
     * Defense evaluation.
     *
     * @var object
     */
    private object $defenseEval;

    /**
     * Pressure evaluation.
     *
     * @var object
     */
    private object $pressureEval;

    /**
     * Space evaluation.
     *
     * @var object
     */
    private object $spaceEval;

    /**
     * Square evaluation.
     *
     * @var object
     */
    private object $sqEval;

    /**
     * Constructor.
     *
     * @param array $pieces
     * @param array $castle
     */
    public function __construct(array $pieces = null, array $castle = null)
    {
        if (empty($pieces)) {
            $this->attach(new Rook(Color::W, 'a1', RookType::O_O_O));
            $this->attach(new Knight(Color::W, 'b1'));
            $this->attach(new Bishop(Color::W, 'c1'));
            $this->attach(new Queen(Color::W, 'd1'));
            $this->attach(new King(Color::W, 'e1'));
            $this->attach(new Bishop(Color::W, 'f1'));
            $this->attach(new Knight(Color::W, 'g1'));
            $this->attach(new Rook(Color::W, 'h1', RookType::O_O));
            $this->attach(new Pawn(Color::W, 'a2'));
            $this->attach(new Pawn(Color::W, 'b2'));
            $this->attach(new Pawn(Color::W, 'c2'));
            $this->attach(new Pawn(Color::W, 'd2'));
            $this->attach(new Pawn(Color::W, 'e2'));
            $this->attach(new Pawn(Color::W, 'f2'));
            $this->attach(new Pawn(Color::W, 'g2'));
            $this->attach(new Pawn(Color::W, 'h2'));
            $this->attach(new Rook(Color::B, 'a8', RookType::O_O_O));
            $this->attach(new Knight(Color::B, 'b8'));
            $this->attach(new Bishop(Color::B, 'c8'));
            $this->attach(new Queen(Color::B, 'd8'));
            $this->attach(new King(Color::B, 'e8'));
            $this->attach(new Bishop(Color::B, 'f8'));
            $this->attach(new Knight(Color::B, 'g8'));
            $this->attach(new Rook(Color::B, 'h8', RookType::O_O));
            $this->attach(new Pawn(Color::B, 'a7'));
            $this->attach(new Pawn(Color::B, 'b7'));
            $this->attach(new Pawn(Color::B, 'c7'));
            $this->attach(new Pawn(Color::B, 'd7'));
            $this->attach(new Pawn(Color::B, 'e7'));
            $this->attach(new Pawn(Color::B, 'f7'));
            $this->attach(new Pawn(Color::B, 'g7'));
            $this->attach(new Pawn(Color::B, 'h7'));
            $this->castle = CastleRule::$initialState;
        } else {
            foreach ($pieces as $piece) {
                $this->attach($piece);
            }
            $this->castle = $castle;
        }

        $this->refresh();
    }

    /**
     * Gets the current turn.
     *
     * @return string
     */
    public function getTurn(): string
    {
        return $this->turn;
    }

    /**
     * Sets the current turn.
     *
     * @param string $color
     * @return \Chess\Board
     */
    public function setTurn(string $color): Board
    {
        $this->turn = Color::validate($color);

        return $this;
    }

    /**
     * Gets the square evaluation.
     *
     * @return object
     */
    public function getSqEval(): object
    {
        return $this->sqEval;
    }

    /**
     * Gets the space evaluation.
     *
     * @return object
     */
    public function getSpaceEval(): object
    {
        return $this->spaceEval;
    }

    /**
     * Gets the defense evaluation.
     *
     * @return object
     */
    public function getDefenseEval(): object
    {
        return $this->defenseEval;
    }

    /**
     * Gets the castle status.
     *
     * @return array
     */
    public function getCastle(): array
    {
        return $this->castle;
    }

    /**
     * Gets the pieces captured by both players as an array of stdClass objects.
     *
     * @return mixed array|null
     */
    public function getCaptures(): ?array
    {
        return $this->captures;
    }

    /**
     * Adds a new element to the captured pieces.
     *
     * @param string $color
     * @param object $capture
     * @return \Chess\Board
     */
    private function pushCapture(string $color, object $capture): Board
    {
        $this->captures[$color][] = $capture;

        return $this;
    }

    /**
     * Removes an element from the captured pieces.
     *
     * @param string $color
     * @return \Chess\Board
     */
    private function popCapture(string $color): Board
    {
        array_pop($this->captures[$color]);

        return $this;
    }

    /**
     * Gets the history.
     *
     * @return mixed array|null
     */
    public function getHistory(): ?array
    {
        return $this->history;
    }

    /**
     * Gets the last history entry.
     *
     * @return mixed object|null
     */
    public function getLastHistory(): ?object
    {
        if (!empty($this->history)) {
            return end($this->history);
        }

        return null;
    }

    /**
     * Gets the movetext.
     *
     * @return string
     */
    public function getMovetext(): string
    {
        $i = 1;
        $movetext = '';
        foreach ($this->history as $key => $val) {
            if ($key % 2 === 0) {
                $movetext .= $i . ".{$val->move->pgn}";
                $i++;
            } else {
                $movetext .= " {$val->move->pgn} ";
            }
        }

        return trim($movetext);
    }

    /**
     * Adds a new element to the history.
     *
     * @param \Chess\Piece\AbstractPiece $piece
     * @return \Chess\Board
     */
    private function pushHistory(AbstractPiece $piece): Board
    {
        $this->history[] = (object) [
            'sq' => $piece->getSquare(),
            'move' => $piece->getMove(),
        ];

        return $this;
    }

    /**
     * Removes an element from the history.
     *
     * @return \Chess\Board
     */
    private function popHistory(): Board
    {
        array_pop($this->history);

        return $this;
    }

    /**
     * Gets the first piece on the board matching the search criteria.
     *
     * @param string $color
     * @param string $id
     * @return mixed \Chess\Piece\AbstractPiece|null
     */
    public function getPiece(string $color, string $id): ?AbstractPiece
    {
        $this->rewind();
        while ($this->valid()) {
            $piece = $this->current();
            if ($piece->getColor() === $color && $piece->getId() === $id) {
                return $piece;
            }
            $this->next();
        }

        return null;
    }

    /**
     * Gets the pieces by color.
     *
     * @param string $color
     * @return array
     */
    public function getPiecesByColor(string $color): array
    {
        $pieces = [];
        $this->rewind();
        while ($this->valid()) {
            $piece = $this->current();
            $piece->getColor() !== $color ?: $pieces[] = $piece;
            $this->next();
        }

        return $pieces;
    }

    /**
     * Gets all pieces.
     *
     * @return array
     */
    public function getPieces(): array
    {
        $pieces = [];
        $this->rewind();
        while ($this->valid()) {
            $pieces[] = $this->current();
            $this->next();
        }

        return $pieces;
    }

    /**
     * Gets a piece by its position on the board.
     *
     * @param string $sq
     * @return mixed \Chess\Piece\AbstractPiece|null
     */
    public function getPieceBySq(string $sq): ?AbstractPiece
    {
        $this->rewind();
        while ($this->valid()) {
            $piece = $this->current();
            if ($piece->getSquare() === $sq) {
                return $piece;
            }
            $this->next();
        }

        return null;
    }

    /**
     * Picks a piece to be moved.
     *
     * @param object $move
     * @return mixed array|null
     * @throws \Chess\Exception\BoardException
     */
    private function pickPiece(object $move): ?array
    {
        $found = [];
        foreach ($this->getPiecesByColor($move->color) as $piece) {
            if ($piece->getId() === $move->id) {
                if ($piece->getId() === Piece::K) {
                    return [$piece->setMove($move)];
                } elseif (preg_match("/{$move->sq->current}/", $piece->getSquare())) {
                    $found[] = $piece->setMove($move);
                }
            }
        }
        if (empty($found)) {
            throw new BoardException("{$move->color} {$move->id} on {$move->sq->current}.");
        }

        return $found;
    }

    /**
     * Captures a piece.
     *
     * @param \Chess\Piece\AbstractPiece $piece
     * @return \Chess\Board
     */
    private function capture(AbstractPiece $piece): Board
    {
        $piece->getSqs(); // creates the enPassantSquare property if the piece is a pawn
        if ($piece->getId() === Piece::P && !empty($piece->getEnPassantSq()) &&
            empty($this->getPieceBySq($piece->getMove()->sq->next))
           ) {
            if ($captured = $this->getPieceBySq($piece->getEnPassantSq())) {
                $capturedData = (object) [
                    'id' => $captured->getId(),
                    'sq' => $piece->getEnPassantSq(),
                ];
            }
        } else {
            if ($captured = $this->getPieceBySq($piece->getMove()->sq->next)) {
                $capturedData = (object) [
                    'id' => $captured->getId(),
                    'sq' => $captured->getSquare(),
                ];
            }
        }
        if ($captured) {
            $capturingData = (object) [
                'id' => $piece->getId(),
                'sq' => $piece->getSquare(),
            ];
            $piece->getId() !== Piece::R ?: $capturingData->type = $piece->getType();
            $captured->getId() !== Piece::R ?: $capturedData->type = $captured->getType();
            $capture = (object) [
                'capturing' => $capturingData,
                'captured' => $capturedData,
            ];
            $this->pushCapture($piece->getColor(), $capture);
            $this->detach($captured);
        }

        return $this;
    }

    /**
     * Promotes a pawn.
     *
     * @param \Chess\Piece\Pawn $pawn
     * @return \Chess\Board
     */
    private function promote(Pawn $pawn): Board
    {
        $this->detach($this->getPieceBySq($pawn->getMove()->sq->next));
        switch ($pawn->getMove()->newId) {
            case Piece::N:
                $this->attach(new Knight($pawn->getColor(), $pawn->getMove()->sq->next));
                break;
            case Piece::B:
                $this->attach(new Bishop($pawn->getColor(), $pawn->getMove()->sq->next));
                break;
            case Piece::R:
                $this->attach(new Rook($pawn->getColor(), $pawn->getMove()->sq->next, RookType::PROMOTED));
                break;
            default:
                $this->attach(new Queen($pawn->getColor(), $pawn->getMove()->sq->next));
                break;
        }

        return $this;
    }

    /**
     * Checks out if a chess move is valid.
     *
     * @param object $move
     * @return bool true if the move is valid; otherwise false
     */
    private function isValidMove(object $move): bool
    {
        if ($move->color !== $this->turn) {
            return false;
        } elseif (
            $move->isCapture &&
            empty($this->getPieceBySq($move->sq->next)) &&
            $move->id !== Piece::P
        ) {
            return false;
        } elseif (!$move->isCapture && !empty($this->getPieceBySq($move->sq->next))) {
            return false;
        }

        return true;
    }

    /**
     * Checks out if a chess move is legal.
     *
     * @param object $move
     * @return bool true if the move is legal; otherwise false
     */
    private function isLegalMove(object $move): bool
    {
        $isLegalMove = false;
        $pieces = $this->pickPiece($move);
        if (count($pieces) > 1) {
            foreach ($pieces as $piece) {
                if ($piece->isMovable() && !$this->leavesInCheck($piece)) {
                    return $this->move($piece);
                }
            }
        } elseif (current($pieces)->isMovable() && !$this->leavesInCheck(current($pieces))) {
            $piece = current($pieces);
            switch ($piece->getMove()->type) {
                case Move::O_O:
                    CastleRule::short($this->turn, $this->castle, $this->spaceEval)
                        ? $isLegalMove = $this->castle($piece)
                        : $isLegalMove = false;
                    break;
                case Move::O_O_O:
                    CastleRule::long($this->turn, $this->castle, $this->spaceEval)
                        ? $isLegalMove = $this->castle($piece)
                        : $isLegalMove = false;
                    break;
                default:
                    $isLegalMove = $this->move($piece);
                    break;
            }
        }

        return $isLegalMove;
    }

    /**
     * Makes a move.
     *
     * @param string $color
     * @param string $pgn
     * @return bool true if the move can be made; otherwise false
     */
    public function play(string $color, string $pgn): bool
    {
        $object = Convert::toObj($color, $pgn);

        return $this->isValidMove($object) && $this->isLegalMove($object);
    }

    /**
     * Castles the king.
     *
     * @param \Chess\Piece\King $king
     * @return bool true if the castle move can be made; otherwise false
     */
    private function castle(King $king): bool
    {
        $rook = $king->getCastleRook(iterator_to_array($this, false));
        if (!empty($rook)) {
            $this->detach($this->getPieceBySq($king->getSquare()));
            $this->attach(
                new King(
                    $king->getColor(),
                    CastleRule::color($king->getColor())[Piece::K][rtrim($king->getMove()->pgn, '+')]['sq']['next']
                )
             );
            $this->detach($rook);
            $this->attach(
                new Rook(
                    $rook->getColor(),
                    CastleRule::color($king->getColor())[Piece::R][rtrim($king->getMove()->pgn, '+')]['sq']['next'],
                    $rook->getType()
                )
            );
            $this->castle[$this->turn] = [
                CastleRule::IS_CASTLED => true,
                Castle::O_O => false,
                Castle::O_O_O => false,
            ];
            $this->pushHistory($king)->refresh();
            return true;
        }

        return false;
    }

    /**
     * Undoes a castle move.
     *
     * @param array $prevCastle
     * @return \Chess\Board
     */
    private function undoCastle(array $prevCastle): Board
    {
        $prev = end($this->history);
        $king = $this->getPieceBySq($prev->move->sq->next);
        $kingUndone = new King($prev->move->color, $prev->sq);
        $this->detach($king);
        $this->attach($kingUndone);
        if (Move::O_O === $prev->move->type) {
            $rook = $this->getPieceBySq(
                CastleRule::color($prev->move->color)[Piece::R][Castle::O_O]['sq']['next']
            );
            $rookUndone = new Rook(
                $prev->move->color,
                CastleRule::color($prev->move->color)[Piece::R][Castle::O_O]['sq']['current'],
                $rook->getType()
            );
            $this->detach($rook);
            $this->attach($rookUndone);
        } elseif (Move::O_O_O === $prev->move->type) {
            $rook = $this->getPieceBySq(
                CastleRule::color($prev->move->color)[Piece::R][Castle::O_O_O]['sq']['next']
            );
            $rookUndone = new Rook(
                $prev->move->color,
                CastleRule::color($prev->move->color)[Piece::R][Castle::O_O_O]['sq']['current'],
                $rook->getType()
            );
            $this->detach($rook);
            $this->attach($rookUndone);
        }
        $this->castle = $prevCastle;
        $this->popHistory()->refresh();

        return $this;
    }

    /**
     * Updates the castle property.
     *
     * @param \Chess\Piece\AbstractPiece $pieceMoved
     * @return \Chess\Board
     */
    private function updateCastle(AbstractPiece $pieceMoved): Board
    {
        if (!$this->castle[$this->turn][CastleRule::IS_CASTLED]) {
            if ($pieceMoved->getId() === Piece::K) {
                $this->castle[$this->turn] = [
                    CastleRule::IS_CASTLED => false,
                    Castle::O_O => false,
                    Castle::O_O_O => false,
                ];
            } elseif ($pieceMoved->getId() === Piece::R) {
                if ($pieceMoved->getType() === RookType::O_O) {
                    $this->castle[$this->turn][Castle::O_O] = false;
                } elseif ($pieceMoved->getType() === RookType::O_O_O) {
                    $this->castle[$this->turn][Castle::O_O_O] = false;
                }
            }
        }
        $oppColor = Color::opp($this->turn);
        if (!$this->castle[$oppColor][CastleRule::IS_CASTLED]) {
            if ($pieceMoved->getMove()->isCapture) {
                if ($pieceMoved->getMove()->sq->next ===
                    CastleRule::color($oppColor)[Piece::R][Castle::O_O]['sq']['current']
                ) {
                    $this->castle[$oppColor][Castle::O_O] = false;
                } elseif (
                    $pieceMoved->getMove()->sq->next ===
                    CastleRule::color($oppColor)[Piece::R][Castle::O_O_O]['sq']['current']
                ) {
                    $this->castle[$oppColor][Castle::O_O_O] = false;
                }
            }
        }

        return $this;
    }

    /**
     * Moves a piece.
     *
     * @param \Chess\Piece\AbstractPiece $piece
     * @return bool true if the move can be made; otherwise false
     */
    private function move(AbstractPiece $piece): bool
    {
        if ($piece->getMove()->isCapture) {
            $this->capture($piece);
        }
        $this->detach($this->getPieceBySq($piece->getSquare()));
        $pieceClass = new \ReflectionClass(get_class($piece));
        $this->attach(
            $pieceClass->newInstanceArgs([
                $piece->getColor(),
                $piece->getMove()->sq->next,
                $piece->getId() !== Piece::R ?: $piece->getType(),
            ])
        );
        if ($piece->getId() === Piece::P) {
            if ($piece->isPromoted()) {
                $this->promote($piece);
            }
        }
        $this->updateCastle($piece)->pushHistory($piece)->refresh();

        return true;
    }

    /**
     * Undoes the last move.
     *
     * @param array $prevCastle
     * @return \Chess\Board
     */
    public function undoMove(array $prevCastle): Board
    {
        $prev = end($this->history);
        if ($prev) {
            $piece = $this->getPieceBySq($prev->move->sq->next);
            $this->detach($piece);
            if ($prev->move->type === Move::PAWN_PROMOTES ||
                $prev->move->type === Move::PAWN_CAPTURES_AND_PROMOTES) {
                $pieceUndone = new Pawn($prev->move->color, $prev->sq);
            } else {
                $pieceUndoneClass = new \ReflectionClass(get_class($piece));
                $pieceUndone = $pieceUndoneClass->newInstanceArgs([
                    $prev->move->color,
                    $prev->sq,
                    $piece->getId() !== Piece::R ?: $piece->getType(),
                ]);
            }
            $this->attach($pieceUndone);
            if ($prev->move->isCapture && $capture = end($this->captures[$prev->move->color])) {
                $capturedClass = new \ReflectionClass(Convert::toClassName($capture->captured->id));
                $this->attach($capturedClass->newInstanceArgs([
                    $prev->move->color === Color::W ? Color::B : Color::W,
                    $capture->captured->sq,
                    $capture->captured->id !== Piece::R ?: $capture->captured->type,
                  ])
                );
                $this->popCapture($prev->move->color);
            }
            !isset($prevCastle) ?: $this->castle = $prevCastle;
            $this->popHistory()->refresh();
        }

        return $this;
    }

    /**
     * Refreshes the board's status.
     */
    public function refresh(): void
    {
        $this->turn = Color::opp($this->turn);

        $this->sqEval = (object) [
            SqEvaluation::TYPE_FREE => (new SqEvaluation($this))->eval(SqEvaluation::TYPE_FREE),
            SqEvaluation::TYPE_USED => (object) (new SqEvaluation($this))->eval(SqEvaluation::TYPE_USED),
        ];

        $this->detachPieces()
            ->attachPieces()
            ->notifyPieces();

        $this->spaceEval = (object) (new SpaceEvaluation($this))->eval();
        $this->pressureEval = (object) (new PressureEvaluation($this))->eval();
        $this->defenseEval = (object) (new DefenseEvaluation($this))->eval();

        $this->notifyPieces();
    }

    /**
     * Checks out if the board is in check when a piece is moved.
     *
     * @param \Chess\Piece\AbstractPiece $piece
     * @return bool
     */
    private function leavesInCheck(AbstractPiece $piece): bool
    {
        $prevCastle = $this->castle;
        if ($piece->getMove()->type === Move::O_O ||
            $piece->getMove()->type === Move::O_O_O) {
            $this->castle($piece);
            $king = $this->getPiece($piece->getColor(), Piece::K);
            $leavesInCheck = in_array($king->getSquare(), $this->pressureEval->{$king->getOppColor()});
            $this->undoCastle($prevCastle);
        } else {
            $this->move($piece);
            $king = $this->getPiece($piece->getColor(), Piece::K);
            $leavesInCheck = in_array($king->getSquare(), $this->pressureEval->{$king->getOppColor()});
            $this->undoMove($prevCastle);
        }

        return $leavesInCheck;
    }

    /**
     * Checks out whether the current player is trapped.
     *
     * @return bool
     */
    private function isTrapped(): bool
    {
        $escape = 0;
        foreach ($this->getPiecesByColor($this->turn) as $piece) {
            foreach ($piece->getSqs() as $sq) {
                switch ($piece->getId()) {
                    case Piece::K:
                        if (in_array($sq, $this->sqEval->used->{$piece->getOppColor()})) {
                            $escape += (int) !$this->leavesInCheck(
                                $piece->setMove(Convert::toObj($this->turn, Piece::K."x$sq"))
                            );
                        } elseif (!in_array($sq, $this->spaceEval->{$piece->getOppColor()})) {
                            $escape += (int) !$this->leavesInCheck(
                                $piece->setMove(Convert::toObj($this->turn, Piece::K.$sq))
                            );
                        }
                        break;
                    case Piece::P:
                        if (in_array($sq, $this->sqEval->used->{$piece->getOppColor()})) {
                            $escape += (int) !$this->leavesInCheck(
                                $piece->setMove(Convert::toObj($this->turn, $piece->getFile()."x$sq"))
                            );
                        } else {
                            $escape += (int) !$this->leavesInCheck(
                                $piece->setMove(Convert::toObj($this->turn, $sq))
                            );
                        }
                        break;
                    default:
                        if (in_array($sq, $this->sqEval->used->{$piece->getOppColor()})) {
                            $escape += (int) !$this->leavesInCheck(
                                $piece->setMove(Convert::toObj($this->turn, $piece->getId()."x$sq"))
                            );
                        } else {
                            $escape += (int) !$this->leavesInCheck(
                                $piece->setMove(Convert::toObj($this->turn, $piece->getId().$sq))
                            );
                        }
                        break;
                }
            }
        }

        return $escape === 0;
    }

    /**
     * Checks out whether the current player is in check.
     *
     * @return bool
     */
    public function isCheck(): bool
    {
        $king = $this->getPiece($this->turn, Piece::K);

        return in_array(
            $king->getSquare(),
            $this->pressureEval->{$king->getOppColor()}
        );
    }

    /**
     * Checks out whether the current player is checkmated.
     *
     * @return bool
     */
    public function isMate(): bool
    {
        return $this->isTrapped() && $this->isCheck();
    }

    /**
     * Checks out whether the current player is stalemated.
     *
     * @return bool
     */
    public function isStalemate(): bool
    {
        return $this->isTrapped() && !$this->isCheck();
    }

    /**
     * Returns all possible moves.
     *
     * @return array
     */
    public function possibleMoves(): ?array
    {
        $moves = [];
        $color = $this->getTurn();
        foreach ($this->getPiecesByColor($color) as $piece) {
            foreach ($piece->getSqs() as $sq) {
                $clone = unserialize(serialize($this));
                switch ($piece->getId()) {
                    case Piece::K:
                        if (
                            CastleRule::color($color)[Piece::K][Castle::O_O]['sq']['next'] === $sq &&
                            $clone->play($color, Castle::O_O)
                        ) {
                            $moves[] = Castle::O_O;
                        } elseif (
                            CastleRule::color($color)[Piece::K][Castle::O_O_O]['sq']['next'] === $sq &&
                            $clone->play($color, Castle::O_O_O)
                        ) {
                            $moves[] = Castle::O_O_O;
                        } elseif ($clone->play($color, Piece::K.$sq)) {
                            $moves[] = Piece::K.$sq;
                        } elseif ($clone->play($color, Piece::K.'x'.$sq)) {
                            $moves[] = Piece::K.'x'.$sq;
                        }
                        break;
                    case Piece::P:
                        if ($clone->play($color, $sq)) {
                            $moves[] = $sq;
                        } elseif ($clone->play($color, $piece->getFile()."x$sq")) {
                            $moves[] = $piece->getFile()."x$sq";
                        }
                        break;
                    default:
                        if ($clone->play($color, $piece->getId().$sq)) {
                            $moves[] = $piece->getId().$sq;
                        } elseif ($clone->play($color, "{$piece->getId()}x$sq")) {
                            $moves[] = "{$piece->getId()}x$sq";
                        }
                        break;
                }
            }
        }

        return $moves;
    }
}
