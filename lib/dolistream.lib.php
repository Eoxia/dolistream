<?php
/* Copyright (C) 2024  Eoxia <technique@eoxia.com>
 * This program is free software under GNU GPL v3+
 */

/**
 * \file    htdocs/custom/dolistream/lib/dolistream.lib.php
 * \ingroup dolistream
 * \brief   Library of helper functions for DoliStream module
 */

/**
 * Prepare admin pages header tabs
 *
 * @return array
 */
function dolinstreamAdminPrepareHead(): array
{
	global $langs, $conf;
	$langs->load('dolistream@dolistream');

	$h    = 0;
	$head = array();

	$head[$h][0] = DOL_URL_ROOT . '/custom/dolistream/admin/setup.php';
	$head[$h][1] = $langs->trans('Setup');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = DOL_URL_ROOT . '/custom/dolistream/admin/about.php';
	$head[$h][1] = $langs->trans('About');
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolistream@dolistream');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolistream@dolistream', 'remove');

	return $head;
}

/**
 * Get a pool of random dates (past 2 years) for bulk generation
 *
 * @return int[]  Array of unix timestamps
 */
function dolinstreamGetRandomDates(): array
{
	$year  = idate('Y') - 1;
	$dates = array();
	foreach (range(1, 12) as $m) {
		$maxDay = cal_days_in_month(CAL_GREGORIAN, $m, $year);
		foreach (array(3, 9, 13, 19, 23, 28) as $d) {
			$day      = min($d, $maxDay);
			$dates[]  = mktime(12, 0, 0, $m, $day, $year);
			$dates[]  = mktime(12, 0, 0, $m, $day, $year - 1);
		}
	}
	return $dates;
}

/**
 * Fetch rowids of client thirdparties
 *
 * @param  DoliDB $db
 * @return int[]
 */
function dolinstreamGetClientIds(DoliDB $db): array
{
	$ids   = array();
	$resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'societe WHERE client IN (1,2,3)');
	if ($resql) {
		while ($row = $db->fetch_row($resql)) {
			$ids[] = (int) $row[0];
		}
		$db->free($resql);
	}
	return $ids;
}

/**
 * Fetch rowids of sellable products
 *
 * @param  DoliDB $db
 * @return int[]
 */
function dolinstreamGetProductIds(DoliDB $db): array
{
	$ids   = array();
	$resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'product WHERE tosell=1');
	if ($resql) {
		while ($row = $db->fetch_row($resql)) {
			$ids[] = (int) $row[0];
		}
		$db->free($resql);
	}
	return $ids;
}
/**
 * Fetch rowids of supplier thirdparties
 *
 * @param  DoliDB $db
 * @return int[]
 */
function dolinstreamGetSupplierIds(DoliDB $db): array
{
	$ids   = array();
	$resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'societe WHERE fournisseur = 1');
	if ($resql) {
		while ($row = $db->fetch_row($resql)) {
			$ids[] = (int) $row[0];
		}
		$db->free($resql);
	}
	return $ids;
}

/**
 * Fetch rowids of validated customer orders (statut=1 = validated)
 *
 * @param  DoliDB $db
 * @return int[]
 */
function dolinstreamGetClientOrderIds(DoliDB $db): array
{
	$ids   = array();
	$resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'commande WHERE fk_statut = 1 ORDER BY rowid DESC LIMIT 200');
	if ($resql) {
		while ($row = $db->fetch_row($resql)) {
			$ids[] = (int) $row[0];
		}
		$db->free($resql);
	}
	return $ids;
}

/**
 * Fetch rowids of validated supplier orders (statut=3 = validated/approved)
 *
 * @param  DoliDB $db
 * @return int[]
 */
function dolinstreamGetSupplierOrderIds(DoliDB $db): array
{
	$ids   = array();
	// statut 3 = approved/validated in llx_commande_fournisseur
	$resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'commande_fournisseur WHERE fk_statut IN (3,4) ORDER BY rowid DESC LIMIT 200');
	if ($resql) {
		while ($row = $db->fetch_row($resql)) {
			$ids[] = (int) $row[0];
		}
		$db->free($resql);
	}
	return $ids;
}

/**
 * Fetch rowids of products buyable from suppliers (tobuy=1)
 *
 * @param  DoliDB $db
 * @return int[]
 */
function dolinstreamGetBuyProductIds(DoliDB $db): array
{
	$ids   = array();
	$resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'product WHERE tobuy = 1');
	if ($resql) {
		while ($row = $db->fetch_row($resql)) {
			$ids[] = (int) $row[0];
		}
		$db->free($resql);
	}
	// Fallback : all products
	if (empty($ids)) {
		$resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'product LIMIT 100');
		if ($resql) {
			while ($row = $db->fetch_row($resql)) {
				$ids[] = (int) $row[0];
			}
			$db->free($resql);
		}
	}
	return $ids;
}
