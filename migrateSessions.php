<?php PHP_SAPI == 'cli' or die('CLI only.');
/*
==New BSD License==

Copyright (c) 2013, Colin Mollenhour
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * The name of Colin Mollenhour may not be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

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
