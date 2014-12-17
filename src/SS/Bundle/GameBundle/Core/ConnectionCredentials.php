<?php

namespace SS\Bundle\GameBundle\Core;

/**
 * Class ConnectionCredentials holds credentials for connecting to services
 *
 * @package SS\Bundle\GameBundle\Core
 */
class ConnectionCredentials
{
    /**
     * Username
     * @var string
     */
    protected $user;

    /**
     * Password
     * @var string
     */
    protected $pass;

    /**
     * Hostname for resource
     * @var string
     */
    protected $host;

    /**
     * Port number for resource
     * @var int
     */
    protected $port;

    /**
     * Database to connect to (optional)
     * @var string
     */
    protected $database;

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param string $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getPass()
    {
        return $this->pass;
    }

    /**
     * @param string $pass
     */
    public function setPass($pass)
    {
        $this->pass = $pass;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort($port)
    {
        $this->port = $port;
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param string $database
     */
    public function setDatabase($database)
    {
        $this->database = $database;
    }
}