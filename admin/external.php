<?php
/* Copyright (C) 2024  Eoxia <technique@eoxia.com>
 *
 * This program is free software under GNU GPL v3+
 */

/**
 * \file    htdocs/custom/dolistream/admin/external.php
 * \ingroup dolistream
 * \brief   DoliStream — external modules setup page
 */

// ── Bootstrap Dolibarr ───────────────────────────────────────────────────────
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace('..', '', $_SERVER["CONTEXT_DOCUMENT_ROOT"]) . "/main.inc.php";
}
$tmp  = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i    = strlen($tmp) - 1;
$j    = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// ── Bibliothèques ────────────────────────────────────────────────────────────
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/dolistream.lib.php';

// ── Sécurité ─────────────────────────────────────────────────────────────────
if (!$user->admin) {
	accessforbidden();
}

// ── Traductions ───────────────────────────────────────────────────────────────
$langs->loadLangs(array('admin', 'dolistream@dolistream'));

// ── Actions ──────────────────────────────────────────────────────────────────
$action     = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

include DOL_DOCUMENT_ROOT . '/core/actions_setmoduleoptions.inc.php';

/*
 * View
 */

$title = $langs->trans('ExternalModules');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolistream page-admin');

$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">'
	. img_picto($langs->trans('BackToModuleList'), 'back', 'class="pictofixedwidth"')
	. '<span class="hideonsmartphone">' . $langs->trans('BackToModuleList') . '</span></a>';

print load_fiche_titre($langs->trans('DoliStream'), $linkback, 'title_setup');

$head = dolinstreamAdminPrepareHead();
print dol_get_fiche_head($head, 'external', $langs->trans('DoliStream'), -1, 'technic');

print '<div class="warning">';
print '<strong>⚠ ' . $langs->trans('DoliStreamWarningDevOnly') . '</strong>';
print '</div><br>';

print '<span class="opacitymedium">' . $langs->trans('ExternalModulesDesc') . '</span><br><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Module') . '</td>';
print '<td class="center">' . $langs->trans('Status') . '</td>';
print '</tr>';

// Rental Module
print '<tr class="oddeven">';
print '<td>' . $langs->trans('ModuleRental') . '<br><span class="opacitymedium">' . $langs->trans('DolistreamRentalDesc') . '</span></td>';
print '<td class="center">';
print ajax_constantonoff('DOLISTREAM_ENABLE_RENTAL');
print '</td>';
print '</tr>';

print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
