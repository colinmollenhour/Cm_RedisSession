<?php PHP_SAPI == 'cli' or die('CLI only.');

require 'app/Mage.php';
Mage::app();

$test = in_array('--test', $argv);

$coreSession = new Mage_Core_Model_Session_Abstract;
$redisSession = new Cm_RedisSession_Model_Session;

$sessionPath = $coreSession->getSessionSavePath();
if ( ! is_readable($sessionPath) || ! ($dir = opendir($sessionPath))) {
  die("The session save path is not readable: {$sessionPath}\n");
}

if( ! $redisSession->hasConnection()) {
  die("Could not connect to redis server, please check your configuration.\n");
}

$sessionLifetime = max(Mage::getStoreConfig('admin/security/session_cookie_lifetime'), Mage::getStoreConfig('web/cookie/cookie_lifetime'), 3600);

if ( ! in_array('-y', $argv)) {
  $redisConnection = Mage::getConfig()->getNode(Cm_RedisSession_Model_Session::XML_PATH_HOST).':'.Mage::getConfig()->getNode(Cm_RedisSession_Model_Session::XML_PATH_PORT).'/'.Mage::getConfig()->getNode(Cm_RedisSession_Model_Session::XML_PATH_DB);
  $input = readline("Migrate sessions from $sessionPath to $redisConnection with $sessionLifetime second lifetime? (y|n) ");
  if($input != 'y') die("Aborted.\n");
}

$i = $migrated = $expired = $noData = $beforeSize = $afterSize = $elapsedTime = 0;
while ($sessionFile = readdir($dir))
{
  if ($sessionFile == '.' || $sessionFile == '..') continue;
  $file = "$sessionPath/$sessionFile";
  if ( ! is_readable($file)) {
    die("Could not read $file. Please run the script as root or as the web server user.\n");
  }
  if ( ! preg_match('/^sess_(\w+)$/', $sessionFile, $matches)) {
    die("Session file name does not match expected pattern: sess_\\w+\n");
  }
  $sessionId = $matches[1];
  $i++;

  $expiry = filemtime($file) + $sessionLifetime;
  if(time() > $expiry) {
    $expired++;
    continue;
  }
  $sessionData = file_get_contents($file);
  if( ! $sessionData) {
    echo "No session data read for $sessionFile\n";
    $noData++;
    continue;
  }

  $startTime = microtime(true);
  if($test) {
    $beforeSize += strlen($sessionData);
    $afterSize += strlen($redisSession->_encodeData($sessionData));
  }
  else {
    $redisSession->_writeRawSession($sessionId, $sessionData, $expiry - time());
  }
  $elapsedTime += microtime(true) - $startTime;
  $migrated++;
}

$reasons = ($expired || $noData ? " ($expired expired, $noData no data)" : '');
if ($test) {
  printf("Can migrate %d of %d sessions%s. Compressed from %d to %d (%.5f%% savings) in %.5f seconds\n", $migrated, $i, $reasons, $beforeSize, $afterSize, (($beforeSize-$afterSize)/$beforeSize)*100, $elapsedTime);
} else {
  printf("Migrated %d of %d session files in %.5f seconds%s\n", $migrated, $i, $elapsedTime, $reasons);
  Mage::app()->cleanCache(Mage_Core_Model_Config::CACHE_TAG);
}
