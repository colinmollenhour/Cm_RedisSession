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
     * @var \Mage_Core_Model_Config_Element
     */
    private $config;

    public function __construct()
    {
        $this->config = Mage::getConfig()->getNode('global/redis_session') ?: new Mage_Core_Model_Config_Element('<root></root>');
    }

    /**
     * {@inheritDoc}
     */
    public function getLogLevel()
    {
        return (int) $this->config->descend('log_level');
    }

    /**
     * {@inheritDoc}
     */
    public function getHost()
    {
        return (string) $this->config->descend('host');
    }

    /**
     * {@inheritDoc}
     */
    public function getPort()
    {
        return (int) $this->config->descend('port');
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabase()
    {
        return (int) $this->config->descend('db');
    }

    /**
     * {@inheritDoc}
     */
    public function getPassword()
    {
        return (string) $this->config->descend('password');
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeout()
    {
        return (float) $this->config->descend('timeout');
    }

    /**
     * {@inheritDoc}
     */
    public function getPersistentIdentifier()
    {
        return (string) $this->config->descend('persistent');
    }

    /**
     * {@inheritDoc}
     */
    public function getCompressionThreshold()
    {
        return (int) $this->config->descend('compression_threshold');
    }

    /**
     * {@inheritDoc}
     */
    public function getCompressionLibrary()
    {
        return (string) $this->config->descend('compression_lib');
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxConcurrency()
    {
        return (int) $this->config->descend('max_concurrency');
    }

    /**
     * @return {@inheritDoc}
     */
    public function getLifetime()
    {
        return Mage::app()->getStore()->isAdmin()
            ? (int)Mage::getStoreConfig('admin/security/session_cookie_lifetime')
            : Mage::getSingleton('core/cookie')->getLifetime();
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxLifetime()
    {
        return (int) $this->config->descend('max_lifetime');
    }

    /**
     * {@inheritDoc}
     */
    public function getMinLifetime()
    {
        return (int) $this->config->descend('min_lifetime');
    }

    /**
     * {@inheritDoc}
     */
    public function getDisableLocking()
    {
        return $this->config->is('disable_locking');
    }

    /**
     * @return int
     */
    public function getBotLifetime()
    {
        return (int) $this->config->descend('bot_lifetime');
    }

    /**
     * {@inheritDoc}
     */
    public function getBotFirstLifetime()
    {
        return (int) $this->config->descend('bot_first_lifetime');
    }

    /**
     * {@inheritDoc}
     */
    public function getFirstLifetime()
    {
        return (int) $this->config->descend('first_lifetime');
    }

    /**
     * {@inheritDoc}
     */
    public function getBreakAfter()
    {
        return (int) $this->config->descend('break_after_' . session_name());
    }

    /**
     * {@inheritDoc}
     */
    public function getFailAfter()
    {
        return (float) $this->config->descend('fail_after');
    }

    /**
     * {@inheritDoc}
     */
    public function getSentinelServers()
    {
        return (string) $this->config->descend('sentinel_servers');
    }

    /**
     * {@inheritDoc}
     */
    public function getSentinelMaster()
    {
        return (string) $this->config->descend('sentinel_master');
    }

    /**
     * {@inheritDoc}
     */
    public function getSentinelVerifyMaster()
    {
        return $this->config->is('sentinel_verify_master');
    }

    /**
     * {@inheritDoc}
     */
    public function getSentinelConnectRetries()
    {
        return (int) $this->config->descend('sentinel_connect_retries');
    }
}
