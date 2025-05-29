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

class Cm_RedisSession_Model_Session_ClusterConfig extends Cm_RedisSession_Model_Session_Config implements \Cm\RedisSession\Handler\ClusterConfigInterface
{

    /**
     * @var Varien_Object
     */
    protected $clusterConfig = null;

    public function __construct(string $sessionName, Mage_Core_Model_Config_Element $clusterConfig)
    {
        parent::__construct($sessionName);

        $seeds = preg_split('/\s*,\s*/', trim((string) $clusterConfig->descend('seeds')), -1, PREG_SPLIT_NO_EMPTY);
        $this->clusterConfig = new Varien_Object([
            'name' => $clusterConfig->descend('name') ? $clusterConfig->descend('name') : null,
            'seeds' => $seeds,
            'persistent' => (bool) $clusterConfig->descend('persistent'),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function isCluster(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getClusterName(): ?string
    {
        return $this->clusterConfig->getData('name');
    }

    /**
     * {@inheritDoc}
     */
    public function getClusterSeeds(): ?array
    {
        return $this->clusterConfig->getData('seeds');
    }

    /**
     * {@inheritDoc}
     */
    public function getClusterUsePersistentConnection(): bool
    {
        return $this->clusterConfig->getData('persistent');
    }
}
