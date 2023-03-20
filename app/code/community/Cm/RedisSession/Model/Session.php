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

class Cm_RedisSession_Model_Session implements \Zend_Session_SaveHandler_Interface
{

    const FLAG_READ_ONLY = 'cm-redissession-read-only';

    /**
     * @var int|null
     */
    public static $failedLockAttempts;

    /**
     * @var \Cm\RedisSession\Handler
     */
    protected $sessionHandler;
    protected bool $dieOnError = true;

    public function __construct($config = array())
    {
        try {
            $this->sessionHandler = new \Cm\RedisSession\Handler(
                new Cm_RedisSession_Model_Session_Config($config['session_name'] ?? 'default'),
                new Cm_RedisSession_Model_Session_Logger(),
                Mage::registry('controller')
                  && Mage::app()->getFrontController()->getAction()
                  && Mage::app()->getFrontController()->getAction()->getFlag('', self::FLAG_READ_ONLY)
            );
        } catch (\Cm\RedisSession\ConnectionFailedException $e) {
            $this->handleException($e);
        }
    }

    public function setDieOnError(bool $flag): void
    {
        $this->dieOnError = $flag;
    }

    /**
     * Setup save handler
     *
     * @return $this
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
        try {
            return $this->sessionHandler->open($savePath, $sessionName);
        } catch (Throwable $e) {
            $this->handleException($e);
            return false;
        }
    }

    /**
     * Fetch session data
     *
     * @param string $sessionId
     * @return string|bool
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
            return false;
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
    protected function handleException(\Exception $e)
    {
        if ($e instanceof \Cm\RedisSession\ConcurrentConnectionsExceededException) {
            Mage::register('concurrent_connections_exceeded', true);
            if (Mage::getConfig()->getNode('global/redis_session')->is('log_exceptions')) {
                Mage::logException($e);
            }
            if ($this->dieOnError) {
                require_once Mage::getBaseDir() . DS . 'errors' . DS . '503.php';
                die();
            }
        } else if ($this->dieOnError) {
            Mage::printException($e);
        }
    }
}
