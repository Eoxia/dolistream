<?php
require 'C:/wamp64/www/dolirent/dolibarr/htdocs/master.inc.php';
global $db;
$res = $db->query('DESCRIBE ' . MAIN_DB_PREFIX . 'entrepot');
while ($row = $db->fetch_object($res)) {
    if (strpos($row->Field, 'project') !== false) {
        print $row->Field . "\n";
    }
}
$res = $db->query('DESCRIBE ' . MAIN_DB_PREFIX . 'entrepot_extrafields');
while ($res && $row = $db->fetch_object($res)) {
    if (strpos($row->Field, 'project') !== false || strpos($row->Field, 'rental') !== false) {
        print "extrafield: " . $row->Field . "\n";
    }
}
