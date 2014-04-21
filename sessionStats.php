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

if (empty($argv[1])) {
  die('Must specify session glob pattern. E.g. sess_*');
}
$sessionPattern = $argv[1];

$redisSession = new Cm_RedisSession_Model_Session;
$cursor = 0;

function getSessionData($data, $key)
{
    if (preg_match("/\"$key\";s:\d+:\"([^\"]+)\"/", $data, $matches)) {
        return $matches[1];
    }
    return NULL;
}

$client = $redisSession->_redisClient(TRUE)->connect();

$userAgents = array();
$cursor = 0;
while(1) {
    try {
        $result = $client->__call('scan', array($cursor, 'MATCH', $sessionPattern, 'COUNT', 10000));
        list($cursor, $keys) = $result;
    } catch (CredisException $e) {
        if ($e->getMessage() != "unknown command 'scan'") {
            throw $e;
        }
        $keys = $client->keys($sessionPattern);
        $cursor = 0;
    }
    foreach ($keys as $sessionId) {
        $sessionData = $redisSession->_inspectSession($sessionId);
        $data = $sessionData['data'];
        $userAgent = getSessionData($data, 'http_user_agent');
        if ( ! $userAgent) {
            echo "No user agent for $sessionId.\n";
        }
        $userAgents[$userAgent]['count'] ++;
        $userAgents[$userAgent]['writes'] += $sessionData['writes'];
    }
    if ($cursor == 0) {
        break;
    }
}

$avg = array();
foreach ($userAgents as $userAgent => &$stats) {
    $stats['avg'] = $avg[$userAgent] = $stats['writes'] / $stats['count'];
}
array_multisort($avg, SORT_DESC | SORT_NUMERIC, $userAgents);

echo "Count\tAvgWr\tUser-Agent\n";
foreach ($userAgents as $userAgent => $stats) {
    echo "{$stats['count']}\t{$stats['avg']}\t$userAgent\n";
}
echo "\n";
