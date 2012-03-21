<?php
/**
 * Redis session handler with optimistic locking.
 *
 * Features:
 *  - Falls back to mysql handler if it can't connect to redis. Mysql handler falls back to file handler.
 *  - When a session's data exceeds the compression threshold the session data will be compressed.
 *  - Compression libraries supported are 'gzip', 'lzf' and 'snappy'. Lzf and Snappy are much faster than gzip.
 *  - Compression can be enabled, disabled, or reconfigured on the fly with no loss of session data.
 *  - Expiration is handled by Redis. No garbage collection needed.
 *  - Logs when sessions are not written due to not having or losing their lock.
 *
 * Locking Algorithm Properties:
 *  - Only one process may get a write lock on a session
 *  - A process may lose it's write lock if the break attempts are exceeded
 *  - If a process cannot get a lock on the session or loses it's lock, it will
 *    read the session but will silently fail to write the session.
 *  - The more processes there are requesting a lock on the session, the faster the lock will be broken.
 *
 */
class Cm_RedisSession_Model_Session extends Mage_Core_Model_Mysql4_Session
{
    const BREAK_AFTER        = 20;      /* Break the lock when the lock value reaches this number */
    const FAIL_AFTER         = 30;      /* Try to get a lock for at most this many seconds */
    const MAX_LIFETIME       = 2592000; /* Redis backend limit */

    const SESSION_PREFIX     = 'sess:';

    const XML_PATH_HOST            = 'global/redis_session/host';
    const XML_PATH_PORT            = 'global/redis_session/port';
    const XML_PATH_TIMEOUT         = 'global/redis_session/timeout';
    const XML_PATH_DB              = 'global/redis_session/db';
    const XML_PATH_COMPRESSION_THRESHOLD = 'global/redis_session/compression_threshold';
    const XML_PATH_COMPRESSION_LIB = 'global/redis_session/compression_lib';

    /** @var bool */
    protected $_useRedis;

    /** @var Credis_Client */
    protected $_redis;

    /** @var int */
    protected $_dbNum;

    protected $_compressionThreshold;
    protected $_compressionLib;

    public function __construct()
    {
        $host = (string)   (Mage::getConfig()->getNode(self::XML_PATH_HOST) ?: '127.0.0.1');
        $port = (int)      (Mage::getConfig()->getNode(self::XML_PATH_PORT) ?: '6379');
        $timeout = (float) (Mage::getConfig()->getNode(self::XML_PATH_TIMEOUT) ?: '2.5');
        $this->_dbNum = (int) (Mage::getConfig()->getNode(self::XML_PATH_DB) ?: 0);
        $this->_compressionThreshold = (int) (Mage::getConfig()->getNode(self::XML_PATH_COMPRESSION_THRESHOLD) ?: 2048);
        $this->_compressionLib = (string) (Mage::getConfig()->getNode(self::XML_PATH_COMPRESSION_LIB) ?: 'gzip');
        $this->_redis = new Credis_Client($host, $port, $timeout);
        $this->_useRedis = TRUE;
    }

    /**
     * Check DB connection
     *
     * @return bool
     */
    public function hasConnection()
    {
        if( ! $this->_useRedis) return parent::hasConnection();

        try {
            $this->_redis->connect();
            return TRUE;
        }
        catch (Exception $e) {
            Mage::logException($e);
            $this->_redis = NULL;
            $this->_useRedis = FALSE;
        }
        return FALSE;
    }

    /**
     * Fetch session data
     *
     * @param string $sessionId
     * @return string
     */
    public function read($sessionId)
    {
        if ( ! $this->_useRedis) return parent::read($sessionId);

        // Get lock on session. Increment the "lock" field and if the new value is 1, we have the lock.
        // If the new value is exactly BREAK_AFTER then we also have the lock and have broken the
        // lock for the previous process.
        $sessionId = self::SESSION_PREFIX.$sessionId;
        $tries = 0;
        if($this->_dbNum) $this->_redis->select($this->_dbNum);
        while(1)
        {
            // Increment lock value for this session and retrieve the new value
            $lock = $this->_redis->hIncrBy($sessionId, 'lock', 1);

            // If we got the lock, update with our pid and reset lock and expiration
            if ($lock == 1 || $lock == self::BREAK_AFTER) {
                $this->_redis->hMSet($sessionId, array(
                    'pid' => getmypid(),
                    'lock' => 1,
                ));
                $this->_redis->expire($sessionId, min($this->getLifeTime(), self::MAX_LIFETIME));
                break;
            }
            if (++$tries >= self::FAIL_AFTER) {
                break;
            }
            sleep(1);
        }

        // Session can be read even if it was not locked by this pid!
        $sessionData = $this->_redis->hGet($sessionId, 'data');
        return $sessionData ? $this->decodeData($sessionData) : '';
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
        if ( ! $this->_useRedis) return parent::write($sessionId, $sessionData);

        // Do not overwrite the session if it is locked by another pid
        $sessionId = self::SESSION_PREFIX.$sessionId;
        try {
            if($this->_dbNum) $this->_redis->select($this->_dbNum);  // Prevent conflicts with other connections?
            $pid = $this->_redis->hGet($sessionId, 'pid');
            if ( ! $pid || $pid == getmypid()) {
                $this->_redis->hMSet($sessionId, array(
                    'data' => $this->encodeData($sessionData),
                    'lock' => 0,  // Unlock session (next lock attempt will get '1')
                ));
                $this->_redis->expire($sessionId, min($this->getLifeTime(), self::MAX_LIFETIME));
            }
            else {
                throw new Exception('Unable to write session, another process has the lock.');
            }
        }
        catch(Exception $e) {
            Mage::logException($e);
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Destroy session
     *
     * @param string $sessionId
     * @return boolean
     */
    public function destroy($sessionId)
    {
        if ( ! $this->_useRedis) return parent::destroy($sessionId);

        if($this->_dbNum) $this->_redis->select($this->_dbNum);
        $this->_redis->del(self::SESSION_PREFIX.$sessionId);
        return TRUE;
    }

    /**
     * Garbage collection
     *
     * @param int $maxLifeTime ignored
     * @return boolean
     */
    public function gc($maxLifeTime)
    {
        if ( ! $this->_useRedis) return parent::gc($maxLifeTime);
        return TRUE;
    }

    /**
     * @param string $data
     * @return string
     */
    protected function encodeData($data)
    {
        if ($this->_compressionThreshold > 0 && $this->_compressionLib != 'none' && strlen($data) >= $this->_compressionThreshold) {
            switch($this->_compressionLib) {
              case 'snappy': $data = snappy_compress($data); break;
              case 'lzf':    $data = lzf_compress($data); break;
              case 'gzip':   $data = gzcompress($data, 1); break;
            }
            if($data) {
                $data = ':'.substr($this->_compressionLib,0,2).':'.$data;
            } else {
                Mage::log("Could not compress session data using {$this->_compressionLib}.");
            }
        }
        return $data;
    }

    /**
     * @param string $data
     * @return string
     */
    protected function decodeData($data)
    {
        switch (substr($data,0,4)) {
            case ':sn:': return snappy_uncompress(substr($data,4));
            case ':lz:': return lzf_decompress(substr($data,4));
            case ':gz:': return gzuncompress(substr($data,4));
        }
        return $data;
    }

}
