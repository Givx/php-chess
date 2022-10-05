<?php

namespace Chess\Variant\Capablanca1008\PGN\AN;

use Chess\Variant\Classical\PGN\AN\Square as ClassicalSquare;

/**
 * Square.
 *
 * @author Jordi Bassagañas
 * @license GPL
 */
class Square extends ClassicalSquare
{
    const REGEX = '[a-j]{1}[1-8]{1}';

    const SIZE = [
        'files' => 10,
        'ranks' => 8,
    ];
}
