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

class Cm_RedisSession_Model_Session_Config implements \Cm\RedisSession\Handler\ConfigInterface
{
    /**
     * @var Varien_Object
     */
    protected $config = null;

    public function __construct(string $sessionName)
    {
        $config = Mage::getConfig()->getNode('global/redis_session') ?: new Mage_Core_Model_Config_Element('<root></root>');

        // Clone the XML Config data to varien object to fix: https://github.com/colinmollenhour/Cm_RedisSession/issues/155
        $this->config = new Varien_Object([
            'log_level' => (int) $config->descend('log_level'),
            'host' => (string) $config->descend('host'),
            'port' => (int) $config->descend('port'),
            'db' => (int) $config->descend('db'),
            'password' => (string) $config->descend('password'),
            'timeout' => (float) $config->descend('timeout'),
            'persistent' => (string) $config->descend('persistent'),
            'compression_threshold' => (int) $config->descend('compression_threshold'),
            'compression_lib' => (string) $config->descend('compression_lib'),
            'max_concurrency' => (int) $config->descend('max_concurrency'),
            'max_lifetime' => (int) $config->descend('max_lifetime'),
            'min_lifetime' => (int) $config->descend('min_lifetime'),
            'disable_locking' => $config->is('disable_locking'),
            'bot_lifetime' => (int) $config->descend('bot_lifetime'),
            'bot_first_lifetime' => (int) $config->descend('bot_first_lifetime'),
            'first_lifetime' => (int) $config->descend('first_lifetime'),
            'fail_after' => (float) $config->descend('fail_after'),
            'sentinel_servers' => (string) $config->descend('sentinel_servers'),
            'sentinel_master' => (string) $config->descend('sentinel_master'),
            'sentinel_verify_master' => $config->is('sentinel_verify_master'),
            'sentinel_connect_retries' => (int) $config->descend('sentinel_connect_retries'),
            'break_after' => (int) $config->descend('break_after_' . $sessionName),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getLogLevel()
    {
        return $this->config->getData('log_level');
    }

    /**
     * {@inheritDoc}
     */
    public function getHost()
    {
        return $this->config->getData('host');
    }

    /**
     * {@inheritDoc}
     */
    public function getPort()
    {
        return $this->config->getData('port');
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabase()
    {
        return $this->config->getData('db');
    }

    /**
     * {@inheritDoc}
     */
    public function getPassword()
    {
        return $this->config->getData('password');
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeout()
    {
        return $this->config->getData('timeout');
    }

    /**
     * {@inheritDoc}
     */
    public function getPersistentIdentifier()
    {
        return $this->config->getData('persistent');
    }

    /**
     * {@inheritDoc}
     */
    public function getCompressionThreshold()
    {
        return $this->config->getData('compression_threshold');
    }

    /**
     * {@inheritDoc}
     */
    public function getCompressionLibrary()
    {
        return $this->config->getData('compression_lib');
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxConcurrency()
    {
        return $this->config->getData('max_concurrency');
    }

    /**
     * @return {@inheritDoc}
     */
    public function getLifetime()
    {
        return Mage::app()->getStore()->isAdmin() && Mage::getStoreConfig('admin/security/session_cookie_lifetime')
            ? (int)Mage::getStoreConfig('admin/security/session_cookie_lifetime')
            : Mage::getSingleton('core/cookie')->getLifetime();
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxLifetime()
    {
        return $this->config->getData('max_lifetime');
    }

    /**
     * {@inheritDoc}
     */
    public function getMinLifetime()
    {
        return $this->config->getData('min_lifetime');
    }

    /**
     * {@inheritDoc}
     */
    public function getDisableLocking()
    {
        return $this->config->getData('disable_locking');
    }

    /**
     * @return int
     */
    public function getBotLifetime()
    {
        return $this->config->getData('bot_lifetime');
    }

    /**
     * {@inheritDoc}
     */
    public function getBotFirstLifetime()
    {
        return $this->config->getData('bot_first_lifetime');
    }

    /**
     * {@inheritDoc}
     */
    public function getFirstLifetime()
    {
        return $this->config->getData('first_lifetime');
    }

    /**
     * {@inheritDoc}
     */
    public function getBreakAfter()
    {
        return $this->config->getData('break_after');
    }

    /**
     * {@inheritDoc}
     */
    public function getFailAfter()
    {
        return $this->config->getData('fail_after');
    }

    /**
     * {@inheritDoc}
     */
    public function getSentinelServers()
    {
        return $this->config->getData('sentinel_servers');
    }

    /**
     * {@inheritDoc}
     */
    public function getSentinelMaster()
    {
        return $this->config->getData('sentinel_master');
    }

    /**
     * {@inheritDoc}
     */
    public function getSentinelVerifyMaster()
    {
        return $this->config->getData('sentinel_verify_master');
    }

    /**
     * {@inheritDoc}
     */
    public function getSentinelConnectRetries()
    {
        return $this->config->getData('sentinel_connect_retries');
    }
}
