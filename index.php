<?php

// Constante de la taille de l'écran du flappy
define('SCREEN_WIDTH', 100);
define('SCREEN_HEIGHT', 30);

// On définit le tuyau vide par défaut
$emptyPipe = '';
for ($i = 0; $i < SCREEN_HEIGHT - 4; $i++) {
    $emptyPipe .= "      \n";
}
define('EMPTY_PIPE', $emptyPipe);


require 'utils.php';
require 'Pipe.php';

// LOGGER
require 'Logger.php';
$logger = new Logger;


clearTerminal();
hideCursor();

$bird = [
    'oldY' => 0,
    'x' => 20,
    'y' => round((SCREEN_HEIGHT - 4) / 2),
    'fallSpeed' => 0,

    // Fonction d'update de l'oiseau
    'update' => function (&$bird) {
        $bird['oldY'] = round($bird['y']);
        $bird['y'] -= $bird['fallSpeed'] -= 0.2;
    },

    // Fonction de render de l'oiseau
    'render' => function ($bird) {
        if ($bird['oldY'] !== $bird['y']) printToCoordinates($bird['x'], $bird['y'], 'B');
        printToCoordinates(20, $bird['oldY'], ' ');
    }
];

$playing = true;
$pipes = [];
$pipeGenerationDelay = 0;

// On dessine le sol
$ground = str_repeat('=', SCREEN_WIDTH) . "\n" . str_repeat('.', SCREEN_WIDTH) . "\n" . str_repeat('.', SCREEN_WIDTH) . "\n" . str_repeat('.', SCREEN_WIDTH);
printToCoordinates(0, SCREEN_HEIGHT - 3, $ground);

$pipes[] = new Pipe($logger);

while ($playing) {

    // Saute si la touche espace est appuyé
    $input = getKeyboardInput();
    if ($input === ' ') {
        $bird['fallSpeed'] = 2;
    }

    // Update puis render de l'oiseau
    $bird['update']($bird);
    $bird['render']($bird);

    $pipeGenerationDelay++;

    // On spawn des tuyaux tous les 50 ticks
    if ($pipeGenerationDelay % 50 == 0) {
        $pipes[] = new Pipe();
    }

    foreach ($pipes as $key => $pipe) {
        $pipe->update($bird, $logger);
        $pipe->render();

        // Si le tuyau arrive sur le bord
        if ($pipe->x < 1) {
            unset($pipes[$key]);
        }
    }

    usleep(100000);
}
