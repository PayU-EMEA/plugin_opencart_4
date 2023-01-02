<?php

class PayUOauthCache implements \OauthCacheInterface
{
    const PAYU_CACHE_CONFIG_PREFIX = 'PAYU_';
    private $cache;

    public function __construct($cache) {
        $this->cache = $cache;
    }

    public function get($key)
    {
        $cache = $this->cache->get(self::PAYU_CACHE_CONFIG_PREFIX . $key);

        return empty($cache) ? null : unserialize($cache);
    }
    public function set($key, $value)
    {
        return $this->cache->set(self::PAYU_CACHE_CONFIG_PREFIX . $key, serialize($value));
    }
    public function invalidate($key)
    {
        return $this->cache->delete(self::PAYU_CACHE_CONFIG_PREFIX . $key);
    }
}
