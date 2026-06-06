<?php
/**
 * DoliStream - Téléchargement d'un fichier log
 * URL: /custom/dolistream/view/download_log.php?f=filename.txt
 */

$res = @include '../../../main.inc.php';
if (!$res) {
	$res = @include '../../../../main.inc.php';
}
if (!$res || !$user->id) {
	http_response_code(403);
	die('Accès refusé');
}
if (!$user->admin && empty($user->rights->dolistream->run)) {
	http_response_code(403);
	die('Droits insuffisants');
}

$file = GETPOST('f', 'alpha');
// Sécurité : nom de fichier uniquement, pas de path traversal
$file = basename($file);
if (!preg_match('/^dolistream-[a-z0-9-]+-\d{8}-\d{6}\.txt$/', $file)) {
	http_response_code(400);
	die('Fichier invalide');
}

$fullPath = DOL_DATA_ROOT . '/dolistream/' . $file;
if (!file_exists($fullPath)) {
	http_response_code(404);
	die('Fichier introuvable');
}

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
