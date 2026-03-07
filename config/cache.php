<?php
/**
 * FateSpy — Caching System
 * Handles: Redis/Memcached caching for performance
 */
require_once __DIR__ . '/config.php';

class Cache
{
    private static ?object $cache = null;
    private static bool $enabled = true;
    
    public static function init(): void
    {
        if (!self::$enabled) return;
        
        try {
            // Try Redis first, fallback to Memcached
            if (extension_loaded('redis')) {
                self::$cache = new Redis();
                self::$cache->connect('127.0.0.1', 6379);
                self::$cache->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            } elseif (extension_loaded('memcached')) {
                self::$cache = new Memcached();
                self::$cache->addServer('127.0.0.1', 11211);
                self::$cache->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_PHP);
            } else {
                self::$enabled = false;
            }
        } catch (\Throwable $e) {
            self::$enabled = false;
        }
    }
    
    public static function get(string $key): mixed
    {
        if (!self::$enabled || !self::$cache) {
            return null;
        }
        
        try {
            if (self::$cache instanceof Redis) {
                return self::$cache->get('fatespy:' . $key);
            } else {
                return self::$cache->get('fatespy:' . $key);
            }
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if (!self::$enabled || !self::$cache) {
            return false;
        }
        
        try {
            if (self::$cache instanceof Redis) {
                return self::$cache->setex('fatespy:' . $key, $ttl, $value);
            } else {
                return self::$cache->set('fatespy:' . $key, $value, $ttl);
            }
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    public static function delete(string $key): bool
    {
        if (!self::$enabled || !self::$cache) {
            return false;
        }
        
        try {
            if (self::$cache instanceof Redis) {
                return self::$cache->del('fatespy:' . $key) > 0;
            } else {
                return self::$cache->delete('fatespy:' . $key);
            }
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    public static function flush(): bool
    {
        if (!self::$enabled || !self::$cache) {
            return false;
        }
        
        try {
            if (self::$cache instanceof Redis) {
                return self::$cache->delete(self::$cache->keys('fatespy:*'));
            } else {
                return self::$cache->flush();
            }
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    public static function remember(string $key, callable $callback, int $ttl = 3600): mixed
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }
    
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}

// Initialize cache on load
Cache::init();
?>