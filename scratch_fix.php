<?php
$file = "C:/wamp64/www/dolirent/dolibarr/htdocs/custom/dolistream/view/index.php";
$content = file_get_contents($file);

$content = str_replace("G?n?rer Produits Loc", "Générer Produits Loc", $content);
$content = str_replace("G?n?re des produits configur?s pour la location avec un ratio param?trable de prix locatif par rapport au prix de vente.", "Génère des produits configurés pour la location avec un ratio paramétrable de prix locatif par rapport au prix de vente.", $content);
$content = str_replace("G?n?rer Projets LLD", "Générer Projets LLD", $content);
$content = str_replace("G?n?re des projets pr?-configur?s comme Location Longue Dur?e (LLD).", "Génère des projets pré-configurés comme Location Longue Durée (LLD).", $content);
$content = str_replace("Nombre ? g?n?rer", "Nombre à générer", $content);
$content = str_replace("R?f", "Réf", $content);
$content = str_replace("Li? ? un tiers al?atoire", "Lié à un tiers aléatoire", $content);
$content = str_replace("G?n?r? automatiquement par DoliStream (Location).", "Généré automatiquement par DoliStream (Location).", $content);
$content = str_replace("Location Longue Dur?e Flotte Auto", "Location Longue Durée Flotte Auto", $content);
$content = str_replace("LLD Mat?riel Chantier", "LLD Matériel Chantier", $content);
$content = str_replace("Contrat LLD ?quipement BTP", "Contrat LLD Équipement BTP", $content);
$content = str_replace("Location Nacelles ?l?vatrices", "Location Nacelles Élévatrices", $content);
$content = str_replace("Gagn?", "Gagné", $content);
$content = str_replace("N?gociation", "Négociation", $content);
$content = str_replace("Qualifi?", "Qualifié", $content);

file_put_contents($file, $content);
echo "Encoding fixed!";
?>