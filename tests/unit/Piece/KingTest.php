<?php

namespace Chess\Tests\Unit\Piece;

use Chess\Piece\King;
use Chess\Tests\AbstractUnitTestCase;

class KingTest extends AbstractUnitTestCase
{
    /**
     * @test
     */
    public function travel_a2()
    {
        $king = new King('w', 'a2');
        $travel = (object) [
            'up' => 'a3',
            'bottom' => 'a1',
            'right' => 'b2',
            'upRight' => 'b3',
            'bottomRight' => 'b1'
        ];
        $this->assertEquals($travel, $king->getTravel());
    }

    /**
     * @test
     */
    public function travel_d5()
    {
        $king = new King('w', 'd5');
        $travel = (object) [
            'up' => 'd6',
            'bottom' => 'd4',
            'left' => 'c5',
            'right' => 'e5',
            'upLeft' => 'c6',
            'upRight' => 'e6',
            'bottomLeft' => 'c4',
            'bottomRight' => 'e4'
        ];
        $this->assertEquals($travel, $king->getTravel());
    }
}
