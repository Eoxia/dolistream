<?php
require 'C:/wamp64/www/dolirent/dolibarr/htdocs/master.inc.php';
global $db;
$res = $db->query('SELECT rowid, nom FROM ' . MAIN_DB_PREFIX . 'societe WHERE status=1 AND client IN (1,3) ORDER BY nom');
if (!$res) echo "ERROR: " . $db->lasterror(); else echo "OK";
