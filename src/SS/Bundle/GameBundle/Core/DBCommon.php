<?php

namespace SS\Bundle\GameBundle\Core;

use \PDO as PDO;
use \PDOStatement as PDOStatement;
use SS\Bundle\GameBundle\Exception\DBOException;

/**
 * Class DBCommon
 *
 * @package SS\Bundle\GameBundle\Core
 */
class DBCommon
{
    /**
     * If this is set to true, all caching will be ignored, and caches will be reh ydrated
     *
     * @var bool
     */
    public static $refreshCache = false;

    /**
     * Is caching turned on or off
     *
     * @var bool
     */
    protected $cacheBool;

    /**
     * Clients that extend this object should not be able to use it directly
     *
     * @var PDO
     */
    private $db;

    /**
     * Service container will create the correct type of cache object
     *
     * @var CacheCommon
     */
    protected $cache;

    /**
     * Query with replacement params populated
     *
     * @var string
     */
    protected $query;

    /**
     * Array of params to set on query
     *
     * @var array
     */
    protected $params;

    public function __construct(ConnectionCredentials $credentials, CacheCommon $cache, $cacheBool)
    {
        $this->db = new PDO(
            "mysql:host=" . $credentials->getHost() . ";dbname=" . $credentials->getDatabase() . ';port=' . $credentials->getPort(),
            $credentials->getUser(),
            $credentials->getPass(),
            array(
                PDO::ATTR_TIMEOUT            => "3",
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"
                /* PDO::MYSQL_ATTR_INIT_COMMAND => "SET @ENTITY_TIMESTAMP_TRIGGERS=FALSE" */
            )
        );

        $this->cache = $cache;
        $this->cacheBool = $cacheBool;
    }

    /**
     * Just execute a query
     *
     * @return bool
     * @throws DBOException
     * @note: Caching is not supported neither is it needed
     */
    public function query()
    {
        $sth = $this->getSth();
        return $sth->execute($this->params) ? true : $this->_throwException($sth);
    }

    /**
     * Load a single value from the DB
     * i.e. select
     *          id
     *      from
     *          user
     *      where
     *          name = 'Samir';
     *
     * @throws DBOException
     * @return mixed <int|bool|string|blob>
     */
    public function loadResult()
    {
        $cacheKey = $this->getCacheKey();

        if ($this->cacheBool) { //Caching is enabled

            //Attempt to get this from cache first
            $data = $this->cache->getFromCache($cacheKey);

            if ($data === null) { //Strict null! cache hit, from an empty query
                return array();
            } elseif ($data !== false && !empty($data)) { //Strict false! Cache hit, false is a miss and we have data
                return $data;
            }
        }

        if ($this->stampedeProtection) {
            //Lock the resource
            $this->_createLock($cacheKey);
        }

        $sth = $this->getSth();

        if (!$sth->execute($this->params)) {
            $this->_throwException($sth);
        }

        $resultArray = $sth->fetchAll();

        $row = array_shift($resultArray);

        if (!empty($row) && is_array($row)) {

            $row = array_values($row);

            $result = $row[0];

            //If caching is enabled
            if ($this->_cacheBool) {

                //Set it to local and remote cache
                $this->_setToCache($cacheKey, $result, $this->getLocalCacheTTL(), $this->getRemoteCacheTTL(), $this->getStampedeProtection());
            }

            //If the config setting to debug is on, store all queries in the registry
            $this->_setToRegistry($cacheKey, 'SQL::loadResult()', $Timer);

            if ($this->getStampedeProtection()) {

                //Unlock the resource
                $this->_nixLock($cacheKey);
            }

            return $result;

        } else {

            return null;
        }
    }

    /**
     * Get the PDOStatement to work with
     *
     * @throws DBOException
     * @return PDOStatement
     */
    private function getSth()
    {
        /** @var PDOStatement $sth */
        $sth = $this->db->prepare($this->query);

        if (!$sth) {
            $this->_throwException($sth);
        }
        $sth->setFetchMode(PDO::FETCH_OBJ);
        return $sth;
    }

    /**
     * Throw an Exception with the correct message and code, Use trace to figure out where it came from
     *
     * @param PDOStatement $sth PDO prepared statement object is where we can get the SQL specific error messages
     *
     * @throws DBOException
     * @return void
     */
    private function _throwException(PDOStatement $sth)
    {
        foreach (debug_backtrace() as $backtrace) {

            //Pull from the next node that is not this method and not this class
            if ($backtrace['function'] != __FUNCTION__ && $backtrace['class'] != __CLASS__) {
                $function = $backtrace['function'];
                $class = $backtrace['class'];
                $line = $backtrace['line'];
                break;
            }
        }

        $errorInfoArray = $sth->errorInfo();
        $exceptionMessage = $errorInfoArray[2];

        throw new DBOException($exceptionMessage);
    }

    /**
     * @param array $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * @param string $query
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        $sth = $this->getSth();
        $this->query = $sth->queryString;
        return $this->query;
    }

    /**
     * Generate a cache key for this query, ensure its uniqueness
     *
     * @return string
     * @throws CacheException
     */
    public function getCacheKey()
    {
        return md5($this->getQuery() . implode(!empty($this->params) ? $this->params : array()));
    }

    /**
     * @return boolean
     */
    public function getStampedeProtection()
    {
        return $this->stampedeProtection;
    }
}