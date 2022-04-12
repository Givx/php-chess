<?php

namespace Chess\Evaluation;

use Chess\Board;
use Chess\Evaluation\SqEvaluation;
use Chess\PGN\SAN\Color;
use Chess\PGN\SAN\Piece;

/**
 * Pressure evaluation.
 *
 * Squares being threatened at the present moment.
 *
 * @author Jordi Bassagañas
 * @license GPL
 */
class PressureEvaluation extends AbstractEvaluation
{
    const NAME = 'pressure';

    /**
     * Square evaluation containing the free and used squares.
     *
     * @var array
     */
    private array $sqEval;

    /**
     * @param \Chess\Board $board
     */
    public function __construct(Board $board)
    {
        parent::__construct($board);

        $sqEval = new SqEvaluation($board);

        $this->sqEval = [
            SqEvaluation::TYPE_FREE => $sqEval->eval(SqEvaluation::TYPE_FREE),
            SqEvaluation::TYPE_USED => $sqEval->eval(SqEvaluation::TYPE_USED),
        ];
    }

    /**
     * Returns the squares being threatened at the present moment.
     *
     * @return array
     */
    public function eval(): array
    {
        foreach ($this->board->getPieces() as $piece) {
            switch ($piece->getId()) {
                case Piece::K:
                    $this->result[$piece->getColor()] = [
                        ...$this->result[$piece->getColor()],
                        ...array_values(
                            array_intersect(
                                array_values((array) $piece->getTravel()),
                                $this->sqEval[SqEvaluation::TYPE_USED][$piece->getOppColor()]
                            )
                        )
                    ];
                    break;
                case Piece::P:
                    $this->result[$piece->getColor()] = [
                        ...$this->result[$piece->getColor()],
                        ...array_intersect(
                            $piece->getCaptureSquares(),
                            $this->sqEval[SqEvaluation::TYPE_USED][$piece->getOppColor()]
                        )
                    ];
                    break;
                default:
                    $this->result[$piece->getColor()] = [
                        ...$this->result[$piece->getColor()],
                        ...array_intersect(
                            $piece->getSqs(),
                            $this->sqEval[SqEvaluation::TYPE_USED][$piece->getOppColor()]
                        )
                    ];
                    break;
            }
        }

        sort($this->result[Color::W]);
        sort($this->result[Color::B]);

        return $this->result;
    }
}
