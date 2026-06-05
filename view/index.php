<?php
/* Copyright (C) 2024  Eoxia <technique@eoxia.com>
 * This program is free software under GNU GPL v3+
 *
 * ⚠ DEVELOPMENT MODULE — DO NOT USE IN PRODUCTION
 */

/**
 * \file    htdocs/custom/dolistream/view/index.php
 * \ingroup dolistream
 * \brief   DoliStream — main runner page (generation & purge)
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
if (!$res && file_exists("../../main.inc.php"))    { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

// ── Bibliothèques ────────────────────────────────────────────────────────────
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
if (file_exists(DOL_DOCUMENT_ROOT . '/reception/class/reception.class.php')) {
	require_once DOL_DOCUMENT_ROOT . '/reception/class/reception.class.php';
}
require_once '../lib/dolistream.lib.php';


// ── Sécurité ─────────────────────────────────────────────────────────────────
if (!isModEnabled('dolistream')) {
	accessforbidden('Module DoliStream is not enabled');
}
if (!$user->admin && !$user->hasRight('dolistream', 'generate', 'run')) {
	accessforbidden();
}

// ── Traductions ───────────────────────────────────────────────────────────────
$langs->loadLangs(array('dolistream@dolistream', 'admin', 'companies', 'products', 'orders', 'bills', 'propal'));

// ── Paramètres ───────────────────────────────────────────────────────────────
$action = GETPOST('action', 'aZ09');
$script = GETPOST('script', 'alpha');
$urlOpt = GETPOST('opt',    'alpha'); // pré-sélection depuis l'URL (ex: opt=supplier)
$page   = max(0, (int) GETPOST('page', 'int')); // pagination des résultats

// ── Progression (fichier temp pour éviter le lock de session) ─────────────────
$dsProgressFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ds_progress_' . session_id() . '.json';

/** Retourne la progression courante en JSON (handler AJAX) */
if ($action === 'progress') {
	header('Content-Type: application/json; charset=utf-8');
	echo file_exists($dsProgressFile)
		? file_get_contents($dsProgressFile)
		: json_encode(['pct' => 0, 'current' => 0, 'total' => 0, 'done' => false]);
	exit;
}

/** Écrit la progression dans un fichier tmp lisible par le handler AJAX */
function dolinstreamProgress(int $current, int $total, bool $done = false): void
{
	global $dsProgressFile;
	$pct = $total > 0 ? (int) round($current / $total * 100) : 0;
	file_put_contents($dsProgressFile, json_encode([
		'pct'     => $pct,
		'current' => $current,
		'total'   => $total,
		'done'    => $done,
	]));
}

// ── Log d'exécution ───────────────────────────────────────────────────────────
$scriptLog = array();

/**
 * Ajoute une ligne de log
 */
function dsLog(string $msg, string $level = 'info'): void
{
	global $scriptLog;
	$scriptLog[] = array('level' => $level, 'msg' => $msg);
}

// ═══════════════════════════════════════════════════════════════════════════════
// ACTIONS — logique inline, pas de subprocess
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'run' && !empty($script) && (int) GETPOST('token_check') >= 0) {
	// Vérification du token CSRF
	if (!newToken() && empty($conf->global->DOLISTREAM_DISABLE_TOKEN_CHECK)) {
		// token check géré par Dolibarr via newToken()
	}

	@set_time_limit(300);

	// Initialise le fichier de progression
	dolinstreamProgress(0, 0);

	$nb   = max(1, (int) GETPOST('nb', 'int'));
	$mode = GETPOST('mode', 'alpha');
	$opt  = GETPOST('opt', 'alpha');
	$date = GETPOST('date', 'alpha');

	// Libère le verrou de session pour que les requêtes AJAX de progression puissent aboutir
	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}

	// ── Utiliser l'utilisateur connecté directement ───────────────────────────
	// L'accès admin est déjà vérifié en haut de page par Dolibarr.
	$fuser = $user;
	if (empty($fuser->rights)) {
		$fuser->loadRights();
	}

	// ════════════════════════════════════════════════════════════════════════
	if ($script === 'generate-thirdparty') {
	// ════════════════════════════════════════════════════════════════════════
		$listoftown = array(
			'Paris', 'Lyon', 'Marseille', 'Bordeaux', 'Nantes', 'Toulouse',
			'Strasbourg', 'Lille', 'Rennes', 'Auray', 'Vannes', 'Haguenau',
			'Lauterbourg', 'Souffelweiersheim', 'Baden', 'Le Bono',
		);
		$listoffirstname = array(
			'Marc', 'Julie', 'Steve', 'Laurent', 'Nicolas', 'Isabelle',
			'Dorothée', 'Brigitte', 'Karine', 'José', 'Céline', 'Virginie',
			'Thomas', 'Emma', 'Lucas', 'Léa', 'Maxime', 'Camille',
		);

		dsLog($langs->trans('GenerateThirdparties') . ' : ' . $nb);

		// Correspondance type → flags Dolibarr
		// client : 0=non-client, 1=client, 2=prospect  |  fournisseur : 0/1
		$socTypeMap = array(
			'prospect'   => array('client' => 2, 'fourn' => 0, 'label' => 'Prospect'),
			'client'     => array('client' => 1, 'fourn' => 0, 'label' => 'Client'),
			'supplier'   => array('client' => 0, 'fourn' => 1, 'label' => 'Fournisseur'),
			'both'       => array('client' => 1, 'fourn' => 1, 'label' => 'Client & Fournisseur'),
		);
		// Si l'option choisie n'est pas reconnue (ou 'random'), on tire au sort à chaque itération
		$fixedType = isset($socTypeMap[$opt]) ? $socTypeMap[$opt] : null;

		$ok = $ko = 0;
		for ($s = 0; $s < $nb; $s++) {
			// Déterminer les flags client/fournisseur
			if ($fixedType !== null) {
				$socClient  = $fixedType['client'];
				$socFourn   = $fixedType['fourn'];
				$socTypeLabel = $fixedType['label'];
			} else {
				// Mode aléatoire : tire un type parmi les 4
				$rndType    = $socTypeMap[array_rand($socTypeMap)];
				$socClient  = $rndType['client'];
				$socFourn   = $rndType['fourn'];
				$socTypeLabel = $rndType['label'];
			}

			$soc               = new Societe($db);
			$soc->name         = 'Company ' . dol_print_date(dol_now(), 'dayhour') . '-' . $s;
			$soc->town         = $listoftown[array_rand($listoftown)];
			$soc->client       = $socClient;
			$soc->fournisseur  = $socFourn;
			// -1 = génération automatique via le module de numérotation configuré
			// (SOCIETE_CODECLIENT_ADDON / SOCIETE_CODEFOURNISSEUR_ADDON)
			$soc->code_client      = -1;
			$soc->code_fournisseur = -1;
			$soc->tva_assuj    = 1;
			$soc->country_id   = 1;
			$soc->country_code = 'FR';
			if (mt_rand(1, 3) === 3) {
				$soc->remise_percent = 5;
			}
			$soc->note_private = 'Créé par DoliStream';

			$socid = $soc->create($fuser);
			if ($socid > 0) {
				$rand = mt_rand(1, 3);
				for ($c = 0; $c < $rand; $c++) {
					$contact            = new Contact($db);
					$contact->socid     = $soc->id;
					$contact->lastname  = 'Lastname' . $c;
					$contact->firstname = $listoffirstname[array_rand($listoffirstname)];
					$contact->create($fuser);
				}
				dsLog(
					'✔ #' . $s . ' — ' . $soc->name . ' [' . $socTypeLabel . ']'
					. ' [cli=' . ($soc->code_client ?: '—') . ', fourn=' . ($soc->code_fournisseur ?: '—') . ']',
					'success'
				);
				$ok++;
			} else {
				dsLog('✘ #' . $s . ' — ' . $soc->error, 'error');
				$ko++;
			}
		}
		dsLog('─── ' . $ok . ' ' . $langs->trans('ResultSuccess') . ', ' . $ko . ' ' . $langs->trans('ResultErrors') . ' ───');

	// ════════════════════════════════════════════════════════════════════════
	} elseif ($script === 'generate-product') {
	// ════════════════════════════════════════════════════════════════════════
		dsLog($langs->trans('GenerateProducts') . ' : ' . $nb);
		// Charger le module de numérotation produit configuré
		// PRODUCT_ADDON (défaut : mod_codeproduct_leopard si non configuré)
		$productAddonName = getDolGlobalString('PRODUCT_ADDON', 'mod_codeproduct_leopard');
		$productAddonFile = DOL_DOCUMENT_ROOT . '/core/modules/product/' . $productAddonName . '.php';
		$productMod = null;
		if (file_exists($productAddonFile)) {
			require_once $productAddonFile;
			$productMod = new $productAddonName();
		}

		$ok = $ko = 0;
		for ($s = 0; $s < $nb; $s++) {
			$product                    = new Product($db);
			$product->type              = mt_rand(0, 1); // 0=produit, 1=service
			$product->status            = 1;
			$product->status_buy        = mt_rand(0, 1);
			$product->finished          = mt_rand(0, 1);
			$product->stockable_product = mt_rand(0, 1);
			$product->description       = 'Généré automatiquement par DoliStream.';
			$product->price             = mt_rand(100, 99999) / 100;
			$product->tva_tx            = '20.000';

			// ── Référence via le module de numérotation Dolibarr configuré ────────
			// Reproduit ce que l'interface Dolibarr fait dans product/card.php.
			// getNextValue($obj, $type) : type 0=produit, 1=service
			if ($productMod !== null) {
				$productRef = $productMod->getNextValue($product, $product->type);
			} else {
				$productRef = '';
			}
			if (!$productRef || $productRef === -1) {
				// Module non configuré (masque vide) → fallback timestamp unique
				$productRef = ($product->type ? 'SRV' : 'PRD') . '-' . dol_print_date(dol_now(), 'dayhour') . '-' . sprintf('%04d', $s);
				dsLog('ℹ #' . $s . ' — module ' . $productAddonName . ' non configuré, utilise ref fallback', 'warn');
			}
			$product->ref   = $productRef;
			$product->label = ($product->type ? 'Service ' : 'Produit ') . dol_print_date(dol_now(), 'dayhour') . '-' . sprintf('%04d', $s);
			// ─────────────────────────────────────────────────────────────────────

			$ret = $product->create($fuser);
			if ($ret >= 0) {
				dsLog('✔ #' . $s . ' — ' . $product->ref . ' (' . $product->price . ' €)', 'success');
				$ok++;
			} else {
				dsLog('✘ #' . $s . ' — ' . $product->error, 'error');
				$ko++;
			}
		}
		dsLog('─── ' . $ok . ' ' . $langs->trans('ResultSuccess') . ', ' . $ko . ' ' . $langs->trans('ResultErrors') . ' ───');

	// ════════════════════════════════════════════════════════════════════════
	} elseif ($script === 'generate-invoice') {
	// ════════════════════════════════════════════════════════════════════════
		$socids  = dolinstreamGetClientIds($db);
		$prodids = dolinstreamGetProductIds($db);

		if (empty($socids)) {
			dsLog('❌ ' . $langs->trans('NoClientThirdparty'), 'error');
			goto render;
		}
		if (empty($prodids)) {
			dsLog('❌ ' . $langs->trans('NoProduct'), 'error');
			goto render;
		}

		$dates = dolinstreamGetRandomDates();
		dsLog($langs->transnoentities('GenerateInvoices') . ' : ' . $nb . ' (' . count($socids) . ' tiers, ' . count($prodids) . ' produits)');
		$ok = $ko = 0;

		for ($i = 0; $i < $nb; $i++) {
			$obj                    = new Facture($db);
			$obj->socid             = $socids[array_rand($socids)];
			$obj->date              = $dates[array_rand($dates)];
			$obj->cond_reglement_id = 3;
			$obj->mode_reglement_id = 3;

			// Note : pas de $db->begin() manuel — Facture::create() et validate()
			// gèrent leurs propres transactions. Un wrapping externe crée un
			// snapshot REPEATABLE READ qui bloque le module de numérotation.
			$result = $obj->create($fuser);
			if ($result >= 0) {
				$nbLines = mt_rand(2, 5);
				$lineOk  = true;
				for ($l = 0; $l < $nbLines; $l++) {
					$pid     = $prodids[array_rand($prodids)];
					$product = new Product($db);
					$product->fetch($pid);
					$r = $obj->addline(
						$product->description,
						$product->price,
						mt_rand(1, 5),
						$product->tva_tx ?? 20,
						0, 0,
						$pid,
						0, '', '', 0, 0, '',
						$product->price_base_type,
						$product->price_ttc,
						$product->type
					);
					if ($r < 0) {
						$lineOk = false;
						break;
					}
				}
				$obj->fetch($obj->id);
				$obj->fetch_thirdparty();
				$obj->fetch_lines();
				if ($lineOk && $obj->validate($fuser) > 0) {
					$ht  = price2num($obj->total_ht, 'MT');
					$ttc = price2num($obj->total_ttc, 'MT');
					dsLog('✔ #' . $i . ' | ' . $obj->ref . ' | soc=' . $obj->socid . ' | ' . dol_print_date($obj->date, 'day') . ' | HT=' . $ht . ' | TTC=' . $ttc, 'success');
					$ok++;
				} else {
					dsLog('✘ #' . $i . ' — validation : ' . $obj->error, 'error');
					$ko++;
				}
			} else {
				dsLog('✘ #' . $i . ' — création : ' . $obj->error, 'error');
				$ko++;
			}
		}
		dsLog('─── ' . $ok . ' OK, ' . $ko . ' erreur(s) ───');

	// ════════════════════════════════════════════════════════════════════════
	} elseif ($script === 'generate-order') {
	// ════════════════════════════════════════════════════════════════════════
		$socids  = dolinstreamGetClientIds($db);
		$prodids = dolinstreamGetProductIds($db);

		if (empty($socids)) { dsLog('❌ ' . $langs->trans('NoClientThirdparty'), 'error'); goto render; }
		if (empty($prodids)) { dsLog('❌ ' . $langs->trans('NoProduct'), 'error'); goto render; }

		$dates = dolinstreamGetRandomDates();
		dsLog($langs->transnoentities('GenerateOrders') . ' : ' . $nb);
		$ok = $ko = 0;

		for ($s = 0; $s < $nb; $s++) {
			$obj                     = new Commande($db);
			$obj->socid              = $socids[array_rand($socids)];
			$obj->date_commande      = $dates[array_rand($dates)];
			$obj->note               = 'Généré par DoliStream';
			$obj->source             = 1;
			$obj->fk_project         = 0;
			$obj->remise_percent     = 0;
			$obj->shipping_method_id = mt_rand(1, 2);
			$obj->cond_reglement_id  = mt_rand(1, 3);
			$obj->availability_id    = mt_rand(0, 1);

			// Note : pas de $db->begin() manuel — Commande::create() et valid()
			// gèrent leurs propres transactions. Un wrapping externe crée un
			// snapshot REPEATABLE READ qui bloque le module de numérotation.
			$result = $obj->create($fuser);
			if ($result >= 0) {
				$nbLines = mt_rand(2, 5);
				$lineOk  = true;
				for ($l = 0; $l < $nbLines; $l++) {
					$pid     = $prodids[array_rand($prodids)];
					$product = new Product($db);
					$product->fetch($pid);
					$r = $obj->addline(
						$product->description,
						$product->price,
						mt_rand(1, 5),
						$product->tva_tx ?? 20,
						0, 0,
						$pid,
						0, 0, 0,
						$product->price_base_type,
						$product->price_ttc,
						'', '',
						$product->type
					);
					if ($r < 0) { $lineOk = false; break; }
				}
				if ($lineOk) {
					// ── Reproduire exactement ce que Facture::validate() fait ──────────
					// fetch() complet + fetch_thirdparty() + fetch_lines() AVANT valid()
					// pour que le module de numérotation ait un $soc et des lignes chargées
					$obj->fetch($obj->id);
					$obj->fetch_thirdparty();
					$obj->fetch_lines();
					// ─────────────────────────────────────────────────────────────────
					if ($obj->valid($fuser) > 0) {
						$obj->fetch($obj->id);
						$ht = price2num($obj->total_ht, 'MT');
						dsLog('✔ #' . $s . ' | ' . $obj->ref . ' | soc=' . $obj->socid . ' | ' . dol_print_date($obj->date_commande, 'day') . ' | HT=' . $ht, 'success');
						$ok++;
					} else {
						dsLog('✘ #' . $s . ' — ' . $obj->error, 'error');
						$ko++;
					}
				} else {
					dsLog('✘ #' . $s . ' — addline : ' . $obj->error, 'error');
					$ko++;
				}
			} else {
				dsLog('✘ #' . $s . ' — création : ' . $obj->error, 'error');
				$ko++;
			}
		}
		dsLog('─── ' . $ok . ' OK, ' . $ko . ' erreur(s) ───');

	// ════════════════════════════════════════════════════════════════════════
	} elseif ($script === 'generate-proposal') {
	// ════════════════════════════════════════════════════════════════════════
		$socids  = dolinstreamGetClientIds($db);
		$prodids = dolinstreamGetProductIds($db);

		if (empty($socids)) { dsLog('❌ ' . $langs->trans('NoClientThirdparty'), 'error'); goto render; }

		$dates = dolinstreamGetRandomDates();
		dsLog($langs->transnoentities('GenerateProposals') . ' : ' . $nb);
		$ok = $ko = 0;

		for ($s = 0; $s < $nb; $s++) {
			$obj                    = new Propal($db);
			$obj->socid             = $socids[array_rand($socids)];
			$obj->date              = $dates[array_rand($dates)];
			$obj->date_fin_validite = $obj->date + (30 * 24 * 3600);
			$obj->cond_reglement_id = 3;
			$obj->mode_reglement_id = 3;
			$obj->fk_project        = 0;

			// Note : pas de $db->begin() manuel — Propal::create() gère sa
			// propre transaction. Un wrapping externe perturbe le module de numérotation.
			$result = $obj->create($fuser);
			if ($result >= 0) {
				$nbLines = mt_rand(1, 4);
				for ($l = 0; $l < $nbLines; $l++) {
					if (empty($prodids)) break;
					$pid     = $prodids[array_rand($prodids)];
					$product = new Product($db);
					$product->fetch($pid);
					$obj->addline(
						$obj->id,
						$product->description,
						$product->price,
						$product->tva_tx ?? 20,
						0, 0,
						mt_rand(1, 5),
						$pid,
						'',
						$product->price_base_type,
						$product->price_ttc,
						0,
						$product->type
					);
				}
				// ── Même pattern que Facture::validate() et Commande::valid() ─────────
				// Recharge l'objet + tiers + lignes AVANT valid() pour que le module
				// de numérotation dispose d'un $soc complet (évite les refs PROV)
				$obj->fetch($obj->id);
				$obj->fetch_thirdparty();
				$obj->fetch_lines();
				if ($obj->valid($fuser) > 0) {
					$obj->fetch($obj->id);
					$ht = price2num($obj->total_ht, 'MT');
					dsLog('✔ #' . $s . ' | ' . $obj->ref . ' | soc=' . $obj->socid . ' | ' . dol_print_date($obj->date, 'day') . ' | HT=' . $ht, 'success');
					$ok++;
				} else {
					dsLog('✘ #' . $s . ' — valid() : ' . $obj->error, 'error');
					$ko++;
				}
			} else {
				dsLog('✘ #' . $s . ' — création : ' . $obj->error, 'error');
				$ko++;
			}
		}
		dsLog('─── ' . $ok . ' OK, ' . $ko . ' erreur(s) ───');

	// ════════════════════════════════════════════════════════════════════════
	} elseif ($script === 'generate-project') {
	// ════════════════════════════════════════════════════════════════════════
		// mode : 'free' = sans tiers | 'linked' = lié à un tiers aléatoire
		$projectMode = in_array($mode, array('free', 'linked')) ? $mode : 'free';
		$socids      = ($projectMode === 'linked') ? dolinstreamGetClientIds($db) : array();

		if ($projectMode === 'linked' && empty($socids)) {
			dsLog('❌ ' . $langs->trans('NoClientThirdparty') . ' (mode lié)', 'error');
			goto render;
		}

		// Statuts d'opportunité Dolibarr (table llx_c_lead_status, rowid standards)
		// 1=PROSP  2=QUAL  3=PROP  4=NEGO  5=WON  — on exclut WON/LOST pour du réaliste
		$oppStatuses = array(
			1 => array('code' => 'PROSP', 'label' => 'Prospection',   'pct' => mt_rand(10, 25)),
			2 => array('code' => 'QUAL',  'label' => 'Qualifié',       'pct' => mt_rand(25, 45)),
			3 => array('code' => 'PROP',  'label' => 'Proposition',    'pct' => mt_rand(40, 65)),
			4 => array('code' => 'NEGO',  'label' => 'Négociation',    'pct' => mt_rand(60, 85)),
			5 => array('code' => 'WON',   'label' => 'Gagné',          'pct' => 100),
		);

		$projectNames = array(
			'Refonte SI', 'Migration Cloud', 'Audit Sécurité', 'Développement App',
			'Intégration ERP', 'Formation Équipe', 'Déploiement Infrastructure',
			'Conseil Stratégique', 'Accompagnement Digital', 'Mise en conformité RGPD',
			'Optimisation Processus', 'Projet Innovation', 'Étude de Marché',
			'Implémentation CRM', 'Transformation Agile',
		);

		$dates = dolinstreamGetRandomDates();
		dsLog('Générer des projets/opportunités : ' . $nb . ' (mode=' . $projectMode . ')');
		$ok = $ko = 0;

		for ($s = 0; $s < $nb; $s++) {
			// Choisir un statut d'opportunité aléatoire
			$oppKey    = array_rand($oppStatuses);
			$oppStatus = $oppStatuses[$oppKey];

			$proj = new Project($db);

			// ── Obtenir la prochaine référence via le module Dolibarr configuré ────
			// Reproduit exactement ce que Project::createFromClone() fait.
			// Le module par défaut (mod_project_simple) génère : PJyymm-nnnn
			$projRef   = '';
			$addonName = getDolGlobalString('PROJECT_ADDON', 'mod_project_simple');
			$addonFile = DOL_DOCUMENT_ROOT . '/core/modules/project/' . $addonName . '.php';
			if (file_exists($addonFile)) {
				require_once $addonFile;
				$modProject = new $addonName();
				$proj->date_c   = $dates[array_rand($dates)]; // date de création fictive pour le yymm
				$projRef = $modProject->getNextValue(null, $proj);
			}
			if (!$projRef || is_numeric($projRef) && (int) $projRef <= 0) {
				dsLog('✘ #' . $s . ' — Impossible d\'obtenir une référence via ' . $addonName, 'error');
				$ko++;
				continue;
			}

			$proj->ref               = $projRef;
			$proj->title             = $projectNames[array_rand($projectNames)] . ' ' . ($s + 1);
			$proj->description       = 'Généré automatiquement par DoliStream (' . $oppStatus['label'] . ')';
			$proj->date_start        = $proj->date_c;
			$proj->date_end          = $proj->date_start + mt_rand(30, 365) * 24 * 3600;
			$proj->statut            = Project::STATUS_VALIDATED; // ouvert d'emblée
			$proj->usage_opportunity = 1;
			$proj->opp_status        = $oppKey;                  // rowid du statut
			$proj->opp_percent       = $oppStatus['pct'];
			$proj->opp_amount        = mt_rand(1000, 150000);    // montant aléatoire
			$proj->budget_amount     = $proj->opp_amount * (mt_rand(90, 110) / 100);
			$proj->public            = 1;
			$proj->fk_user_creat     = $fuser->id;

			// Lier à un tiers si mode 'linked'
			if ($projectMode === 'linked' && !empty($socids)) {
				$proj->socid = $socids[array_rand($socids)];
			}

			$result = $proj->create($fuser);
			if ($result > 0) {
				dsLog(
					'✔ #' . $s . ' | ' . $proj->ref . ' | ' . $proj->title
					. ' | ' . $oppStatus['label']
					. ' | ' . number_format((int)$proj->opp_amount, 0, ',', ' ') . ' €'
					. ' | ' . number_format((int)$proj->budget_amount, 0, ',', ' ') . ' €',
					'success'
				);
				$ok++;
			} else {
				dsLog('✘ #' . $s . ' — ' . $proj->error, 'error');
				$ko++;
			}
			// Progression toutes les 5 items ou au dernier
			if ($s % 5 === 0 || $s === $nb - 1) {
				dolinstreamProgress($s + 1, $nb);
			}
		}
		dolinstreamProgress($nb, $nb, true); // marque terminé
		dsLog('─── ' . $ok . ' OK, ' . $ko . ' erreur(s) ───');

	// ════════════════════════════════════════════════════════════════════════
	} elseif ($script === 'generate-expedition') {
	// ════════════════════════════════════════════════════════════════════════
		$orderIds = dolinstreamGetClientOrderIds($db);

		if (empty($orderIds)) {
			dsLog('❌ Aucune commande client validée trouvée. Générez des commandes client d\'abord.', 'error');
			goto render;
		}

		dsLog('Générer des expéditions : ' . $nb);
		$ok = $ko = 0;

		for ($s = 0; $s < $nb; $s++) {
			$orderId = $orderIds[array_rand($orderIds)];
			$order   = new Commande($db);
			if ($order->fetch($orderId) <= 0 || $order->fetch_lines() < 0) {
				dsLog('✘ #' . $s . ' — Impossible de charger la commande #' . $orderId, 'error');
				$ko++;
				continue;
			}

			$exp = new Expedition($db);
			$exp->socid     = $order->socid;
			$exp->origin    = 'commande';
			$exp->origin_id = $orderId;
			$exp->date_delivery = dol_now();
			$exp->note_private  = 'Généré par DoliStream';
			$exp->fk_project    = 0;

			$expid = $exp->create($fuser);
			if ($expid <= 0) {
				dsLog('✘ #' . $s . ' — create : ' . $exp->error, 'error');
				$ko++;
				continue;
			}

			// Ajouter les lignes depuis la commande
			foreach ($order->lines as $line) {
				if (empty($line->fk_product)) continue;
				// addline($entrepot_id, $id_order_line, $qty, $array_options, $fk_product)
				$exp->addline(0, $line->id, (float) $line->qty, array(), (int) $line->fk_product);
			}

			if ($exp->valid($fuser) > 0) {
				dsLog('✔ #' . $s . ' | ' . $exp->ref . ' | soc=' . $exp->socid . ' | ' . dol_print_date($exp->date_delivery, 'day'), 'success');
				$ok++;
			} else {
				dsLog('✘ #' . $s . ' — valid : ' . $exp->error, 'error');
				$ko++;
			}
		}
		dsLog('─── ' . $ok . ' OK, ' . $ko . ' erreur(s) ───');

	// ════════════════════════════════════════════════════════════════════════
	} elseif ($script === 'generate-supplier-order') {
	// ════════════════════════════════════════════════════════════════════════
		$supplierIds = dolinstreamGetSupplierIds($db);
		$prodIds     = dolinstreamGetBuyProductIds($db);

		if (empty($supplierIds)) {
			dsLog('❌ Aucun fournisseur trouvé. Générez des tiers de type Fournisseur d\'abord.', 'error');
			goto render;
		}

		$dates = dolinstreamGetRandomDates();
		dsLog('Générer des commandes fournisseur : ' . $nb);
		$ok = $ko = 0;

		for ($s = 0; $s < $nb; $s++) {
			$socid = $supplierIds[array_rand($supplierIds)];

			$obj = new CommandeFournisseur($db);
			$obj->socid         = $socid;
			$obj->date_commande = $dates[array_rand($dates)];
			$obj->note_private  = 'Généré par DoliStream';
			$obj->source        = 0;

			$result = $obj->create($fuser);
			if ($result <= 0) {
				dsLog('✘ #' . $s . ' — create : ' . $obj->error, 'error');
				$ko++;
				continue;
			}

			// Ajouter des lignes
			$nbLines = mt_rand(1, 4);
			for ($l = 0; $l < $nbLines; $l++) {
				if (empty($prodIds)) break;
				$pid     = $prodIds[array_rand($prodIds)];
				$product = new Product($db);
				$product->fetch($pid);
				$obj->addline(
					$socid,
					$product->description ?: $product->label,
					$product->price_min > 0 ? $product->price_min : $product->price * 0.7,
					$product->tva_tx ?? 20,
					0, 0,
					mt_rand(1, 10),
					$pid,
					'',
					$product->price_base_type,
					$product->price * 0.7,
					0,
					$product->type
				);
			}

			$obj->fetch($obj->id);
			$obj->fetch_thirdparty();
			$obj->fetch_lines();
			if ($obj->valid($fuser, 0) >= 0) {
				$obj->fetch($obj->id);
				$ht = price2num($obj->total_ht, 'MT');
				dsLog('✔ #' . $s . ' | ' . $obj->ref . ' | soc=' . $socid . ' | ' . dol_print_date($obj->date_commande, 'day') . ' | HT=' . $ht, 'success');
				$ok++;
			} else {
				dsLog('✘ #' . $s . ' — valid : ' . $obj->error, 'error');
				$ko++;
			}
		}
		dsLog('─── ' . $ok . ' OK, ' . $ko . ' erreur(s) ───');

	// ════════════════════════════════════════════════════════════════════════
	} elseif ($script === 'generate-reception') {
	// ════════════════════════════════════════════════════════════════════════
		if (!class_exists('Reception')) {
			dsLog('❌ Module Réception non disponible dans cette version de Dolibarr.', 'error');
			goto render;
		}

		$orderIds = dolinstreamGetSupplierOrderIds($db);

		if (empty($orderIds)) {
			dsLog('❌ Aucune commande fournisseur validée. Générez des commandes fournisseur d\'abord.', 'error');
			goto render;
		}

		dsLog('Générer des réceptions fournisseur : ' . $nb);
		$ok = $ko = 0;

		for ($s = 0; $s < $nb; $s++) {
			$orderId = $orderIds[array_rand($orderIds)];
			$order   = new CommandeFournisseur($db);
			if ($order->fetch($orderId) <= 0 || $order->fetch_lines() < 0) {
				dsLog('✘ #' . $s . ' — Commande fourn #' . $orderId . ' introuvable', 'error');
				$ko++;
				continue;
			}

			$rec = new Reception($db);
			$rec->socid     = $order->socid;
			$rec->origin    = 'order_supplier';
			$rec->origin_id = $orderId;
			$rec->date_reception = dol_now();
			$rec->note_private   = 'Généré par DoliStream';

			$recid = $rec->create($fuser);
			if ($recid <= 0) {
				dsLog('✘ #' . $s . ' — create : ' . $rec->error, 'error');
				$ko++;
				continue;
			}

			if ($rec->valid($fuser) >= 0) {
				dsLog('✔ #' . $s . ' | ' . $rec->ref . ' | soc=' . $rec->socid . ' | ' . dol_print_date($rec->date_reception, 'day'), 'success');
				$ok++;
			} else {
				dsLog('✘ #' . $s . ' — valid : ' . $rec->error, 'error');
				$ko++;
			}
		}
		dsLog('─── ' . $ok . ' OK, ' . $ko . ' erreur(s) ───');

	// ════════════════════════════════════════════════════════════════════════
	} elseif ($script === 'generate-supplier-invoice') {
	// ════════════════════════════════════════════════════════════════════════
		$supplierIds = dolinstreamGetSupplierIds($db);
		$prodIds     = dolinstreamGetBuyProductIds($db);

		if (empty($supplierIds)) {
			dsLog('❌ Aucun fournisseur trouvé. Générez des tiers de type Fournisseur d\'abord.', 'error');
			goto render;
		}

		$dates = dolinstreamGetRandomDates();
		dsLog('Générer des factures fournisseur : ' . $nb);
		$ok = $ko = 0;

		for ($s = 0; $s < $nb; $s++) {
			$socid = $supplierIds[array_rand($supplierIds)];

			$obj = new FactureFournisseur($db);
			$obj->socid        = $socid;
			$obj->date         = $dates[array_rand($dates)];
			$obj->ref_supplier = 'FOURN-' . dol_print_date(dol_now(), '%Y%m') . '-' . str_pad($s, 4, '0', STR_PAD_LEFT);
			$obj->note_private = 'Généré par DoliStream';
			$obj->type         = FactureFournisseur::TYPE_STANDARD;

			$result = $obj->create($fuser);
			if ($result <= 0) {
				dsLog('✘ #' . $s . ' — create : ' . $obj->error, 'error');
				$ko++;
				continue;
			}

			$nbLines = mt_rand(1, 4);
			for ($l = 0; $l < $nbLines; $l++) {
				if (empty($prodIds)) break;
				$pid     = $prodIds[array_rand($prodIds)];
				$product = new Product($db);
				$product->fetch($pid);
				$unitPrice = $product->price_min > 0 ? $product->price_min : $product->price * 0.7;
				$obj->addline(
					$product->description ?: $product->label,
					$unitPrice,
					mt_rand(1, 10),
					$product->tva_tx ?? 20,
					0, 0, 0,
					$pid,
					'',
					$product->price_base_type,
					$product->price * 0.7,
					$product->type
				);
			}

			$obj->fetch($obj->id);
			$obj->fetch_thirdparty();
			$obj->fetch_lines();
			if ($obj->validate($fuser) >= 0) {
				$obj->fetch($obj->id);
				$ht  = price2num($obj->total_ht, 'MT');
				$ttc = price2num($obj->total_ttc, 'MT');
				dsLog('✔ #' . $s . ' | ' . $obj->ref . ' | soc=' . $socid . ' | ' . dol_print_date($obj->date, 'day') . ' | HT=' . $ht . ' | TTC=' . $ttc, 'success');
				$ok++;
			} else {
				dsLog('✘ #' . $s . ' — validate : ' . $obj->error, 'error');
				$ko++;
			}
		}
		dsLog('─── ' . $ok . ' OK, ' . $ko . ' erreur(s) ───');

	// ════════════════════════════════════════════════════════════════════════
	} elseif ($script === 'purge-data') {
	// ════════════════════════════════════════════════════════════════════════
		if (!$user->admin && !$user->hasRight('dolistream', 'purge', 'run')) {
			accessforbidden();
		}
		if (!in_array($mode, array('test', 'confirm'))) {
			dsLog('❌ Mode invalide. Utilisez "test" ou "confirm".', 'error');
			goto render;
		}

		$cutoff = ($date === 'all' || empty($date)) ? '2199-01-01' : $date;
		if ($date !== 'all' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoff)) {
			dsLog('❌ Date invalide. Format attendu : YYYY-MM-DD ou "all".', 'error');
			goto render;
		}

		// Table des DELETE par famille
		$families = array(
			'user'     => array("DELETE FROM " . MAIN_DB_PREFIX . "user WHERE admin=0 AND login!='admin' AND datec<'__DATE__'"),
			'event'    => array("DELETE FROM " . MAIN_DB_PREFIX . "actioncomm WHERE datec<'__DATE__'"),
			'payment'  => array(
				"DELETE FROM " . MAIN_DB_PREFIX . "paiement_facture WHERE fk_facture IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "facture WHERE datec<'__DATE__')",
				"DELETE FROM " . MAIN_DB_PREFIX . "paiement WHERE rowid NOT IN (SELECT fk_paiement FROM " . MAIN_DB_PREFIX . "paiement_facture)",
			),
			'invoice'  => array(
				'@payment',
				"DELETE FROM " . MAIN_DB_PREFIX . "facturedet WHERE fk_facture IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "facture WHERE datec<'__DATE__')",
				"DELETE FROM " . MAIN_DB_PREFIX . "facture WHERE datec<'__DATE__'",
			),
			'proposal' => array(
				"DELETE FROM " . MAIN_DB_PREFIX . "propaldet WHERE fk_propal IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "propal WHERE datec<'__DATE__')",
				"DELETE FROM " . MAIN_DB_PREFIX . "propal WHERE datec<'__DATE__'",
			),
			'order'    => array(
				"DELETE FROM " . MAIN_DB_PREFIX . "commandedet WHERE fk_commande IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "commande WHERE date_creation<'__DATE__')",
				"DELETE FROM " . MAIN_DB_PREFIX . "commande WHERE date_creation<'__DATE__'",
			),
			'product'  => array(
				"DELETE FROM " . MAIN_DB_PREFIX . "product_price WHERE fk_product IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "product WHERE datec<'__DATE__')",
				"DELETE FROM " . MAIN_DB_PREFIX . "product WHERE datec<'__DATE__'",
			),
			'contact'  => array(
				"DELETE FROM " . MAIN_DB_PREFIX . "socpeople WHERE datec<'__DATE__'",
			),
			'thirdparty' => array(
				'@contact',
				"DELETE FROM " . MAIN_DB_PREFIX . "societe WHERE datec<'__DATE__'",
			),
			'project'  => array(
				"DELETE FROM " . MAIN_DB_PREFIX . "projet_task WHERE fk_projet IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "projet WHERE datec<'__DATE__')",
				"DELETE FROM " . MAIN_DB_PREFIX . "projet WHERE datec<'__DATE__'",
			),
			'bank'     => array(
				"DELETE FROM " . MAIN_DB_PREFIX . "bank WHERE datec<'__DATE__'",
			),
		);

		$toProcess = ($opt === 'all') ? array_keys($families) : array($opt);
		$processed = array();

		// Fonction récursive pour gérer les dépendances (@famille)
		$runFamily = function (string $fam) use (&$runFamily, $families, $cutoff, $mode, &$processed, $db, $langs): void {
			if (in_array($fam, $processed)) return;
			$processed[] = $fam;
			if (!isset($families[$fam])) {
				dsLog('⚠ Famille inconnue : ' . $fam, 'warn');
				return;
			}
			dsLog('── Famille : ' . $fam . ' ──');
			foreach ($families[$fam] as $sql) {
				if (preg_match('/^@(.+)$/', $sql, $m)) {
					$runFamily($m[1]);
					continue;
				}
				$sql = str_replace('__DATE__', $cutoff, $sql);
				if ($mode === 'test') {
					dsLog('[SIMULATION] ' . $sql, 'warn');
					dsLog('  → (mode test : rien ne sera supprimé)', 'warn');
				} else {
					dsLog($sql, 'info');
					$r = $db->query($sql);
					if (!$r) {
						dsLog('  ✘ Erreur SQL : ' . $db->lasterror(), 'error');
					} else {
						dsLog('  ✔ ' . $db->affected_rows($r) . ' ligne(s) supprimée(s)', 'success');
					}
				}
			}
		};

		if ($mode === 'confirm') $db->begin();
		foreach ($toProcess as $fam) $runFamily(trim($fam));
		if ($mode === 'confirm') {
			// Vérifier s'il y a des erreurs dans le log
			$hasError = count(array_filter($scriptLog, static fn($l) => $l['level'] === 'error')) > 0;
			if ($hasError) {
				$db->rollback();
				dsLog('✘ Erreurs détectées — ROLLBACK effectué', 'error');
			} else {
				$db->commit();
				dsLog('✔ Transaction validée (COMMIT)', 'success');
			}
		} else {
			dsLog('ℹ Mode test terminé — aucune donnée modifiée.', 'warn');
		}
	}
}

// ═══════════════════════════════════════════════════════════════════════════════
// RENDER
// ═══════════════════════════════════════════════════════════════════════════════
render:

// ── Stats base de données ────────────────────────────────────────────────────
$statsMap = array(
	'thirdparties' => array('icon' => '🏢', 'label' => $langs->trans('StatsThirdparties'), 'sql' => 'SELECT COUNT(*) FROM ' . MAIN_DB_PREFIX . 'societe'),
	'products'     => array('icon' => '🏷️', 'label' => $langs->trans('StatsProducts'),     'sql' => 'SELECT COUNT(*) FROM ' . MAIN_DB_PREFIX . 'product'),
	'invoices'     => array('icon' => '🧾', 'label' => $langs->trans('StatsInvoices'),      'sql' => 'SELECT COUNT(*) FROM ' . MAIN_DB_PREFIX . 'facture'),
	'orders'       => array('icon' => '📦', 'label' => $langs->trans('StatsOrders'),        'sql' => 'SELECT COUNT(*) FROM ' . MAIN_DB_PREFIX . 'commande'),
	'proposals'    => array('icon' => '📋', 'label' => $langs->trans('StatsProposals'),     'sql' => 'SELECT COUNT(*) FROM ' . MAIN_DB_PREFIX . 'propal'),
	'projects'     => array('icon' => '🎯', 'label' => 'Projets/Opportunités',              'sql' => 'SELECT COUNT(*) FROM ' . MAIN_DB_PREFIX . 'projet'),
);
$stats = array();
foreach ($statsMap as $key => $info) {
	$r           = $db->query($info['sql']);
	$stats[$key] = array_merge($info, array('count' => $r ? (int) $db->fetch_row($r)[0] : 0));
}

// ── Script actif ─────────────────────────────────────────────────────────────
$activeScript = $script ?: 'generate-thirdparty';

// ── Définitions des formulaires avec colonnes de résultat ─────────────────────
$scriptDefs = array(
	'generate-thirdparty' => array(
		'label'   => $langs->trans('GenerateThirdparties'),
		'icon'    => 'company',
		'hint'    => $langs->trans('HintThirdparty'),
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Nom', 'Type', 'Code client', 'Code fourn.'),
		'fields'  => array(
			array('name' => 'nb', 'label' => $langs->trans('NumberToGenerate'), 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 10000),
			array(
				'name'    => 'opt',
				'label'   => 'Type de tiers',
				'type'    => 'select',
				'options' => array(
					'random'   => 'Aléatoire (mélange de tous les types)',
					'prospect' => 'Prospect',
					'client'   => 'Client',
					'supplier' => 'Fournisseur',
					'both'     => 'Client & Fournisseur',
				),
				'default' => 'random',
			),
		),
	),
	'generate-product' => array(
		'label'   => $langs->trans('GenerateProducts'),
		'icon'    => 'product',
		'hint'    => $langs->trans('HintProduct'),
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf.', 'Type', 'Prix HT'),
		'fields'  => array(
			array('name' => 'nb', 'label' => $langs->trans('NumberToGenerate'), 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 10000),
		),
	),
	'generate-invoice' => array(
		'label'   => $langs->trans('GenerateInvoices'),
		'icon'    => 'bill',
		'hint'    => $langs->trans('HintInvoice'),
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf.', 'Date', 'Tiers', 'Montant HT', 'Montant TTC'),
		'fields'  => array(
			array('name' => 'nb', 'label' => $langs->trans('NumberToGenerate'), 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 5000),
		),
	),
	'generate-order' => array(
		'label'   => $langs->trans('GenerateOrders'),
		'icon'    => 'order',
		'hint'    => $langs->trans('HintOrder'),
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf.', 'Date', 'Tiers', 'Montant HT'),
		'fields'  => array(
			array('name' => 'nb', 'label' => $langs->trans('NumberToGenerate'), 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 5000),
		),
	),
	'generate-proposal' => array(
		'label'   => $langs->trans('GenerateProposals'),
		'icon'    => 'propal',
		'hint'    => $langs->trans('HintProposal'),
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf.', 'Date', 'Tiers', 'Montant HT'),
		'fields'  => array(
			array('name' => 'nb', 'label' => $langs->trans('NumberToGenerate'), 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 5000),
		),
	),
	'generate-project' => array(
		'label'   => 'Générer des Projets / Opportunités',
		'icon'    => 'project',
		'hint'    => 'Crée des projets avec suivi d\'opportunité (statut aléatoire, montant aléatoire).',
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf.', 'Titre', 'Statut opp.', 'Montant opp.', 'Budget'),
		'fields'  => array(
			array('name' => 'nb', 'label' => $langs->trans('NumberToGenerate'), 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 2000),
			array(
				'name'    => 'mode',
				'label'   => 'Mode de liaison tiers',
				'type'    => 'select',
				'options' => array(
					'free'   => 'Projet libre (sans tiers)',
					'linked' => 'Lié à un tiers aléatoire',
				),
				'default' => 'free',
			),
		),
	),
	'purge-data' => array(
		'label'   => $langs->trans('PurgeData'),
		'icon'    => 'delete',
		'hint'    => $langs->trans('HintPurge'),
		'danger'  => true,
		'perm'    => 'purge',
		'columns' => array('Opération', 'Statut', 'Détail'),
		'fields'  => array(
			array(
				'name'    => 'mode',
				'label'   => $langs->trans('ExecutionMode'),
				'type'    => 'select',
				'options' => array(
					'test'    => $langs->trans('TestMode'),
					'confirm' => $langs->trans('ConfirmMode'),
				),
				'default' => 'test',
			),
			array(
				'name'    => 'opt',
				'label'   => $langs->trans('CategoryToPurge'),
				'type'    => 'select',
				'options' => array(
					'all'        => $langs->trans('AllCategories'),
					'invoice'    => 'invoice', 'order' => 'order', 'proposal' => 'proposal',
					'product'    => 'product', 'contact' => 'contact', 'thirdparty' => 'thirdparty',
					'payment'    => 'payment', 'project' => 'project', 'bank' => 'bank',
					'event'      => 'event',   'user'    => 'user',
				),
				'default' => 'all',
			),
			array('name' => 'date', 'label' => $langs->trans('BeforeDate'), 'type' => 'text', 'default' => 'all', 'placeholder' => 'all  ou  2024-01-01'),
		),
	),
	// ── Nouveaux scripts ──────────────────────────────────────────────────────
	'generate-expedition' => array(
		'label'   => 'Générer des Expéditions',
		'icon'    => 'shipment',
		'hint'    => 'Crée des expéditions depuis les commandes client validées existantes.',
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf.', 'Tiers', 'Date livraison'),
		'fields'  => array(
			array('name' => 'nb', 'label' => $langs->trans('NumberToGenerate'), 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 500),
		),
	),
	'generate-supplier-order' => array(
		'label'   => 'Générer des Commandes fournisseur',
		'icon'    => 'supplier_order',
		'hint'    => 'Crée des commandes fournisseur validées avec des tiers fournisseurs et des produits aléatoires.',
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf.', 'Date', 'Fournisseur', 'Montant HT'),
		'fields'  => array(
			array('name' => 'nb', 'label' => $langs->trans('NumberToGenerate'), 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 2000),
		),
	),
	'generate-reception' => array(
		'label'   => 'Générer des Réceptions fournisseur',
		'icon'    => 'reception',
		'hint'    => 'Crée des réceptions depuis les commandes fournisseur validées existantes.',
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf.', 'Fournisseur', 'Date réception'),
		'fields'  => array(
			array('name' => 'nb', 'label' => $langs->trans('NumberToGenerate'), 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 500),
		),
	),
	'generate-supplier-invoice' => array(
		'label'   => 'Générer des Factures fournisseur',
		'icon'    => 'supplier_invoice',
		'hint'    => 'Crée des factures fournisseur validées avec des tiers fournisseurs et des produits aléatoires.',
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf.', 'Date', 'Fournisseur', 'Montant HT', 'Montant TTC'),
		'fields'  => array(
			array('name' => 'nb', 'label' => $langs->trans('NumberToGenerate'), 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 2000),
		),
	),
);

$def = $scriptDefs[$activeScript] ?? null;

// ── Parsing du log en lignes structurées pour le tableau ──
$structLog = array();

// Fonction locale : résout un socid en nom de tiers
$socCache = array();
$resolveSoc = static function (int $id) use ($db, &$socCache): string {
    if (isset($socCache[$id])) return $socCache[$id];
    $r = $db->query('SELECT nom FROM ' . MAIN_DB_PREFIX . 'societe WHERE rowid=' . $id);
    $socCache[$id] = ($r && ($o = $db->fetch_object($r))) ? $o->nom : '#' . $id;
    return $socCache[$id];
};

foreach ($scriptLog as $entry) {
    $msg  = $entry['msg'];
    $lvl  = $entry['level'];
    $cells = array();

    if ($activeScript === 'generate-thirdparty') {
        // ✓ #N - Company xxx [Type] [cli=CODE, fourn=CODE]
        if (preg_match('/- (.+?) \[(.+?)\](?:\s*\[cli=(.+?),\s*fourn=(.+?)\])?/', $msg, $m)) {
            $cells = array($m[1], $m[2], $m[3] ?? '-', $m[4] ?? '-');
        }

    } elseif ($activeScript === 'generate-product') {
        // ✓ #N - REF (PRICE)
        if (preg_match('/- (\S+)\s+\(([\d.]+)/', $msg, $m)) {
            $isService = (strpos($m[1], 'SRV') !== false);
            $cells = array($m[1], $isService ? 'Service' : 'Produit', $m[2] . ' €');
        }

    } elseif (in_array($activeScript, array('generate-invoice', 'generate-order', 'generate-proposal'))) {
        // ✓ #N | REF | soc=SOCID | DATE | HT=X [| TTC=Y]
        if (preg_match('/\| (\S+) \| soc=(\d+) \| ([\d\/]+)(?: \| HT=([\d.]+))?(?: \| TTC=([\d.]+))?/', $msg, $m)) {
            $soc = $resolveSoc((int)$m[2]);
            if ($activeScript === 'generate-invoice') {
                $cells = array($m[1], $m[3], $soc, number_format((float)($m[4]??0), 2, ',', ' ') . ' €', number_format((float)($m[5]??0), 2, ',', ' ') . ' €');
            } else {
                $cells = array($m[1], $m[3], $soc, number_format((float)($m[4]??0), 2, ',', ' ') . ' €');
            }
        }

    } elseif ($activeScript === 'generate-project') {
        // ✓ #N | REF | TITRE | STATUT | MONTANT € | BUDGET €
        if (preg_match('/\| (\S+) \| (.+?) \| (.+?) \| ([\d\s]+)\s*€?\s*\| ([\d\s]+)/', $msg, $m)) {
            $cells = array($m[1], trim($m[2]), trim($m[3]), trim($m[4]) . ' €', trim($m[5]) . ' €');
        }

    } elseif (in_array($activeScript, array('generate-expedition', 'generate-reception'))) {
        // ✓ #N | REF | soc=SOCID | DATE
        if (preg_match('/\| (\S+) \| soc=(\d+) \| (.+)$/', $msg, $m)) {
            $soc = $resolveSoc((int)$m[2]);
            $cells = array($m[1], $soc, trim($m[3]));
        }

    } elseif ($activeScript === 'generate-supplier-order') {
        // ✓ #N | REF | soc=SOCID | DATE | HT=X
        if (preg_match('/\| (\S+) \| soc=(\d+) \| ([\d\/]+)(?: \| HT=([\d.]+))?/', $msg, $m)) {
            $soc = $resolveSoc((int)$m[2]);
            $cells = array($m[1], $m[3], $soc, number_format((float)($m[4]??0), 2, ',', ' ') . ' €');
        }

    } elseif ($activeScript === 'generate-supplier-invoice') {
        // ✓ #N | REF | soc=SOCID | DATE | HT=X | TTC=Y
        if (preg_match('/\| (\S+) \| soc=(\d+) \| ([\d\/]+)(?: \| HT=([\d.]+))?(?: \| TTC=([\d.]+))?/', $msg, $m)) {
            $soc = $resolveSoc((int)$m[2]);
            $cells = array($m[1], $m[3], $soc, number_format((float)($m[4]??0), 2, ',', ' ') . ' €', number_format((float)($m[5]??0), 2, ',', ' ') . ' €');
        }

    } elseif ($activeScript === 'purge-data') {
        // Lignes libres : on affiche tout ce qui est success/error/warn
        if ($lvl !== 'info' && !empty($msg) && !preg_match('/^[\x{2550}\x{2554}\x{2557}]/u', $msg)) {
            $icon = $lvl === 'success' ? '✓' : ($lvl === 'error' ? '✗' : '!');
            $cells = array($icon, $lvl, $msg);
        }
    } else {
        if ($lvl !== 'info' && !empty($msg)) {
            $cells = array($lvl, $msg);
        }
    }

    if (!empty($cells)) {
        $structLog[] = array('level' => $lvl, 'cells' => $cells);
    } elseif ($lvl !== 'info' && !empty($msg) && !preg_match('/^[✓═╔╗]/u', $msg)) {
        $structLog[] = array('level' => $lvl, 'cells' => array($msg));
    }
}
// ── En-tête Dolibarr avec menu gauche natif ───────────────────────────────────
$leftMenuKey = 'dolistream_' . str_replace('-', '_', $activeScript);

llxHeader('', 'DoliStream', '', '', 0, 0, '', '', '', 'mod-dolistream page-index', '', '', 'dolistream', $leftMenuKey);
?>
<style>
.ds-warning-banner {
	background: #fff3cd;
	border: 1px solid #ffc107;
	border-left: 4px solid #993013;
	padding: 8px 14px;
	font-size: 0.88em;
	color: #5a3e0a;
	margin-bottom: 12px;
	display: flex;
	align-items: center;
	gap: 8px;
	border-radius: 2px;
}
.ds-stats {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	margin-bottom: 12px;
	align-items: center;
}
.ds-stat-chip {
	background: var(--colorbacktabcard1, #fff);
	border: 1px solid var(--inputbordercolor, rgba(0,0,0,.15));
	border-radius: 20px;
	padding: 4px 10px;
	font-size: 0.82em;
	display: flex;
	align-items: center;
	gap: 4px;
}
.ds-stat-chip strong { color: var(--colorbackhmenu1, rgb(90,50,120)); }
.ds-hint {
	background: #eef3fb;
	border: 1px solid #c5d8f5;
	padding: 7px 12px;
	font-size: 0.85em;
	color: #1a3a6e;
	border-radius: 2px;
	margin-bottom: 10px;
}
.ds-danger-banner {
	background: #f8d7da;
	border: 1px solid #f5c2c7;
	padding: 7px 12px;
	font-size: 0.85em;
	color: #842029;
	font-weight: 600;
	border-radius: 2px;
	margin-bottom: 10px;
}
/* ── Spinner inline (CSS pur, aucun JS pour l'animation) ── */
#ds-ring {
	visibility: hidden;
	display: flex;
	align-items: center;
	flex-shrink: 0;
}
.ds-ring-circle {
	width: 26px;
	height: 26px;
	border: 3px solid #e5dff0;
	border-top-color: rgb(90,50,120);
	border-radius: 50%;
	animation: ds-spin 0.75s linear infinite;
	animation-play-state: paused;
}
@keyframes ds-spin {
	to { transform: rotate(360deg); }
}
</style>

<div class="fiche">
<?php
$head = array(array(dol_buildpath('/custom/dolistream/view/index.php', 1) . '?script=' . urlencode($activeScript), ($def ? $def['label'] : 'DoliStream'), 'index'));
print dol_get_fiche_head($head, 'index', 'DoliStream', -1, 'technic');
?>



<!-- Bandeau avertissement dev -->
<div class="ds-warning-banner">⚠ <strong><?php print $langs->transnoentities('DoliStreamWarningDevOnly'); ?></strong></div>

<!-- Stats -->
<div class="ds-stats">
<?php foreach ($stats as $s): ?>
	<div class="ds-stat-chip"><?php print $s['icon']; ?> <strong><?php print $s['count']; ?></strong> <?php print $s['label']; ?></div>
<?php endforeach; ?>
	<a href="<?php print $_SERVER['PHP_SELF']; ?>?script=<?php print urlencode($activeScript); ?>" style="margin-left:auto;font-size:0.82em;color:var(--colorbackhmenu1,rgb(90,50,120))">↺ <?php print $langs->trans('RefreshStats'); ?></a>
</div>

<?php if ($def): ?>

<?php if ($def['danger']): ?>
<div class="ds-danger-banner">⚠ <?php print $langs->trans('DangerPurge'); ?></div>
<?php endif; ?>

<div class="ds-hint">ℹ <?php print $def['hint']; ?></div>

<!-- Formulaire 1 ligne -->
<form method="POST" action="<?php print $_SERVER['PHP_SELF']; ?>" style="margin-top:12px;">
<input type="hidden" name="action" value="run">
<input type="hidden" name="script" value="<?php print htmlspecialchars($activeScript); ?>">
<input type="hidden" name="token" value="<?php print newToken(); ?>">
<input type="hidden" name="token_check" value="1">
<div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
<?php foreach ($def['fields'] as $field): ?>
  <label style="display:flex;align-items:center;gap:6px;white-space:nowrap;">
    <span style="font-weight:600;"><?php print $field['label']; ?> <span class="error">*</span></span>
    <?php if ($field['type'] === 'select'): ?>
      <select name="<?php print $field['name']; ?>" class="flat">
      <?php
        $selectedVal = ($field['name'] === 'opt' && !empty($urlOpt)) ? $urlOpt : ($field['default'] ?? '');
        foreach ($field['options'] as $v => $l):
      ?>
        <option value="<?php print htmlspecialchars($v); ?>" <?php print ($selectedVal === $v ? 'selected' : ''); ?>><?php print htmlspecialchars($l); ?></option>
<?php endforeach; ?>
      </select>
    <?php elseif ($field['type'] === 'number'): ?>
      <input type="number" name="<?php print $field['name']; ?>" class="flat" style="width:70px;"
        value="<?php print (int)($field['default'] ?? 10); ?>"
        min="<?php print $field['min'] ?? 1; ?>" max="<?php print $field['max'] ?? 100000; ?>" required>
    <?php else: ?>
      <input type="text" name="<?php print $field['name']; ?>" class="flat minwidth200"
        value="<?php print htmlspecialchars($field['default'] ?? ''); ?>"
        placeholder="<?php print htmlspecialchars($field['placeholder'] ?? ''); ?>">
    <?php endif; ?>
  </label>
<?php endforeach; ?>
  <!-- Ring + boutons sur la même ligne -->
  <div style="display:inline-flex;align-items:center;gap:8px;">
    <!-- Ring: visibility:hidden par défaut = réserve l'espace, 0 décalage -->
    <div id="ds-ring" style="visibility:hidden;">
      <div class="ds-ring-circle" id="ds-ring-circle">

      </div>
    </div>
    <?php if ($def['danger']): ?>
      <button type="submit" class="butActionDelete"
        onclick="return confirm('Continuer ?')">&#128163; <?php print $langs->trans('RunScript'); ?></button>
    <?php else: ?>
      <button type="submit" class="butAction">&#9654; <?php print $langs->trans('RunScript'); ?></button>
    <?php endif; ?>
    <a href="?script=<?php print htmlspecialchars($activeScript); ?>" class="butActionRefused"><?php print $langs->trans('Cancel'); ?></a>
  </div>
</div>
</form>


<?php if (!empty($scriptLog)):
	$okCount  = count(array_filter($scriptLog, static fn($l) => $l['level'] === 'success'));
	$errCount = count(array_filter($scriptLog, static fn($l) => $l['level'] === 'error'));
	$cols     = $def['columns'] ?? array();
?>

<!-- Résultats en liste Dolibarr -->
<?php
$resLimit   = 25;
$resOffset  = $page * $resLimit;
$pagedLog   = array_slice($structLog, $resOffset, $resLimit);
$totalRows  = count($structLog);
$pageParam  = 'script=' . urlencode($activeScript);


print_barre_liste(
	$langs->trans('ExecutionResult'),
	$page, $_SERVER['PHP_SELF'], $pageParam,
	'', '',
	'<span class="badge badge-status4 badge-status">' . $okCount . ' OK</span>'
	. ($errCount > 0 ? '&nbsp;<span class="badge badge-status8 badge-status">' . $errCount . ' Erreur(s)</span>' : ''),
	count($pagedLog), $totalRows, '', 0, '', '', $resLimit
);
?>

<table class="noborder centpercent">
<thead>
<tr class="liste_titre">
	<th style="width:30px;"></th>
	<?php foreach ($cols as $col): ?><th><?php print $col; ?></th><?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php
$nbCols = count($cols);
foreach ($pagedLog as $row):
	$badge = match($row['level']) {
		'success' => '<span class="badge badge-status4 badge-status">✔</span>',
		'error'   => '<span class="badge badge-status8 badge-status">✘</span>',
		'warn'    => '<span class="badge badge-status1 badge-status">!</span>',
		default   => '<span class="badge badge-status0 badge-status">ℹ</span>',
	};
	$nCells = count($row['cells']);
?>
<tr class="oddeven">
	<td><?php print $badge; ?></td>
	<?php if ($nCells === 1): ?>
		<td colspan="<?php print max(1, $nbCols); ?>"><?php print htmlspecialchars($row['cells'][0]); ?></td>
	<?php else: ?>
		<?php foreach ($row['cells'] as $cell): ?><td><?php print htmlspecialchars((string)$cell); ?></td><?php endforeach; ?>
		<?php for ($i = $nCells; $i < $nbCols; $i++): ?><td></td><?php endfor; ?>
	<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php endif; ?>

<?php else: ?>
<div class="info">Sélectionnez un script dans le menu de gauche.</div>
<?php endif; ?>

</div><!-- /fiche -->

<script>
(function () {
  var ring   = document.getElementById('ds-ring');
  var circle = document.getElementById('ds-ring-circle');
  if (!ring || !circle) return;

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form) return;
    var nbInput = form.querySelector('[name="nb"]');
    var nb = nbInput ? parseInt(nbInput.value, 10) : Infinity;
    if (nb < 1) return; // nb=Infinity si pas de champ nb (purge-data) => toujours visible
    // Affiche le spinner CSS (tourne tout seul grâce à @keyframes)
    ring.style.visibility = 'visible';
    circle.style.animationPlayState = 'running';
  });
})();
</script>

<?php
llxFooter();
$db->close();
