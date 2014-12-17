<?php

namespace SS\Bundle\GameBundle\Core;

use Desarrolla2\Cache\Adapter\AdapterInterface;
use SS\Bundle\GameBundle\Core\Registry;

/**
 * Class CacheCommon handle systemwide caching using SC cache provider
 *
 * @package SS\Bundle\GameBundle\Core
 */
class CacheCommon
{
    /**
     * Should we debug cache hits/misses?
     *
     * @var bool
     */
    private $debugCache;

    /**
     * Should cache stampeding be turned on
     * @var bool
     */
    private $stampedeProtection;

    /**
     * Time to live for this caching resource
     * @var int
     */
    private $ttl;

    /**
     * If a query returns an empty resultset, then this value will be stored in cache
     * If we store a null in cache, the next cache->get will return false, and the query that yields empty data will be run again
     *
     * @var string
     */
    const EMPTY_RESULT_CACHE_VALUE = 'QUERY_HAS_EMPTY_RESULTSET';

    /**
     * How long should a lock hang around
     *
     * @var int
     */
    const LOCK_TTL_SECONDS = 60;

    /**
     * How long should stale data be stored? Stale data is retrieved only if a query is being run and cache is being refreshed
     *
     * @note: this number is added to the localTTL and remoteTTL respectively
     * @var int
     */
    const STALE_DATA_TTL_SECONDS = 60;

    /**
     * How long should slave status be cached in MemCache
     *
     * @var int
     */
    const SLAVE_STATUS_TTL = 30;

    /**
     * Number of microseconds to sleep and recursively try to get from cache; when a query is running, i.e. a lock exists
     *
     * @var int
     */
    const LOCK_SLEEP_MICROSECONDS = 250000;

    /**
     * Log counter to increment the num
     *
     * @var int
     */
    protected $_logCounter = 0;

    /**
     * Concrete cache instance
     *
     * @var AdapterInterface
     */
    protected $adapter;

    public function __construct(AdapterInterface $cacheAdapter, bool $debugCache, bool $stampedeProtection, int $ttl)
    {
        $this->adapter = new $cacheAdapter();
        $this->debugCache = $debugCache;
        $this->stampedeProtection = $stampedeProtection;
        $this->ttl = $ttl;
    }

    /**
     * Attempt to get an item from cache. First check local cache, if we don't find an item, then check the remote cache
     *
     * @param string $cacheKey           Unique cache key
     *
     * @return mixed|bool|null Return false if nothing is found, null is query data is empty; otherwise return data
     */
    public function getFromCache($cacheKey)
    {
        /*
         * If $_refreshCache is statically set, at the class level, return false and ignore all caching
         * If we set this property to true, caching is ignored. This results in a 'cache miss'
         * Fresh data would be retrieved from the database, stale data in cache will be updated with new data, effectively "refreshing" the cache
         */
        if (DBCommon::$refreshCache == true) {
            return false;
        }

        //If we have a connection to remote i.e MemCache
        if (isset($this->adapter) && !empty($this->adapter)) {

            $data = $this->adapter->get($cacheKey);

            if (!empty($data)) { //We have remote data

                //Set a registry entry for MemCache
                $this->setToRegistry($cacheKey, 'Cache::get()');

                //Try to set the same data to local
                $this->setToCache($cacheKey, $data);

                //If data is not the result of an empty query, return it, otherwise return null.
                return $data !== self::EMPTY_RESULT_CACHE_VALUE ? $data : null;
            }
        }

        //If stampede protection is turned on
        if ($this->stampedeProtection) {

            if ($this->lockExists($cacheKey)) { //Look in memcache for a lock

                //Return old data from remote
                $staleData = $this->getStaleDataFromCache($cacheKey);

                //If we have stale data, return it to the client
                if (!empty($staleData)) {

                    //If data is not the result of an empty query, return it, otherwise return null.
                    return $data !== self::EMPTY_RESULT_CACHE_VALUE ? $data : null;

                } else { //Sleep, and call recursively

                    usleep(self::LOCK_SLEEP_MICROSECONDS + rand(-50000, 50000));
                    
                    $this->getFromCache($cacheKey);
                }
            }
        }
        return false; //cache miss
    }

    /**
     * Attempt to set something to cache. First set to local, then to remote
     *
     * @param string $cacheKey Cache key
     * @param mixed  $data     Data to store in cache
     *
     * @return bool|null
     */
    protected function setToCache($cacheKey, $data)
    {
        //If we have empty data, set the data to this constant
        if (empty($data)) {
            $data = self::EMPTY_RESULT_CACHE_VALUE;
        }

        /**
         * Was the data set to local cache?
         *
         * @var bool
         */
        $localSetBool = false;

        /**
         * Was the data set to remote?
         *
         * @var bool
         */
        $remoteSetBool = false;

        //Set to local
        if (isset($this->_local) && !empty($this->_local)) {

            if ($this->_local->set($cacheKey, $data, $localTTL)) { //Local data set success

                //Set an APC registry listener (if enabled)
                $this->setToRegistry($cacheKey, 'Local::set()');

                $localSetBool = true;
            }
        }

        //Set to remote
        if (isset($this->_remote) && !empty($this->_remote) && $shouldSetRemote) {

            if ($this->_remote->set($cacheKey, $data, $remoteTTL)) { //Remote data set success

                //Set a memcache registry listener
                $this->setToRegistry($cacheKey, 'Remote::set()');

                $remoteSetBool = true;

                //Stampede protection has been turned on
                if ($stampedeProtection) {

                    //Set stale data to cache. Stale data is set to remote only!
                    $this->_setStaleDataToCache($cacheKey, $data, $remoteTTL);
                }
            }
        }
        return ($remoteSetBool || $localSetBool) ? true : false;
    }

    /**
     * Delete from local and remote cache
     *
     * @param string $cacheKey Cache Key
     *
     * @return bool|null
     */
    protected function _deleteFromCache($cacheKey)
    {
        $didLocalDelete = false;
        $didRemoteDelete = false;

        //Delete from local
        if (isset($this->_local) && !empty($this->_local)) {
            $didLocalDelete = $this->_local->delete($cacheKey);
        }

        //Delete from remote
        if (isset($this->_remote) && !empty($this->_remote)) {
            $didRemoteDelete = $this->_remote->delete($cacheKey);
        }

        return ($didRemoteDelete || $didLocalDelete) ? true : false;
    }

    /**
     * This data is slightly stale from cache. This will only get called if there is a lock set.
     * A lock will only get set if stampede protection is turned on.
     *
     * @param string $cacheKey Cache key
     *
     * @return mixed | null
     * @throws CacheException
     */
    private function getStaleDataFromCache($cacheKey)
    {
        /**
         * Old cache key name
         *
         * @var string
         */
        $staleCacheKey = $this->_getStaleCacheKey($cacheKey);

        if (isset($this->_remote) && !empty($this->_remote)) {
            $data = $this->_remote->get($staleCacheKey);
        }

        return !empty($data) ? $data : null;
    }

    /**
     * Set an old copy of data to cache.
     * This will only ever get called if stampede protection is set to true.
     *
     * @param string $cacheKey  Cache Key
     * @param string $data      The data to store as an old version i.e. this data could potentially be stale
     * @param int    $remoteTTL Remote cache time to live
     *
     * @return bool
     */
    private function _setStaleDataToCache($cacheKey, $data, $remoteTTL)
    {
        /**
         * Old cache key name
         *
         * @var string
         */
        $staleCacheKey = $this->_getStaleCacheKey($cacheKey);

        /**
         * Did the old data set to remote cache?
         *
         * @var bool
         */
        $staleRemoteDataSetBool = false;

        if (isset($this->_remote) && !empty($this->_remote)) {

            $remoteCacheTTLSeconds = $remoteTTL + self::STALE_DATA_TTL_SECONDS;

            //If the data is empty, set the empty result key
            $data = !empty($data) ? $data : self::EMPTY_RESULT_CACHE_VALUE;

            $staleRemoteDataSetBool = (bool)$this->_remote->set($staleCacheKey, $data, $remoteCacheTTLSeconds);

            if ($staleRemoteDataSetBool) {

                $this->setToRegistry($cacheKey, 'Remote::staleSet()');
            }
        }

        return $staleRemoteDataSetBool;
    }

    /**
     * Set a value to the registry if its enabled
     *  The registry is a container that holds all data for the current execution thread
     *
     * @param string $cacheKey Cache Key for this entry
     * @param string $type     The type of cache i.e apc, mc, db etc..
     * @param Timer  $Timer    Query execution timer
     *
     * @return bool
     */
    protected function setToRegistry($cacheKey, $type, $Timer = null)
    {
        if ($this->debugCache == false) {
            return false;
        }

        $Registry = Registry::getInstance();

        $query = strstr($type, 'SQL') !== false ? $this->getQuery() : 'n/a';

        $queryTag = str_replace(array("\n", "/** ", " */"), '', $this->_generateQueryTag());

        if (!empty($Timer)) {
            $elapse = round($Timer->getTotalTime() * 1000, 3) . ' ms';
        } else {
            $elapse = 'n/a';
        }

        return $Registry->set(
            $queryTag,
            array(
                'type'      => $type,
                'cacheKey'  => $cacheKey,
                'cacheBool' => (int)$this->_cacheBool,
                'stampede'  => (int)$this->getStampedeProtection(),
                'localTTL'  => (int)$this->_localCacheTTLCommon,
                'remoteTTL' => (int)$this->_remoteCacheTTLCommon,
                'elapse'    => $elapse,
                'query'     => $query
            )
        );
    }

    /**
     * Check to see if a lock exists in the remote cache store
     *
     * @param string $cacheKey Name of this cache key
     *
     * @return bool
     * @throws CacheException
     */
    private function lockExists($cacheKey)
    {
        /**
         * Name of the lock cache key
         *
         * @var string
         */
        $lockKey = $this->_getLockCacheKey($cacheKey);

        /**
         * Does the lock exist
         *
         * @var bool
         */
        $lockExistsBool = false;

        if (isset($this->_remote) && !empty($this->_remote)) {

            $this->setToRegistry($cacheKey, 'MemCache::lockExists()');

            $lockExistsBool = $this->_remote->get($lockKey);
        }

        return (bool)$lockExistsBool;
    }

    /**
     * Remove a lock from the remote store
     *
     * @param string $cacheKey Name of this cache key
     *
     * @return bool
     * @throws CacheException
     */
    protected function _nixLock($cacheKey)
    {
        /**
         * Name of the lock key
         *
         * @var string
         */
        $lockKey = $this->_getLockCacheKey($cacheKey);

        /**
         * Did this lock successfully delete
         *
         * @var bool
         */
        $didLockDeleteBool = false;

        if (isset($this->_remote) && !empty($this->_remote)) {

            $this->setToRegistry($cacheKey, 'Remote::lockNix()');

            $didLockDeleteBool = $this->_remote->delete($lockKey);
        }

        return (bool)$didLockDeleteBool;
    }

    /**
     * Create a lock for a given cache key
     *
     * @param string $cacheKey Name of this cache key
     *
     * @return bool
     */
    protected function _createLock($cacheKey)
    {
        /**
         * Name of the lock key
         *
         * @var string
         */
        $lockKey = $this->_getLockCacheKey($cacheKey);

        /**
         * Did the lock creation succeed?
         *
         * @var bool
         */
        $didCreateLockBool = false;

        if (isset($this->_remote) && !empty($this->_remote)) {

            $didCreateLockBool = $this->_remote->set($lockKey, $value = 1, self::LOCK_TTL_SECONDS);

            if ($didCreateLockBool) {

                $this->setToRegistry($cacheKey, 'Remote::lockCreate()');
            }
        }

        return (bool)$didCreateLockBool;
    }

    /**
     * Generate a cache key for this query, ensure its uniqueness
     *
     * @return bool
     * @throws CacheException
     */
    protected function _generateCacheKey()
    {
        $cacheKey = md5(Config::$_currentTagVersion . $this->getQuery());

        if (!empty($cacheKey)) {

            $this->_cacheKey = $cacheKey;
        }

        return !empty($this->_cacheKey) ? true : false;
    }

    /**
     * Get an old cache key name
     *
     * @param string $cacheKey Cache Key
     *
     * @return string
     */
    private function _getStaleCacheKey($cacheKey)
    {
        return 'old' . $cacheKey;
    }

    /**
     * Get a lock cache key name
     *
     * @param string $cacheKey Cache key
     *
     * @return string
     */
    private function _getLockCacheKey($cacheKey)
    {
        return 'lock' . $cacheKey;
    }

//    public function get($key)
//    {
//        return $this->cache->get($key);
//    }
//
//    public function set($key, $val, $ttl)
//    {
//        return $this->cache->set($key, $val, $ttl);
//    }
//
//    public function nix($key)
//    {
//        return $this->cache->delete($key);
//    }
//
//    public function has($key)
//    {
//        return $this->cache->has($key);
//    }
//
//    public function clearCache()
//    {
//        return $this->cache->clearCache();
//    }
}