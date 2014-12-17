<?php
namespace SS\Bundle\GameBundle\Core;

/**
 * Standard registry to be used as application wide k => v store per request cycle
 * Warning: Data in this class will be stored in RAM. Consider space requirements vs performance benefits prior to implementing this
 * Currently this is being used by DBCommon as a logging mechanism for queries that get executed and APC/MemCache hits/misses
 *
 * @author spatel
 * @since  5/6/2013
 */
class Registry
{
    /**
     * Local singleton instance
     *
     * @var Registry
     */
    private static $_instance = null;

    /**
     * k => v pair of registry data
     *  k = key
     *  v = data (string, int, object etc...)
     *
     * @var array
     */
    private static $_registryArray = array();

    /**
     * Return an application wide Registry instance
     *
     * @return Registry
     */
    public static function getInstance()
    {
        if (!isset(static::$_instance)) {

            static::$_instance = new static();
        }

        return static::$_instance;
    }

    /**
     * Get a value from the registry
     *
     * @param string $key Key to check in registry
     *
     * @return mixed
     */
    public static function get($key)
    {
        if (array_key_exists($key, static::$_registryArray)) {

            return static::$_registryArray[$key];

        } else {

            return null;
        }
    }

    /**
     * Set an item in the registry
     *
     * @param string $key  Key (should be unique)
     * @param mixed  $data Data to set as value in array
     */
    public static function set($key, $data)
    {
        if (array_key_exists($key, self::$_registryArray)) {
            self::$_registryArray[$key][] = $data;
        } else {
            static::$_registryArray[$key][] = $data;
        }
    }

    /**
     * Get all registry entries
     *
     * @return array
     */
    public static function getAll()
    {
        return static::$_registryArray;
    }
}