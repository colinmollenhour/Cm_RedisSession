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
if (empty($argv[2])) {
    die('Must specify group-by key. E.g. http_user_agent, remote_addr, http_secure, http_host, request_uri, is_new_visitor');
}
$groupBy = $argv[2];
if (empty($argv[3])) {
    die('Must specify sort-by parameter. writes or count');
}
$sortBy = $argv[3];

$redisSession = new Cm_RedisSession_Model_Session;
$cursor = 0;

$getSessionData = function ($data, $key) use ($groupBy)
{
    switch ($groupBy) {
        case 'is_new_visitor':
            if (preg_match("/\"$key\";b:([01])/", $data, $matches)) {
                return $matches[1];
            }
            break;
        default: // remote_addr, http_secure, http_host, http_user_agent, request_uri, is_new_visitor
            if (preg_match("/\"$key\";s:\\d+:\"([^\"]+)\"/", $data, $matches)) {
                return $matches[1];
            }
            break;
    }
    return 'N/A';
};

$client = $redisSession->_redisClient(TRUE)->connect();

$groupedData = array();
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
        $key = $getSessionData($data, $groupBy);
        $groupedData[$key]['count'] ++;
        $groupedData[$key]['writes'] += $sessionData['writes'];
    }
    if ($cursor == 0) {
        break;
    }
}

$sortKeys = array();
foreach ($groupedData as $key => &$stats) {
    $stats['avg'] = $stats['writes'] / $stats['count'];
    $sortKeys[$key] = $sortBy == 'writes' ? $stats['avg'] : $stats['count'];
}
array_multisort($sortKeys, SORT_DESC | SORT_NUMERIC, $groupedData);

echo "Count\tAvgWr\t$groupBy\n";
foreach ($groupedData as $key => $stats) {
    echo "{$stats['count']}\t{$stats['avg']}\t$key\n";
}
echo "\n";
