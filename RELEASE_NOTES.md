# [DoliStream] [23.0.1] - Génération et purge de données en masse pour Dolibarr

Description : Module de développement fournissant une interface intégrée à Dolibarr pour générer ou purger de gros volumes de données de test (tiers, produits, projets, propositions, commandes, expéditions, factures, ainsi que le cycle fournisseur). ⚠ Module de développement — à ne pas utiliser en production.

## Nouvelles fonctionnalités et innovations

### Génération de données client

* Génération en masse de tiers, produits, projets / opportunités, propositions commerciales, commandes, expéditions et factures.
* Paramétrage des volumes, du type de ligne, du nombre de produits/services et des quantités.

### Génération du cycle fournisseur

* Génération de commandes fournisseur, réceptions et factures fournisseur.

### Pré-requis, stock et workflows

* Génération d'entrepôts et de stock, avec liaison des entrepôts aux projets.
* Flux complet OPP + CL + PR (opportunité → client → proposition).

### Module externe Location (Rental)

* Génération dédiée au module Location : produits, projets, propositions, commandes, expéditions, livraisons et flux de location complet (dates de location, facturation LLD, multi-sélection de tiers).

---

## Améliorations & corrections

* Correction du reporting d'erreurs lors de la génération de stock (règles de lots respectées).
* Correction de la signature de `addline()` pour Dolibarr v23.
* Purge des données de test depuis les réglages du module.
* Protection : le module ne peut pas être activé en environnement de production.
* Conformité store : `ajax/run.php` inclut désormais `view/index.php` via `dol_include_once` (au lieu de `DOL_DOCUMENT_ROOT`), et suppression des scripts de debug (`test.php`, `test2.php`, `scratch_*.php`) qui ne respectaient pas les bonnes pratiques d'inclusion de `main.inc.php`.
