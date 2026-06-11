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

	$head[$h][0] = DOL_URL_ROOT . '/custom/dolistream/admin/external.php';
	$head[$h][1] = $langs->trans('ExternalModules');
	$head[$h][2] = 'external';
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
function dolinstreamGetProductIds(DoliDB $db, string $batchMode = 'all', string $inStock = 'all'): array
{
	$ids   = array();
	$sql = 'SELECT p.rowid FROM ' . MAIN_DB_PREFIX . 'product p WHERE p.tosell=1';

	if ($batchMode === 'no_batch') {
		$sql .= ' AND p.tobatch = 0';
	} elseif ($batchMode === 'lot') {
		$sql .= ' AND p.tobatch = 1';
	} elseif ($batchMode === 'serial') {
		$sql .= ' AND p.tobatch = 2';
	}

	if ($inStock === 'yes') {
		$sql .= ' AND IFNULL((SELECT SUM(reel) FROM ' . MAIN_DB_PREFIX . 'product_stock WHERE fk_product = p.rowid), 0) > 0';
	} elseif ($inStock === 'no') {
		$sql .= ' AND IFNULL((SELECT SUM(reel) FROM ' . MAIN_DB_PREFIX . 'product_stock WHERE fk_product = p.rowid), 0) <= 0';
	}

	$resql = $db->query($sql);
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
	$sql = 'SELECT DISTINCT c.rowid FROM ' . MAIN_DB_PREFIX . 'commande c ';
	$sql.= 'JOIN ' . MAIN_DB_PREFIX . 'commandedet cd ON cd.fk_commande = c.rowid ';
	$sql.= 'WHERE c.fk_statut = 1 AND cd.product_type = 0 ';
	$sql.= 'ORDER BY c.rowid DESC LIMIT 200';
	$resql = $db->query($sql);
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

/**
 * Fetch rowids of open warehouses (statut=1)
 *
 * @param  DoliDB $db
 * @return int[]
 */
function dolinstreamGetWarehouseIds(DoliDB $db): array
{
	$ids   = array();
	$resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'entrepot WHERE statut = 1 ORDER BY rowid LIMIT 200');
	if ($resql) {
		while ($row = $db->fetch_row($resql)) {
			$ids[] = (int) $row[0];
		}
		$db->free($resql);
	}
	return $ids;
}

/**
 * Fetch rowids of stockable products, optionally filtered by type
 *
 * @param  DoliDB $db
 * @param  string $type 'all' | 'product' (type=0) | 'service' (type=1)
 * @return int[]
 */
function dolinstreamGetStockableProductIds(DoliDB $db, string $type = 'all'): array
{
	$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'product WHERE tosell = 1';
	if ($type === 'product') {
		$sql .= ' AND fk_product_type = 0';
	} elseif ($type === 'service') {
		$sql .= ' AND fk_product_type = 1';
	}
	$sql .= ' ORDER BY rowid LIMIT 500';

	$ids   = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($row = $db->fetch_row($resql)) {
			$ids[] = (int) $row[0];
		}
		$db->free($resql);
	}
	// Fallback : tous les produits si aucun actif
	if (empty($ids)) {
		$resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'product LIMIT 200');
		if ($resql) {
			while ($row = $db->fetch_row($resql)) {
				$ids[] = (int) $row[0];
			}
			$db->free($resql);
		}
	}
	return $ids;
}
