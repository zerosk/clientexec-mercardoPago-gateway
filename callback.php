<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$_GET['fuse']   = 'billing';
$_GET['action'] = 'gatewaycallback';
$_GET['plugin'] = 'mercadopago';

chdir('../../..');

require_once dirname(__FILE__).'/../../../library/front.php';

?>