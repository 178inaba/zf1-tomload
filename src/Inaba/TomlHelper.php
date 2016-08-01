<?php

namespace Inaba;

use Memcache;
use Memcached;
use Exception;
use Yosymfony\Toml\Toml;

class TomlHelper
{
    /**
     * Helper instance.
     *
     * @var static
     */
    private static $instance;

    /**
     * Is use Memcached.
     *
     * @var bool
     */
    private $useMem = true;

    /**
     * Memcached server host.
     *
     * @var string
     */
    private $host = 'localhost';

    /**
     * Memcached server port.
     *
     * @var int
     */
    private $port = 11211;

    /**
     * Constructor.
     * Keep in private so as not new let from outside.
     * Can not override.
     *
     * @return void
     */
    final private function __construct()
    {
    }

    /**
     * Set instance of helper.
     *
     * @return static
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Clone does not allow.
     * Can not override.
     *
     * @return void
     */
    final public function __clone()
    {
        throw new Exception('Clone is not allowed against '.__CLASS__.'.');
    }

    /**
     * Get the is use Memcached.
     *
     * @return bool
     */
    public function getUseMem()
    {
        return $this->useMem;
    }

    /**
     * Set the is use Memcached.
     * Can method chain.
     *
     * @param  bool  $useMem
     * @return $this
     */
    public function setUseMem($useMem)
    {
        $this->useMem = $useMem;

        return $this;
    }

    /**
     * Get hostname of Memcached server.
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Set hostname of Memcached server.
     * Can method chain.
     *
     * @param  string  $host
     * @return $this
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Get port of Memcached server.
     *
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Set port of Memcached server.
     * Can method chain.
     *
     * @param  int  $port
     * @return $this
     */
    public function setPort($port)
    {
        $this->port = $port;

        return $this;
    }

    // TODO

    /**
     * Get toml value.
     *
     * @param  string|null  $keyChain
     * @return mixed
     */
    public function toml($keyChain = null)
    {
        $toml = [];

        $m = null;
        if ($this->useMem) {
            $m = $this->getMemcacheD();
        }

        $paths = _get_paths();
        if ($m === null) {
            $toml = _parse_toml($paths);
        } else {
            $maxUpdateTime = 0;
            foreach ($paths as $path) {
                $updateTime = filemtime($path);
                if ($maxUpdateTime < $updateTime) {
                    $maxUpdateTime = $updateTime;
                }
            }

            $mBaseKey = '178inaba/toml_helper:';
            $mTimeKey = __DIR__.':time';
            $mTomlKey = __DIR__.':toml';

            $memUpdateTime = @$m->get($mBaseKey.$mTimeKey);
            if ($memUpdateTime < $maxUpdateTime) {
                $toml = _parse_toml($paths);
                $m->set($mBaseKey.$mTomlKey, $toml);
                $m->set($mBaseKey.$mTimeKey, $maxUpdateTime);
            } else {
                $toml = @$m->get($mBaseKey.$mTomlKey);
                if ($toml === false) {
                    $toml = _parse_toml($paths);
                    $m->set($mBaseKey.$mTomlKey, $toml);
                }
            }
        }

        if ($keyChain === null) {
            return $toml;
        }

        $keys = explode('.', $keyChain);
        foreach ($keys as $key) {
            $toml = @$toml[$key];
        }

        return $toml;
    }

    /**
     * Get use memcached or memcache class.
     *
     * @return \Memcached|\Memcache|null
     */
    private function getMemcacheD()
    {
        $m = null;
        if (extension_loaded('memcached')) {
            $m = new Memcached('178inaba/toml_helper');
            if (empty($m->getServerList())) {
                $m->addServer($this->host, $this->port);
            }
        } elseif (extension_loaded('memcache')) {
            $m = new Memcache();
            $m->addServer($this->host, $this->port);
        }

        return $m;
    }

    private function parse_toml(array $paths)
    {
        $toml = [];

        foreach ($paths as $path) {
            $toml[basename($path, '.toml')] = Toml::Parse($path);
        }

        return $toml;
    }

    private function get_paths()
    {
        // get directory
        $tomlDir = getenv('TOML_DIR');
        if ($tomlDir === false) {
            // default
            $tomlDir = '../tomls';
        }

        return glob($tomlDir.'/*.toml');
    }
}
