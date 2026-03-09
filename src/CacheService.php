<?php
/**
 * CacheService.php
 * @author cdyun(121625706@qq.com)
 * @date 2026/3/7 20:26
 */

declare (strict_types=1);

namespace Cdyun\ThinkphpJwt;

use Psr\SimpleCache\InvalidArgumentException;
use think\cache\Driver;
use think\facade\Cache;

class CacheService
{
    /**
     * 缓存标签，默认为token
     * @var string
     */
    private const DEFAULT_TAG = 'token';

    /**
     * 缓存
     * @param string $key - 缓存key
     * @param mixed $value - 缓存值
     * @param string $tagName - 标签名称
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    public static function set(string $key, mixed $value, string $tagName): bool
    {
        try {
            $config = JwtEnforcer::getConfig();
            $leeway = $config['leeway'] ?? 60;
            $exp = $config['refresh_exp'] ?? 60;
            $ttl = (int)$exp + (int)$leeway;

            return self::cacheHandler()->tag(self::getTag($tagName))->set($key, $value, $ttl);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    /**
     * 缓存句柄
     * @return Driver
     * @author cdyun(121625706@qq.com)
     */
    private static function cacheHandler(): Driver
    {
        $store = JwtEnforcer::getConfig('cache_store', 'redis');
        return Cache::store($store);
    }

    /**
     * 获取缓存标签
     * @param string $tagName
     * @return string[]
     * @author cdyun(121625706@qq.com)
     */
    private static function getTag(string $tagName): array
    {
        return [self::DEFAULT_TAG, $tagName];
    }

    /**
     * 获取缓存
     * @param string $key - 缓存key
     * @return mixed
     * @author cdyun(121625706@qq.com)
     */
    public static function get(string $key): mixed
    {

        try {
            return self::cacheHandler()->get($key);
        } catch (InvalidArgumentException $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    /**
     * 获取缓存并删除缓存
     * @param string $key
     * @return mixed
     * @author cdyun(121625706@qq.com)
     */
    public static function pull(string $key): mixed
    {
        return self::cacheHandler()->pull($key);
    }

    /**
     * 删除缓存
     * @param string $key
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    public static function delete(string $key): bool
    {
        try {
            return self::cacheHandler()->delete($key);
        } catch (InvalidArgumentException $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    /**
     * 清空指定标签下的缓存
     * @param string|array $tagName - 标签名称
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    public static function clear(string|array $tagName): bool
    {
        return self::cacheHandler()->tag($tagName)->clear();
    }

    /**
     * 清空所有缓存
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    public static function clearAll(): bool
    {
        return self::cacheHandler()->tag(self::DEFAULT_TAG)->clear();
    }
}