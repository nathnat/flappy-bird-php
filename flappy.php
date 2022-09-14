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

/**
 * Cette fonction renvoie true si l'ordinateur est sous Windows
 * 
 * @return bool
 */
function isUnderWindows(): bool
{
	return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

/**
 * @param int      $x     Coordonnée X dans notre terminal
 * @param int      $y     Coordonnée Y dans notre terminal
 * @param string   $text  Texte à afficher aux coordonnées données
 */
function printToCoordinates($x, $y, string $text)
{
	fprintf(STDOUT, "\033[%d;%dH%s", round($y), round($x), $text);
}

/** Cette fonction efface tous le terminal */
function clearTerminal()
{
	fprintf(STDOUT, "\033[H\033[J");
}

function getKeyboardInput()
{
	if (isUnderWindows()) {
		return windowsKeyboardInput();
	}
	return unixKeyboardInput();
}

/**
 * Cette fonction lit l'input du terminal pour les systèmes Unix.
 * 
 * @return string L'input dans le terminal
 */
function unixKeyboardInput()
{
	static $streamReady = false;

	// On utilise une variable statique car on ne veut exécuter cette fonction qu'une seule fois.
	if (!$streamReady) {

		// Set the stream to be non-blocking.
		stream_set_blocking(STDIN, false);
		system('stty cbreak -echo');
		$streamReady = true;
	}

	return fgets(STDIN);
}

/**
 * Fonctions qui récupère l'input du clavier sous windows en passant par le C et en utilisant l'extension FFI.
 * 
 * @return string Le charactère input
 */
function windowsKeyboardInput()
{
	// J'utilise ces constantes pour y voir plus clair dans la suite du code qui est déjà coriace.
	$STD_INPUT_HANDLE = -10;
	// https://docs.microsoft.com/fr-fr/windows/console/setconsolemode
	$ENABLE_PROCESSED_INPUT = 0x0001;
	$ENABLE_WINDOW_INPUT = 0x0008;
	// https://docs.microsoft.com/fr-fr/windows/console/input-record-str
	$KEY_EVENT = 0x0001;

	static $windows = null;
	static $handle = null;


	if (null === $windows) {
		// Cette définition vient du gist suivant qui détaille beaucoup plus la chose
		// https://gist.github.com/Nek-/118cc36d0d075febf614c53a48470490
		$windows = \FFI::cdef(<<<C
        typedef unsigned short wchar_t;
        typedef int BOOL;
        typedef unsigned long DWORD;
        typedef void *PVOID;
        typedef PVOID HANDLE;
        typedef DWORD *LPDWORD;
        typedef unsigned short WORD;
        typedef wchar_t WCHAR;
        typedef short SHORT;
        typedef unsigned int UINT;
        typedef char CHAR;
        typedef struct _COORD {
          SHORT X;
          SHORT Y;
        } COORD, *PCOORD;
        typedef struct _WINDOW_BUFFER_SIZE_RECORD {
          COORD dwSize;
        } WINDOW_BUFFER_SIZE_RECORD;
        typedef struct _MENU_EVENT_RECORD {
          UINT dwCommandId;
        } MENU_EVENT_RECORD, *PMENU_EVENT_RECORD;
        typedef struct _KEY_EVENT_RECORD {
          BOOL  bKeyDown;
          WORD  wRepeatCount;
          WORD  wVirtualKeyCode;
          WORD  wVirtualScanCode;
          union {
            WCHAR UnicodeChar;
            CHAR  AsciiChar;
          } uChar;
          DWORD dwControlKeyState;
        } KEY_EVENT_RECORD;
        typedef struct _MOUSE_EVENT_RECORD {
          COORD dwMousePosition;
          DWORD dwButtonState;
          DWORD dwControlKeyState;
          DWORD dwEventFlags;
        } MOUSE_EVENT_RECORD;
        typedef struct _FOCUS_EVENT_RECORD {
          BOOL bSetFocus;
        } FOCUS_EVENT_RECORD;
        typedef struct _INPUT_RECORD {
          WORD  EventType;
          union {
            KEY_EVENT_RECORD          KeyEvent;
            MOUSE_EVENT_RECORD        MouseEvent;
            WINDOW_BUFFER_SIZE_RECORD WindowBufferSizeEvent;
            MENU_EVENT_RECORD         MenuEvent;
            FOCUS_EVENT_RECORD        FocusEvent;
          } Event;
        } INPUT_RECORD;
        typedef INPUT_RECORD *PINPUT_RECORD;
        HANDLE GetStdHandle(DWORD nStdHandle);
        BOOL GetConsoleMode(
            HANDLE  hConsoleHandle,
            LPDWORD lpMode
        );
        BOOL SetConsoleMode(
          HANDLE hConsoleHandle,
          DWORD  dwMode
        );
        BOOL GetNumberOfConsoleInputEvents(
          HANDLE  hConsoleInput,
          LPDWORD lpcNumberOfEvents
        );
        BOOL ReadConsoleInputA(
          HANDLE        hConsoleInput,
          PINPUT_RECORD lpBuffer,
          DWORD         nLength,
          LPDWORD       lpNumberOfEventsRead
        );
        BOOL ReadConsoleInputW(
          HANDLE        hConsoleInput,
          PINPUT_RECORD lpBuffer,
          DWORD         nLength,
          LPDWORD       lpNumberOfEventsRead
        );
        BOOL CloseHandle(HANDLE hObject);
        C, 'C:\\Windows\\System32\\kernel32.dll');

		$handle = $windows->GetStdHandle($STD_INPUT_HANDLE);

		$newConsoleMode = $ENABLE_WINDOW_INPUT | $ENABLE_PROCESSED_INPUT;
		if (!$windows->SetConsoleMode($handle, $newConsoleMode)) {
			throw new \RuntimeException('Il y a un problème avec la fonction SetConsoleMode: impossible de capturer les entrées...! Si vous avez cette erreur postez un message sur le forum de Zeste de Savoir.');
		}
	}

	$availableCharsInBuffer = $windows->new('DWORD');
	$localInputBufferSize = 128;
	$localInputBuffer = $windows->new("INPUT_RECORD[$localInputBufferSize]");
	$inputInLocalBuffer = $windows->new('DWORD');


	$windows->GetNumberOfConsoleInputEvents(
		$handle,
		\FFI::addr($availableCharsInBuffer)
	);

	// Le caractère \0 est quasiment toujours disponible mais ne nous intéresse pas !
	if ($availableCharsInBuffer->cdata <= 1) {
		return null; // Encore la petite technique de retour le plus rapidement possible pour éviter d'avoir un niveau supplémentaire
	}

	if (!$windows->ReadConsoleInputA($handle, $localInputBuffer, $localInputBufferSize, \FFI::addr($inputInLocalBuffer))) {
		throw new \RuntimeException('Il y a un problème avec la fonction ReadConsoleInputW: impossible de capturer les entrées...! Si vous avez cette erreur postez un message sur le forum de Zeste de Savoir.');
	}

	for ($i = $inputInLocalBuffer->cdata - 1; $i >= 0; $i--) {
		if ($localInputBuffer[$i]->EventType === $KEY_EVENT) {
			$keyEvent = $localInputBuffer[$i]->Event->KeyEvent;

			return $keyEvent->uChar->AsciiChar;
		}
	}

	return null;
}

/**
 * Cette fonction permet de cacher le curseur du terminal.
 *
 * Elle écrit sur le stream STDOUT des caractères spéciaux qui vont informer le terminal
 * qu'on veut effacer le curseur. Elle déclare aussi une fonction qui s'exécutera à la fin
 * de l'exécution, son but : rétablir le curseur ! Toujours avec des caractères spéciaux.
 */
function hideCursor(): void
{
	fprintf(STDOUT, "\033[?25l"); // cache le curseur

	// A la fin de l'exécution on affiche le curseur
	register_shutdown_function(function () {
		fprintf(STDOUT, "\033[?25h"); // montre le curseur
	});

	// Si jamais on fait CTRL+C on réaffiche le curseur (et on arrête le programme)
	if (function_exists('pcntl_signal')) {
		// Cette fonction n'existe que sous linux et macos
		pcntl_signal(SIGINT, function () {
			fprintf(STDOUT, "\033[?25h"); // montre le curseur
			clearTerminal();
			exit;
		});
	} else {
		// Sous windows on doit en utiliser une autre.
		sapi_windows_set_ctrl_handler(function (int $event) {
			if ($event === PHP_WINDOWS_EVENT_CTRL_C) {
				fprintf(STDOUT, "\033[?25h"); // montre le curseur
				clearTerminal();
				exit;
			}
		});
	}
}

/**
 * Classe des tuyaux
 */
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

    public function update($bird, &$score)
    {
        // Quand on arrive au niveau du tuyau
        if ($bird['x'] >= $this->x && $bird['x'] <= $this->x + 6) {
            // On vérifie si il y a une collision
            if (
                $bird['y'] >= SCREEN_HEIGHT - $this->bottomPipeHeight - 2
                || $bird['y'] < $this->topPipeHeight + 1
            ) {
                gameOver();
            }
        }

        if ($bird['x'] >= $this->x && $bird['x'] == $this->x + 6) {
            $score++;
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

// L'oiseau
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

// Execution du jeu
clearTerminal();
hideCursor();

printToCoordinates(0, SCREEN_HEIGHT / 2 - 5, $startScreen);

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
