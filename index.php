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

// L'écran de startup
$spaceBefore = str_repeat(' ', SCREEN_WIDTH / 2 - 26);
$startScreen = "";
$startScreen .= $spaceBefore . " ______ _                           ____  _         _ \n";
$startScreen .= $spaceBefore . "|  ____| |                         |  _ \(_)       | |\n";
$startScreen .= $spaceBefore . "| |__  | | __ _ _ __  _ __  _   _  | |_) |_ _ __ __| |\n";
$startScreen .= $spaceBefore . "|  __| | |/ _` | '_ \| '_ \| | | | |  _ <| | '__/ _` |\n";
$startScreen .= $spaceBefore . "| |    | | (_| | |_) | |_) | |_| | | |_) | | | | (_| |\n";
$startScreen .= $spaceBefore . "|_|    |_|\__,_| .__/| .__/ \__, | |____/|_|_|  \__,_|\n";
$startScreen .= $spaceBefore . "               | |   | |     __/ |                    \n";
$startScreen .= $spaceBefore . "               |_|   |_|    |___/                     \n";
$startScreen .= $spaceBefore . "                                                      \n";
$startScreen .= $spaceBefore . "                                                      \n";
$startScreen .= $spaceBefore . "              press <spacebar> to start                 ";

printToCoordinates(0, SCREEN_HEIGHT / 2 - 5, $startScreen);


$bird = [
    'oldY' => 0,
    'x' => 20,
    'y' => round((SCREEN_HEIGHT - 4) / 2),
    'fallSpeed' => 0,
    'animate' => 0,

    // Fonction d'update de l'oiseau
    'update' => function (&$bird) {
        $bird['oldY'] = round($bird['y']);
        $bird['y'] -= $bird['fallSpeed'] -= 0.2;

        if ($bird['y'] > SCREEN_HEIGHT - 2) {
            gameOver();
        }

        $bird['animate']++;
    },

    // Fonction de render de l'oiseau
    'render' => function ($bird) {
        if ($bird['oldY'] !== $bird['y']) {
            printToCoordinates($bird['x'], $bird['y'], '\o/');
            
            if ($bird['animate'] % 2 == 0) {
                printToCoordinates($bird['x'], $bird['y'], '/o\\');
            }

            if ($bird['y'] > $bird['oldY']) {
                printToCoordinates($bird['x'], $bird['y'], '\o/');
            }
        }
        printToCoordinates(20, $bird['oldY'], '   ');
    },

    // Fonction de reset pour le game over
    'reset' => function (&$bird) {
        $bird['oldY'] = 0;
        $bird['x'] = 20;
        $bird['y'] = round((SCREEN_HEIGHT - 4) / 2);
        $bird['fallSpeed'] = 0;
        $bird['animate'] = 0;
    }
];

function gameOver()
{
    // On clear l'écran
    clearTerminal();

    if ($GLOBALS['score'] > $GLOBALS['bestScore']) {
        $GLOBALS['bestScore'] = $GLOBALS['score'];
    }

    // On affiche l'écran de game over
    $spaceBefore = str_repeat(' ', SCREEN_WIDTH / 2 - 26);
    $gameOverScreen = "";
    $gameOverScreen .= $spaceBefore . "  _____                         ____                    \n";
    $gameOverScreen .= $spaceBefore . " / ____|                       / __ \                   \n";
    $gameOverScreen .= $spaceBefore . "| |  __  __ _ _ __ ___   ___  | |  | |_   _____ _ __    \n";
    $gameOverScreen .= $spaceBefore . "| | |_ |/ _` | '_ ` _ \ / _ \ | |  | \ \ / / _ \ '__|   \n";
    $gameOverScreen .= $spaceBefore . "| |__| | (_| | | | | | |  __/ | |__| |\ V /  __/ |      \n";
    $gameOverScreen .= $spaceBefore . " \_____|\__,_|_| |_| |_|\___|  \____/  \_/ \___|_|      \n";
    $gameOverScreen .= $spaceBefore . "                                                        \n";
    $gameOverScreen .= $spaceBefore . "                    SCORE : " . $GLOBALS['score'] .    "\n";
    $gameOverScreen .= $spaceBefore . "                     BEST : " . $GLOBALS['bestScore'] ."\n";
    $gameOverScreen .= $spaceBefore . "                                                        \n";
    $gameOverScreen .= $spaceBefore . "            press <spacebar> to play again              ";

    printToCoordinates(0, SCREEN_HEIGHT / 2 - 5, $gameOverScreen);

    // On reset les variables
    $GLOBALS['score'] = 0;
    $GLOBALS['pipes'] = [];
    $GLOBALS['playing'] = false;
    $GLOBALS['starting'] = true;
    $GLOBALS['pipeGenerationDelay'] = 0;

    // On reset l'oiseau
    $GLOBALS['bird']['reset']($GLOBALS['bird']);
}

$score = 0;
$pipes = [];
$bestScore = 0;
$playing = false;
$starting = true;
$pipeGenerationDelay = 0;

while (1) {
    // On récupère l'input du clavier
    $keyboardInput = getKeyboardInput();

    // Le début de la game
    if ($starting && $keyboardInput === ' ') {
        $starting = false;

        clearTerminal();
        $playing = true;

        // On dessine le sol
        $ground = str_repeat('=', SCREEN_WIDTH) . "\n" . str_repeat(str_repeat('.', SCREEN_WIDTH) . "\n", 3);
        printToCoordinates(0, SCREEN_HEIGHT - 3, $ground);

        // On initialise le premier tuyau
        $pipes[] = new Pipe();
    }

    // On saute l'éxecution de la suite si le jeu n'est pas en cours
    if (!$playing) continue;

    // Affichage du score
    printToCoordinates(5, SCREEN_HEIGHT - 1, ' SCORE : ' . $score . ' ');

    // Saute si la touche espace est appuyé
    if ($keyboardInput === ' ') {
        $bird['fallSpeed'] = 2;
    }

    // Update puis render de l'oiseau
    $bird['update']($bird);
    if ($playing) {
        $bird['render']($bird);
    }

    $pipeGenerationDelay++;

    // On spawn des tuyaux tous les 50 ticks
    if ($pipeGenerationDelay % 50 == 0) {
        $pipes[] = new Pipe;
    }

    foreach ($pipes as $key => $pipe) {
        $pipe->update($bird, $score);

        if (!$playing) break;

        $pipe->render();

        // Si le tuyau arrive sur le bord
        if ($pipe->x < 1) {
            unset($pipes[$key]);
        }
    }

    usleep(100000);
}
