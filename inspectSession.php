<?php PHP_SAPI == 'cli' or die('CLI only.');

require 'app/Mage.php';
Mage::app();

if (empty($argv[1])) {
  die('Must specify session id.');
}
$sessionId = $argv[1];

$redisSession = new Cm_RedisSession_Model_Session;
$sessionData = $redisSession->_inspectSession($sessionId);
$data = $sessionData['data'];
unset($sessionData['data']);
var_dump($sessionData);
echo "DATA:\n$data\n";
