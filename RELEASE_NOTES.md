# [DoliStream] [23.0.0] - Génération et purge de données en masse pour Dolibarr

Description : Première release de DoliStream, un module de développement fournissant une interface intégrée à Dolibarr pour générer ou purger de gros volumes de données de test (tiers, produits, projets, propositions, commandes, expéditions, factures, ainsi que le cycle fournisseur). ⚠ Module de développement — à ne pas utiliser en production.

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
