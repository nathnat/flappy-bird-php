<?php

define('SCREEN_WIDTH', 150);
define('SCREEN_HEIGHT', 30);

require 'utils.php';
require 'Pipe.php';

clearTerminal();
hideCursor();

$bird = [
    'oldY' => 0,
    'x' => 20,
    'y' => round((SCREEN_HEIGHT - 4) / 2),
    'fallSpeed' => 0,
    'update' => function (&$bird) {

        $bird['oldY'] = round($bird['y']);
        $bird['y'] -= $bird['fallSpeed'] -= 0.3;
    },
    'render' => function ($bird) {
        if ($bird['oldY'] !== $bird['y']) printToCoordinates($bird['x'], $bird['y'], 'B');
        printToCoordinates(20, $bird['oldY'], ' ');
    }
];

$playing = true;



printToCoordinates(0, SCREEN_HEIGHT - 3, str_repeat('=', SCREEN_WIDTH));
printToCoordinates(0, SCREEN_HEIGHT - 2, str_repeat('.', SCREEN_WIDTH));
printToCoordinates(0, SCREEN_HEIGHT - 1, str_repeat('.', SCREEN_WIDTH));
printToCoordinates(0, SCREEN_HEIGHT, str_repeat('.', SCREEN_WIDTH));

printToCoordinates(SCREEN_WIDTH, SCREEN_HEIGHT - 11, '======');
printToCoordinates(SCREEN_WIDTH, SCREEN_HEIGHT - 10, '======');
printToCoordinates(SCREEN_WIDTH, SCREEN_HEIGHT - 9, '|    |');
printToCoordinates(SCREEN_WIDTH, SCREEN_HEIGHT - 8, '|    |');
printToCoordinates(SCREEN_WIDTH, SCREEN_HEIGHT - 7, '|    |');
printToCoordinates(SCREEN_WIDTH, SCREEN_HEIGHT - 6, '|    |');
printToCoordinates(SCREEN_WIDTH, SCREEN_HEIGHT - 4, '|    |');
printToCoordinates(SCREEN_WIDTH, SCREEN_HEIGHT - 5, '|    |');


while ($playing) {

    $input = getKeyboardInput();

    if ($input === ' ') {
        $bird['fallSpeed'] = 3;
    }

    $bird['update']($bird);
    $bird['render']($bird);




    usleep(100000);
}
