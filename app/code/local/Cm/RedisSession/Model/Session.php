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
if (is_dir(__DIR__.'/../lib/src/Cm/RedisSession')) {
    require_once __DIR__.'/../lib/src/Cm/RedisSession/Handler/ConfigInterface.php';
    require_once __DIR__.'/../lib/src/Cm/RedisSession/Handler/LoggerInterface.php';
    require_once __DIR__.'/../lib/src/Cm/RedisSession/Handler.php';
    require_once __DIR__.'/../lib/src/Cm/RedisSession/ConnectionFailedException.php';
    require_once __DIR__.'/../lib/src/Cm/RedisSession/ConcurrentConnectionsExceededException.php';
}

class Cm_RedisSession_Model_Session implements \Zend_Session_SaveHandler_Interface
{

    /**
     * @var int|null
     */
    public static $failedLockAttempts;

    /**
     * @var \Cm\RedisSession\Handler
     */
    private $sessionHandler;

    public function __construct()
    {
        try {
            $this->sessionHandler = new \Cm\RedisSession\Handler(
                new Cm_RedisSession_Model_Session_Config(),
                new Cm_RedisSession_Model_Session_Logger()
            );
        } catch (\Cm\RedisSession\ConnectionFailedException $e) {
            $this->handleException($e);
        }
    }

    /**
     * Setup save handler
     *
     * @return Mage_Core_Model_Resource_Session
     */
    public function setSaveHandler()
    {
        session_set_save_handler(
            array($this, 'open'),
            array($this, 'close'),
            array($this, 'read'),
            array($this, 'write'),
            array($this, 'destroy'),
            array($this, 'gc')
        );
        return $this;
    }

    /**
     * Adds session handler via static call
     */
    public static function setStaticSaveHandler()
    {
        $handler = new self;
        $handler->setSaveHandler();
    }

    /**
     * Open session
     *
     * @param string $savePath ignored
     * @param string $sessionName ignored
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function open($savePath, $sessionName)
    {
        return $this->sessionHandler->open($savePath, $sessionName);
    }

    /**
     * Fetch session data
     *
     * @param string $sessionId
     * @return string
     */
    public function read($sessionId)
    {
        try {
            $data = $this->sessionHandler->read($sessionId);
            self::$failedLockAttempts = $this->sessionHandler->getFailedLockAttempts();
            return $data;
        } catch (\Cm\RedisSession\ConcurrentConnectionsExceededException $e) {
            self::$failedLockAttempts = $this->sessionHandler->getFailedLockAttempts();
            $this->handleException($e);
        }
    }

    /**
     * Update session
     *
     * @param string $sessionId
     * @param string $sessionData
     * @return boolean
     */
    public function write($sessionId, $sessionData)
    {
        return $this->sessionHandler->write($sessionId, $sessionData);
    }

    /**
     * Destroy session
     *
     * @param string $sessionId
     * @return boolean
     */
    public function destroy($sessionId)
    {
        return $this->sessionHandler->destroy($sessionId);
    }

    /**
     * Close session
     *
     * @return bool
     */
    public function close()
    {
        return $this->sessionHandler->close();
    }

    /**
     * Garbage collection
     *
     * @param int $maxLifeTime ignored
     * @return boolean
     */
    public function gc($maxLifeTime)
    {
        return $this->sessionHandler->gc($maxLifeTime);
    }

    /**
     * @param \Exception $e
     * @return void
     */
    private function handleException(\Exception $e)
    {
        Mage::logException($e);
        require_once Mage::getBaseDir() . DS . 'errors' . DS . '503.php';
        exit;
    }
}
