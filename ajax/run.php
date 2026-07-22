<?php
/**
 * \file    htdocs/custom/dolistream/ajax/run.php
 * \ingroup dolistream
 * \brief   DoliStream — AJAX endpoint for async script execution + log streaming.
 *
 * Avoids Dolibarr's full HTML rendering pipeline.
 * view/index.php detects DOLISTREAM_AJAX_RUN and returns JSON + exits.
 *
 * URL    : POST /dolibarr/htdocs/custom/dolistream/ajax/run.php
 * Params : script, nb, [field params], token
 * Returns: {"ok":N,"ko":M,"warn":W,"total":T}
 */

// ── Dolibarr AJAX bootstrap constants ─────────────────────────────────────────
// Ces constantes sont lues par main.inc.php pour alléger le chargement
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);  // Pas de renouvellement CSRF
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1'); // Pas de rendu menu
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1'); // Pas d'en-têtes HTML
if (!defined('NOREQUIRESOC'))   define('NOREQUIRESOC', '1');  // Pas de session societe

// ── Signal à view/index.php : mode AJAX, retourner JSON et sortir ─────────────
define('DOLISTREAM_AJAX_RUN', 1);

// ── Bootstrap Dolibarr avec chemin absolu ─────────────────────────────────────
// ajax/ est 3 niveaux sous htdocs/ : ajax → dolistream → custom → htdocs/main.inc.php
$_mainPath = realpath(__DIR__ . '/../../../main.inc.php');
if (!$_mainPath || !file_exists($_mainPath)) {
	// Tentative alternative via DOCUMENT_ROOT
	$_dr = $_SERVER['DOCUMENT_ROOT'] ?? '';
	if ($_dr && file_exists($_dr . '/main.inc.php')) {
		$_mainPath = $_dr . '/main.inc.php';
	} elseif ($_dr && file_exists(dirname($_dr) . '/htdocs/main.inc.php')) {
		$_mainPath = dirname($_dr) . '/htdocs/main.inc.php';
	}
}

if (!$_mainPath || !file_exists($_mainPath)) {
	http_response_code(500);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['error' => 'Cannot locate main.inc.php. Searched from: ' . __DIR__]);
	exit;
}

require_once $_mainPath;
// Après ce require_once : $db, $user, $conf, $langs sont disponibles globalement

// ── Vérification d'accès (admin seulement) ────────────────────────────────────
// isModEnabled() et $user sont disponibles car main.inc.php est chargé
if (empty($user) || !$user->admin) {
	http_response_code(403);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['error' => 'Access denied — admin required']);
	exit;
}

if (!isModEnabled('dolistream')) {
	http_response_code(403);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['error' => 'Module dolistream is not enabled']);
	exit;
}

global $dolibarr_main_prod;
if (!empty($dolibarr_main_prod)) {
	http_response_code(403);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['error' => 'DoliStream cannot be used in a production environment (dolibarr_main_prod=1)']);
	exit;
}

// ── Déléguer l'exécution à view/index.php ────────────────────────────────────
// index.php voit DOLISTREAM_AJAX_RUN défini + $db déjà initialisé →
//   • skip du re-bootstrap (guard en haut de index.php)
//   • exécution du bloc action=run normalement
//   • sortie JSON avant llxHeader() / rendu HTML
dol_include_once('/dolistream/view/index.php');
