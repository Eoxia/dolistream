<?php
/* Copyright (C) 2024  Eoxia <technique@eoxia.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * ⚠ DEVELOPMENT MODULE — DO NOT USE IN PRODUCTION
 */

/**
 * \defgroup   dolistream   Module DoliStream
 * \brief      Bulk data generation and purge tools for Dolibarr development.
 * \file       htdocs/custom/dolistream/core/modules/modDolistream.class.php
 * \ingroup    dolistream
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module DoliStream
 */
class modDolistream extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions and menus.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// ── ID unique du module ───────────────────────────────────────────────
		$this->numero = 680002; // ID réservé pour ce module custom

		// ── Identifiant texte (permissions, menus...) ────────────────────────
		$this->rights_class = 'dolistream';

		// ── Famille ──────────────────────────────────────────────────────────
		$this->family          = 'technic';
		$this->module_position = '90';

		// ── Nom et description ───────────────────────────────────────────────
		$this->name            = preg_replace('/^mod/i', '', get_class($this)); // Dolistream
		$this->description     = 'Bulk data generation and purge tools for development and load testing';
		$this->descriptionlong = 'DoliStream provides a Dolibarr-integrated interface to generate or purge large volumes of test data (thirdparties, products, invoices, orders, proposals). FOR DEVELOPMENT USE ONLY.';

		// ── Auteur ───────────────────────────────────────────────────────────
		$this->editor_name          = 'Eoxia';
		$this->editor_url           = 'https://www.eoxia.com';
		$this->editor_squarred_logo = '';

		// ── Version ──────────────────────────────────────────────────────────
		$this->version = '1.0.0';

		// ── Clé constante d'activation ───────────────────────────────────────
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

		// ── Icône ────────────────────────────────────────────────────────────
		$this->picto = 'technic';

		// ── Fonctionnalités du module ────────────────────────────────────────
		$this->module_parts = array(
			'triggers'          => 0,
			'login'             => 0,
			'substitutions'     => 0,
			'menus'             => 1,
			'tpl'               => 0,
			'barcode'           => 0,
			'models'            => 0,
			'printing'          => 0,
			'theme'             => 0,
			'css'               => array(),
			'js'                => array(),
			'hooks'             => array(),
			'moduleforexternal' => 0,
			'websitetemplates'  => 0,
			'captcha'           => 0,
		);

		// ── Répertoires créés à l'activation ─────────────────────────────────
		$this->dirs = array('/dolistream/temp');

		// ── Page de configuration ────────────────────────────────────────────
		$this->config_page_url = array('setup.php@dolistream');

		// ── Dépendances ──────────────────────────────────────────────────────
		$this->hidden       = 0;
		$this->depends      = array();
		$this->requiredby   = array();
		$this->conflictwith = array();

		// ── Langue ───────────────────────────────────────────────────────────
		$this->langfiles = array('dolistream@dolistream');

		// ── Prérequis ────────────────────────────────────────────────────────
		$this->phpmin                = array(8, 0);
		$this->need_dolibarr_version = array(17, 0);
		$this->need_javascript_ajax  = 0;

		// ── Avertissements à l'activation ────────────────────────────────────
		$this->warnings_activation = array(
			'always' => 'DoliStreamWarningDevOnly',
		);

		// ── Constantes ───────────────────────────────────────────────────────
		$this->const = array();

		// ── Tabs / Dictionnaires / Widgets / Cron ────────────────────────────
		$this->tabs        = array();
		$this->dictionaries = array();
		$this->boxes       = array();
		$this->cronjobs    = array();

		// ── Permissions ──────────────────────────────────────────────────────
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero . '01';
		$this->rights[$r][1] = 'Use DoliStream data generation tools';
		$this->rights[$r][4] = 'generate';
		$this->rights[$r][5] = 'run';
		$r++;

		$this->rights[$r][0] = $this->numero . '02';
		$this->rights[$r][1] = 'Use DoliStream data purge tools (DANGER)';
		$this->rights[$r][4] = 'purge';
		$this->rights[$r][5] = 'run';
		$r++;

		// ── Menus ────────────────────────────────────────────────────────────
		$this->menu = array();
		$r = 0;

		// Top menu
		$this->menu[$r++] = array(
			'fk_menu'  => '',
			'type'     => 'top',
			'titre'    => 'DoliStream',
			'prefix'   => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolistream',
			'leftmenu' => '',
			'url'      => '/dolistream/view/index.php',
			'langs'    => 'dolistream@dolistream',
			'position' => 1000 + $r,
			'enabled'  => "isModEnabled('dolistream')",
			'perms'    => '$user->hasRight("dolistream", "generate", "run")',
			'target'   => '',
			'user'     => 0,
		);

		// Left menu — Clients (parent)
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=dolistream',
			'type'     => 'left',
			'titre'    => 'Tiers',
			'prefix'   => img_picto('', 'company', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolistream',
			'leftmenu' => 'dolistream_generate',
			'url'      => '/dolistream/view/index.php?script=generate-thirdparty',
			'langs'    => 'dolistream@dolistream',
			'position' => 1000 + $r,
			'enabled'  => "isModEnabled('dolistream')",
			'perms'    => '$user->hasRight("dolistream", "generate", "run")',
			'target'   => '',
			'user'     => 0,
		);

		// Left menu — sous-items Clients
		$generateItems = array(
			'generate-product'    => 'Produits',
			'generate-project'    => 'Projets / Opportunités',
			'generate-proposal'   => 'Devis',
			'generate-order'      => 'Commandes',
			'generate-expedition' => 'Expéditions',
			'generate-invoice'    => 'Factures',
		);
		foreach ($generateItems as $scriptKey => $label) {
			$this->menu[$r++] = array(
				'fk_menu'  => 'fk_mainmenu=dolistream,fk_leftmenu=dolistream_generate',
				'type'     => 'left',
				'titre'    => $label,
				'mainmenu' => 'dolistream',
				'leftmenu' => 'dolistream_' . str_replace('-', '_', $scriptKey),
				'url'      => '/dolistream/view/index.php?script=' . $scriptKey,
				'langs'    => 'dolistream@dolistream',
				'position' => 1000 + $r,
				'enabled'  => "isModEnabled('dolistream')",
				'perms'    => '$user->hasRight("dolistream", "generate", "run")',
				'target'   => '',
				'user'     => 0,
			);
		}

		// Left menu — Fournisseurs (parent)
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=dolistream',
			'type'     => 'left',
			'titre'    => 'Fournisseurs',
			'prefix'   => img_picto('', 'supplier', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolistream',
			'leftmenu' => 'dolistream_suppliers',
			'url'      => '/dolistream/view/index.php?script=generate-thirdparty&opt=supplier',
			'langs'    => 'dolistream@dolistream',
			'position' => 1000 + $r,
			'enabled'  => "isModEnabled('dolistream')",
			'perms'    => '$user->hasRight("dolistream", "generate", "run")',
			'target'   => '',
			'user'     => 0,
		);

		// Left menu — sous-items Fournisseurs
		$supplierItems = array(
			'generate-supplier-order'   => 'Commandes fourn.',
			'generate-reception'        => 'Réceptions',
			'generate-supplier-invoice' => 'Factures fourn.',
		);
		foreach ($supplierItems as $scriptKey => $label) {
			$this->menu[$r++] = array(
				'fk_menu'  => 'fk_mainmenu=dolistream,fk_leftmenu=dolistream_suppliers',
				'type'     => 'left',
				'titre'    => $label,
				'mainmenu' => 'dolistream',
				'leftmenu' => 'dolistream_' . str_replace('-', '_', $scriptKey),
				'url'      => '/dolistream/view/index.php?script=' . $scriptKey,
				'langs'    => 'dolistream@dolistream',
				'position' => 1000 + $r,
				'enabled'  => "isModEnabled('dolistream')",
				'perms'    => '$user->hasRight("dolistream", "generate", "run")',
				'target'   => '',
				'user'     => 0,
			);
		}

		// Left menu — Pré-requis (section en gras)
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=dolistream',
			'type'     => 'left',
			'titre'    => '<b>Pré-requis</b>',
			'prefix'   => img_picto('', 'stock', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolistream',
			'leftmenu' => 'dolistream_prerequis',
			'url'      => '/dolistream/view/index.php?script=generate-warehouse',
			'langs'    => 'dolistream@dolistream',
			'position' => 1000 + $r,
			'enabled'  => "isModEnabled('dolistream')",
			'perms'    => '$user->hasRight("dolistream", "generate", "run")',
			'target'   => '',
			'user'     => 0,
		);

		// Left menu — Entrepôt (sous Pré-requis)
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=dolistream,fk_leftmenu=dolistream_prerequis',
			'type'     => 'left',
			'titre'    => 'Entrepôt',
			'prefix'   => img_picto('', 'stock', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolistream',
			'leftmenu' => 'dolistream_generate_warehouse',
			'url'      => '/dolistream/view/index.php?script=generate-warehouse',
			'langs'    => 'dolistream@dolistream',
			'position' => 1000 + $r,
			'enabled'  => "isModEnabled('dolistream')",
			'perms'    => '$user->hasRight("dolistream", "generate", "run")',
			'target'   => '',
			'user'     => 0,
		);

		// Left menu — Purger les données
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=dolistream',
			'type'     => 'left',
			'titre'    => 'Purger les données',
			'prefix'   => img_picto('', 'delete', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolistream',
			'leftmenu' => 'dolistream_purge',
			'url'      => '/dolistream/view/index.php?script=purge-data',
			'langs'    => 'dolistream@dolistream',
			'position' => 1000 + $r,
			'enabled'  => "isModEnabled('dolistream')",
			'perms'    => '$user->hasRight("dolistream", "purge", "run")',
			'target'   => '',
			'user'     => 0,
		);
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param  string $options Options ('', 'noboxes')
	 * @return int<-1,1>       1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		$this->remove($options);
		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param  string $options Options ('', 'noboxes')
	 * @return int<-1,1>       1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
