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
 *  - Only one process may get a write lock on a session.
 *  - A process may lose it's lock if another process breaks it, in which case the session will not be written.
 *  - The lock may be broken after 120 seconds and the process that gets the lock is indeterminate.
 *
 */
class Cm_RedisSession_Model_Session extends Mage_Core_Model_Mysql4_Session
{
    const BREAK_AFTER        = 120;      /* Try to break the lock after this many seconds */
    const BREAK_MODULO       = 5;        /* The lock will only be broken one of of this many tries to prevent multiple processes breaking the same lock */
    const FAIL_AFTER         = 180;      /* Try to get a lock for at most this many seconds */
    const MAX_LIFETIME       = 2592000; /* Redis backend limit */

    const SESSION_PREFIX     = 'sess_';

    const LOG_FILE           = 'redis_session.log';

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
    protected $_hasLock;
    protected $_sessionWritten; // avoid infinite loops

    static public $failedLockAttempts = 0; // for debug or informational purposes

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
            if ($lock == 1 || ($tries >= self::BREAK_AFTER && $lock % self::BREAK_MODULO == 0)) {
                $this->_redis->pipeline()
                    ->hMSet($sessionId, array(
                        'pid' => getmypid(),
                        'lock' => 1,
                    ))
                    ->expire($sessionId, min($this->getLifeTime(), self::MAX_LIFETIME))
                    ->exec();
                $this->_hasLock = TRUE;
                break;
            }
            if (++$tries >= self::FAIL_AFTER) {
                $this->_hasLock = FALSE;
                break;
            }
            sleep(1);
        }
        self::$failedLockAttempts = $tries;

        // Session can be read even if it was not locked by this pid!
        $sessionData = $this->_redis->hGet($sessionId, 'data');
        return $sessionData ? $this->_decodeData($sessionData) : '';
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
        if ($this->_sessionWritten) { return TRUE; }
        $this->_sessionWritten = TRUE;

        // Do not overwrite the session if it is locked by another pid
        try {
            if($this->_dbNum) $this->_redis->select($this->_dbNum);  // Prevent conflicts with other connections?
            $pid = $this->_redis->hGet('sess_'.$sessionId, 'pid'); // PHP Fatal errors cause self::SESSION_PREFIX to not work..
            if ( ! $pid || $pid == getmypid()) {
                $this->_writeRawSession($sessionId, $sessionData, $this->getLifeTime());
            }
            else {
                if ($this->_hasLock) {
                    Mage::log('Unable to write session, another process took the lock: '.$sessionId, Zend_Log::NOTICE, self::LOG_FILE);
                } else {
                    Mage::log('Unable to write session, unable to acquire lock: '.$sessionId, Zend_Log::NOTICE, self::LOG_FILE);
                }
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

        $this->_redis->pipeline();
        if($this->_dbNum) $this->_redis->select($this->_dbNum);
        $this->_redis->del(self::SESSION_PREFIX.$sessionId);
        $this->_redis->exec();
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
     * Public for testing purposes only.
     *
     * @param string $data
     * @return string
     */
    public function _encodeData($data)
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
     * Public for testing purposes only.
     *
     * @param string $data
     * @return string
     */
    public function _decodeData($data)
    {
        switch (substr($data,0,4)) {
            case ':sn:': return snappy_uncompress(substr($data,4));
            case ':lz:': return lzf_decompress(substr($data,4));
            case ':gz:': return gzuncompress(substr($data,4));
        }
        return $data;
    }

    /**
     * Public for testing/import purposes only.
     *
     * @param $id
     * @param $data
     * @param $lifetime
     * @throws Exception
     */
    public function _writeRawSession($id, $data, $lifetime)
    {
        if ( ! $this->_useRedis) {
            throw new Exception('Not connected to redis!');
        }

        $this->_redis->pipeline()
            ->select($this->_dbNum)
            ->hMSet('sess_'.$id, array(
                'data' => $this->_encodeData($data),
                'lock' => 0, // 0 so that next lock attempt will get 1
            ))
            ->expire('sess_'.$id, min($lifetime, 2592000))
            ->exec();
    }

}
