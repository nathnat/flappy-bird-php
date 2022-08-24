<?php

class Pipe
{
    public $x;
    protected $topPipeHeight;
    protected $bottomPipeHeight;

    public function __construct()
    {
        $this->x = SCREEN_WIDTH;

        // On génère la taille des tuyaux
        $this->topPipeHeight = rand(2, SCREEN_HEIGHT / 2);
        $this->bottomPipeHeight = SCREEN_HEIGHT - $this->topPipeHeight - 7 - 7;
    }

    public function __destruct()
    {
        // Au moment du unset on fait disparaitre les tuyaux
        printToCoordinates($this->x, 0, EMPTY_PIPE);
    }

    public function update($bird)
    {
        // Quand on arrive au niveau du tuyau
        if ($bird['x'] >= $this->x && $bird['x'] <= $this->x + 6) {
            // On vérifie si il y a une collision
            if (
                ($bird['y'] >= SCREEN_HEIGHT - $this->bottomPipeHeight - 2)
                || ($bird['y'] < $this->topPipeHeight + 1)
            )   
            {
                exit;
            }
        }

        $this->x -= 1;
    }

    public function render()
    {
        // Pipe du haut
        for ($i = 0; $i <= $this->topPipeHeight; $i++) {
            // On rajoute a la fin le haut du tuyau
            if ($i == $this->topPipeHeight) {
                printToCoordinates($this->x, $this->topPipeHeight, '====== ');
                printToCoordinates($this->x, $this->topPipeHeight + 1, '====== ');
                break;
            }

            printToCoordinates($this->x, 0 + $i, '|    | ');
        }

        // Pipe du bas
        for ($i = 0; $i <= $this->bottomPipeHeight; $i++) {

            if ($i == $this->bottomPipeHeight) {
                printToCoordinates($this->x, SCREEN_HEIGHT - 4 - $this->bottomPipeHeight, '====== ');
                printToCoordinates($this->x, SCREEN_HEIGHT - 4 - $this->bottomPipeHeight - 1, '====== ');
                break;
            }

            printToCoordinates($this->x, SCREEN_HEIGHT - 4 - $i, '|    | ');
        }
    }
}
