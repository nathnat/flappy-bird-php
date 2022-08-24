
<?php
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
 * @param int|null $color Couleur du texte (disponible en constante en haut du fichier)
 */
function printToCoordinates($x, $y, string $text)
{
	//fprintf(STDOUT,"\x1b7\x1b[".$y.';'.$x.'f'.$text."\x1b8");
	fprintf(STDOUT, "\033[%d;%dH%s", round($y), round($x), $text);
}

function clearTerminal()
{
	fprintf(STDOUT, "\033[H\033[J");
}

/**
 * La fonction tailleFenetre fait appel à un autre programme qui écrit la taille de la fenêtre (deux chiffres)
 * sur la sortie standard.
 *
 * Elle s'occupe ensuite de découper ces deux chiffres à l'aide de la fonction explode.
 *
 * Attention tout de même, l'appel d'un programme externe peut se révéler coûteux pour PHP car on "sort" de PHP. De
 * manière générale on va éviter les appels externe autant qu'on le peut, de plus cela peut aussi poser des problèmes
 * de sécurité.
 *
 * @return array
 */
function terminalSize(): array
{
	// La syntaxe `` est un peu particulière en PHP. C'est une façon rapide d'utiliser la fonction "exec"
	// pour exécuter un programme externe. On l'utilise rarement principalement parce qu'on ne
	// contrôle pas grand chose avec son utilisation et elle peut même s'avérer dangereuse dans certains cas.
	// Mais pour notre petit exercice cela conviendra parfaitement.

	if (isUnderWindows()) {
		// La version pour windows
		$info = `mode CON`;

		if (null === $info || !preg_match('/--------+\r?\n.+?(\d+)\r?\n.+?(\d+)\r?\n/', $info, $matches)) {
			return null;
		}

		return [(int) $matches[2], (int) $matches[1]];
	}

	// Sous Linux
	$width = intval(`tput cols`);
	$height = intval(`tput lines`);

	return [$width, $height];
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
 * Chers petits curieux, je pense que vous pouvez passer votre chemin ici !
 * Le principe de réutiliser des fonctions est de pouvoir utiliser "facilement" du code bien plus compliqué. Vous ne
 * pouviez pas tomber sur un plus bel exemple. Le code qui suit est très complexe, j'ai même eu du mal à l'écrire.
 * J'ai maudit plusieurs fois Windows mais je tenais à ce que ce TP fonctionne bien même sous Windows SANS WSL.
 * (car oui, ceux d'entre vous qui auront choisi d'installer WSL au début du cours ont des fonctionnalités supplémentaires)
 *
 * @param int[] $touches Les touches à détecter
 * @return int|null La touche détectée ou null.
 */
function windowsKeyboardInput()
{

	// J'utilise ces constantes pour y voir plus clair dans la suite du code qui est déjà coriace.
	$STD_INPUT_HANDLE = -10;
	// https://docs.microsoft.com/fr-fr/windows/console/setconsolemode
	$ENABLE_ECHO_INPUT = 0x0004;
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
