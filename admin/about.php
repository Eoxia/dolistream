<?php
/* Copyright (C) 2024  Eoxia <technique@eoxia.com>
 * This program is free software under GNU GPL v3+
 */

/**
 * \file    htdocs/custom/dolistream/admin/about.php
 * \ingroup dolistream
 * \brief   DoliStream — about page
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace('..', '', $_SERVER["CONTEXT_DOCUMENT_ROOT"]) . "/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php"))   { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/dolistream.lib.php';

if (!$user->admin) { accessforbidden(); }

$langs->loadLangs(array('admin', 'dolistream@dolistream'));
$backtopage = GETPOST('backtopage', 'alpha');

$title = $langs->trans('DoliStream') . ' — ' . $langs->trans('About');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolistream page-admin-about');

$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">'
	. img_picto($langs->trans('BackToModuleList'), 'back', 'class="pictofixedwidth"')
	. '<span class="hideonsmartphone">' . $langs->trans('BackToModuleList') . '</span></a>';

print load_fiche_titre($langs->trans('DoliStream'), $linkback, 'title_setup');

$head = dolinstreamAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans('DoliStream'), -1, 'technic');

print '<div class="info">';
print '<h3>DoliStream v1.0.0</h3>';
print '<p><strong>' . $langs->trans('Publisher') . ' :</strong> <a href="https://www.eoxia.com" target="_blank">Eoxia</a></p>';
print '<p><strong>Contact :</strong> <a href="mailto:technique@eoxia.com">technique@eoxia.com</a></p>';
print '<p>' . $langs->trans('DoliStreamDesc') . '</p>';
print '<br>';
print '<strong>⚠ ' . $langs->trans('DoliStreamWarningDevOnly') . '</strong>';
print '</div><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">Fonctionnalités</td></tr>';
print '<tr class="oddeven"><td>✔</td><td>Génération de tiers (clients/fournisseurs) aléatoires</td></tr>';
print '<tr class="oddeven"><td>✔</td><td>Génération de produits aléatoires</td></tr>';
print '<tr class="oddeven"><td>✔</td><td>Génération de factures validées</td></tr>';
print '<tr class="oddeven"><td>✔</td><td>Génération de commandes validées</td></tr>';
print '<tr class="oddeven"><td>✔</td><td>Génération de devis</td></tr>';
print '<tr class="oddeven"><td>✔</td><td>Purge de données par catégorie et date (mode test/confirm)</td></tr>';
print '<tr class="oddeven"><td>✔</td><td>Statistiques temps réel de la base de données</td></tr>';
print '<tr class="oddeven"><td>✔</td><td>Interface 100% intégrée Dolibarr (pas de subprocess)</td></tr>';
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
