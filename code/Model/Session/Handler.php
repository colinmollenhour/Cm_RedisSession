<?php
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
    * Redistributions in any form must not change the Cm_RedisSession namespace.

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

// Modman install will have files in module directory (submodule) and composer install
// will have files in vendor directory with autoloader registered
if (is_dir(__DIR__.'/../../lib/src/Cm/RedisSession')) {
    require_once __DIR__.'/../../lib/src/Cm/RedisSession/Handler/ConfigInterface.php';
    require_once __DIR__.'/../../lib/src/Cm/RedisSession/Handler/LoggerInterface.php';
    require_once __DIR__.'/../../lib/src/Cm/RedisSession/Handler.php';
    require_once __DIR__.'/../../lib/src/Cm/RedisSession/ConnectionFailedException.php';
    require_once __DIR__.'/../../lib/src/Cm/RedisSession/ConcurrentConnectionsExceededException.php';
}

class Cm_RedisSession_Model_Session_Handler extends \Cm\RedisSession\Handler
{
    public function __construct()
    {
        parent::__construct(
            new Cm_RedisSession_Model_Session_Config(),
            new Cm_RedisSession_Model_Session_Logger()
        );
    }

    /**
     * Return all data for a given session
     *
     * @param string $id
     * @return array
     * @throws Exception
     */
    public function inspectSession($id)
    {
        $sessionId = strpos($id, 'sess_') === 0 ? $id : 'sess_' . $id;
        $this->_redis->select($this->_dbNum);
        $data = $this->_redis->hGetAll($sessionId);
        if ($data && isset($data['data'])) {
            $data['data'] = $this->_decodeData($data['data']);
        }
        return $data;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $data
     * @return string
     */
    public function encodeData($data)
    {
        return parent::_encodeData($data);
    }

    /**
     * {@inheritDoc}
     *
     * @param $id
     * @param $data
     * @param $lifetime
     * @throws \Exception
     */
    public function writeRawSession($id, $data, $lifetime)
    {
        parent::_writeRawSession($id, $data, $lifetime);
    }

    /**
     * Public for testing/inspection purposes only.
     *
     * @param $forceStandalone
     * @return \Credis_Client
     */
    public function redisClient($forceStandalone)
    {
        if ($forceStandalone) {
            $this->_redis->forceStandalone();
        }
        if ($this->_dbNum) {
            $this->_redis->select($this->_dbNum);
        }
        return $this->_redis;
    }
}
