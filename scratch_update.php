<?php
$file = "C:/wamp64/www/dolirent/dolibarr/htdocs/custom/dolistream/view/index.php";
$content = file_get_contents($file);

// 1. Add to scriptDefs
$scriptDefsRental = "
	'generate-rental-product' => array(
		'label'   => 'Générer Produits Loc',
		'icon'    => 'product',
		'hint'    => 'Génère des produits configurés pour la location avec un ratio paramétrable de prix locatif par rapport au prix de vente.',
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf', 'Titre', 'Prix Vente', 'Prix Loc/J'),
		'fields'  => array(
			array('name' => 'nb', 'label' => 'Nombre à générer', 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 5000),
			array('name' => 'rental_ratio', 'label' => 'Ratio Prix Loc/Vente (%)', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 100),
		),
	),
	'generate-rental-project' => array(
		'label'   => 'Générer Projets LLD',
		'icon'    => 'project',
		'hint'    => 'Génère des projets pré-configurés comme Location Longue Durée (LLD).',
		'danger'  => false,
		'perm'    => 'generate',
		'columns' => array('Réf', 'Titre', 'Statut', 'Montant', 'Budget'),
		'fields'  => array(
			array('name' => 'nb', 'label' => 'Nombre à générer', 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 2000),
			array(
				'name'    => 'mode',
				'label'   => 'Mode (Lié/Libre)',
				'type'    => 'select',
				'options' => array(
					'free'   => 'Projet libre (sans tiers)',
					'linked' => 'Lié à un tiers aléatoire',
				),
				'default' => 'free',
			),
		),
	),
	'purge-data' => array(";

$content = str_replace("'purge-data' => array(", $scriptDefsRental, $content);

// 2. Add execution logic for generate-rental-product
$rentalProductLogic = "} elseif (\$script === 'generate-rental-product') {
		\$rentalRatio = max(1, (int)(GETPOST('rental_ratio', 'int') ?: 5));
		\$_refDateBase = date('ym');
		\$_lastRefNum = 0;
		\$_rRes = \$db->query('SELECT MAX(rowid) AS m FROM ' . MAIN_DB_PREFIX . 'product');
		if (\$_rRes && (\$_rRow = \$db->fetch_object(\$_rRes))) \$_lastRefNum = (int)\$_rRow->m;
		\$_nextFallbackNum = \$_lastRefNum + 1;
		\$ok = \$ko = 0;
		for (\$s = 0; \$s < \$nb; \$s++) {
			\$product = new Product(\$db);
			\$product->type = 0;
			\$product->status = 1;
			\$product->status_buy = 1;
			\$product->finished = 0;
			\$product->stockable_product = 1;
			\$product->description = 'Généré automatiquement par DoliStream (Location).';
			\$sellPrice = round(mt_rand(50000, 999900) / 100, 2);
			\$costPrice = round(\$sellPrice * 0.6, 2);
			\$product->price = \$sellPrice;
			\$product->cost_price = \$costPrice;
			\$product->tva_tx = '20.000';
			\$product->ref = 'PRDL-' . \$_refDateBase . '-' . sprintf('%05d', \$_nextFallbackNum);
			\$product->label = 'Produit LLD ' . date('ymd-His') . '-' . sprintf('%04d', \$s);
			\$product->array_options = array(
				'options_rental_product' => 1,
				'options_rental_label' => \$product->label . '-location',
				'options_rental_price' => round(\$sellPrice * (\$rentalRatio / 100), 2),
				'options_rental_costprice' => round(\$costPrice * (\$rentalRatio / 100), 2),
			);
			\$ret = \$product->create(\$fuser);
			if (\$ret < 0) {
				dsLog('? #' . (\$s + 1) . ' — ' . \$product->error, 'error');
				\$ko++;
				continue;
			}
			\$_nextFallbackNum++;
			dsLog('? #' . (\$s + 1) . ' | id=' . \$product->id . ' | ' . \$product->ref . ' | ' . \$product->label . ' | ' . \$product->price . ' € | ' . \$product->array_options['options_rental_price'] . ' €/j', 'success');
			\$ok++;
		}
		dsLog('- ' . \$ok . ' OK, ' . \$ko . ' erreur(s) -');
	} elseif (\$script === 'generate-invoice') {";

$content = str_replace("} elseif (\$script === 'generate-invoice') {", $rentalProductLogic, $content);

// 3. Add execution logic for generate-rental-project
$rentalProjectLogic = "} elseif (\$script === 'generate-rental-project') {
		\$projectMode = in_array(\$mode, array('free', 'linked')) ? \$mode : 'free';
		\$socids      = (\$projectMode === 'linked') ? dolinstreamGetClientIds(\$db) : array();
		if (\$projectMode === 'linked' && empty(\$socids)) {
			dsLog('? ' . \$langs->transnoentitiesnoconv('NoClientThirdparty') . ' (mode lié)', 'error');
			goto render;
		}
		\$oppStatuses = array(
			1 => array('code' => 'PROSP', 'label' => 'Prospection',   'pct' => mt_rand(10, 25)),
			2 => array('code' => 'QUAL',  'label' => 'Qualifié',       'pct' => mt_rand(25, 45)),
			3 => array('code' => 'PROP',  'label' => 'Proposition',    'pct' => mt_rand(40, 65)),
			4 => array('code' => 'NEGO',  'label' => 'Négociation',    'pct' => mt_rand(60, 85)),
			5 => array('code' => 'WON',   'label' => 'Gagné',          'pct' => 100),
		);
		\$projectNames = array('Location Longue Durée Flotte Auto', 'LLD Matériel Chantier', 'Location Informatique 36 mois', 'Contrat LLD Équipement BTP', 'Pack LLD Serveurs', 'Location Nacelles Élévatrices');
		\$dates = dolinstreamGetRandomDates();
		\$ok = \$ko = 0;
		for (\$s = 0; \$s < \$nb; \$s++) {
			\$oppStatus = \$oppStatuses[array_rand(\$oppStatuses)];
			\$proj = new Project(\$db);
			\$proj->title       = \$projectNames[array_rand(\$projectNames)] . ' - ' . date('ym') . '-' . sprintf('%04d', \$s);
			\$proj->ref         = 'LLD-' . date('ym') . '-' . sprintf('%05d', mt_rand(1, 99999));
			\$proj->opp_status  = array_search(\$oppStatus, \$oppStatuses);
			\$proj->opp_percent = \$oppStatus['pct'];
			\$proj->datec       = \$dates['start'];
			\$proj->budget_amount = mt_rand(5000, 50000);
			\$proj->opp_amount    = \$proj->budget_amount * (mt_rand(80, 120) / 100);
			\$proj->status      = 1;
			\$proj->array_options = array('options_rental_ltrproject' => 2);
			if (\$projectMode === 'linked' && !empty(\$socids)) {
				\$proj->socid = \$socids[array_rand(\$socids)];
			}
			\$result = \$proj->create(\$fuser);
			if (\$result > 0) {
				dsLog('? #' . \$s . ' | ' . \$proj->ref . ' | ' . \$proj->title . ' | ' . \$oppStatus['label'] . ' | ' . number_format((int)\$proj->opp_amount, 0, ',', ' ') . ' € | ' . number_format((int)\$proj->budget_amount, 0, ',', ' ') . ' €', 'success');
				\$ok++;
			} else {
				dsLog('? #' . \$s . ' — ' . \$proj->error, 'error');
				\$ko++;
			}
			if (\$s % 5 === 0 || \$s === \$nb - 1) dolinstreamProgress(\$s + 1, \$nb);
		}
		dolinstreamProgress(\$nb, \$nb, true);
		dsLog('--- ' . \$ok . ' OK, ' . \$ko . ' erreur(s) ---');
	} elseif (\$script === 'workflow-opp-cl-pr') {";

$content = str_replace("} elseif (\$script === 'workflow-opp-cl-pr') {", $rentalProjectLogic, $content);

// 4. Parsing log output
$parsers = "    } elseif (\$activeScript === 'generate-rental-product') {
        if (preg_match('/\| id=(\d+) \| (\S+) \| (.+?) \| ([\d\.]+ \S+) \| ([\d\.]+ \S+)/', \$msg, \$m)) {
            \$cells = array(
                \$makeLink('product', \$m[2], '/product/card.php?id=' . \$m[1]),
                trim(\$m[3]), \$m[4], \$m[5]
            );
        }
    } elseif (\$activeScript === 'generate-rental-project') {
        if (preg_match('/\| (\S+) \| (.+?) \| (.+?) \| ([\d ]+) €\S* \| ([\d ]+)/', \$msg, \$m)) {
            \$cells = array(
                \$makeLink('projet', \$m[1], '/projet/card.php?id='),
                trim(\$m[2]), trim(\$m[3]),
                str_replace(' ', '', \$m[4]) . ' €',
                str_replace(' ', '', \$m[5]) . ' €',
            );
        }
    } elseif (\$activeScript === 'generate-stock') {";

$content = str_replace("} elseif (\$activeScript === 'generate-stock') {", $parsers, $content);

file_put_contents($file, $content);
echo "File updated successfully!";
?>
