<?php
/**
 * JwtEnforcer.php
 * @author cdyun(121625706@qq.com)
 * @date 2026/3/7 20:26
 */

declare (strict_types=1);

namespace Cdyun\ThinkphpJwt;

use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtEnforcer
{
    /**
     * 定义Access令牌类型
     * @var int
     */
    private const ACCESS_TYPE = 1;

    /**
     * 定义Refresh令牌类型
     * @var int
     */
    private const REFRESH_TYPE = 2;

    /**
     * 开启单点登录时,定义令牌客户端,支持多端单点登录,也支持单端单点登录
     * @var string
     */
    private const TOKEN_CLIENT = 'web';

    /**
     * 生成Access令牌和Refresh令牌
     * @param array $extend - 扩展数据
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    public static function generateToken(array $extend): array
    {
        if (!isset($extend['id'])) {
            throw new \RuntimeException('缺少全局唯一字段：id', 401014);
        }
        $config = self::getConfig();
        $config['access_exp'] = $extend['access_exp'] ?? $config['access_exp'];
        $config['refresh_exp'] = $extend['refresh_exp'] ?? $config['refresh_exp'];

        $extend['etag'] = self::getEtag($extend, true);
        $payload = self::generatePayload($config, $extend);
        $secretKey = self::getPrivateKey($config);
        $token = [
            'type' => 'Bearer',
            'expires' => $config['access_exp'],
            'etag' => $extend['etag'],
            'access_token' => self::makeToken($payload['access'], $secretKey, $config['alg'])
        ];

        $tagName = $extend['client'] ?? self::TOKEN_CLIENT;

        // 开启双Token
        if (isset($config['double_token']) && ($config['double_token'] === true)) {
            $refreshSecretKey = self::getPrivateKey($config, self::REFRESH_TYPE);
            $token['refresh_token'] = self::makeToken($payload['refresh'], $refreshSecretKey, $config['alg']);

            // 开启Refresh令牌轮换，需要缓存Refresh令牌状态
            if (isset($config['refresh_rolling']) && ($config['refresh_rolling'] === true)) {
                $maxExp = self::getRollingMaxExp();
                $endTime = self::calculateNextRunTime($maxExp['day'], $maxExp['hour']);
                CacheService::set($extend['etag'], $endTime, $tagName);
            }

        }

        // 开启单点登录
        if (isset($config['single_enable']) && ($config['single_enable'] === true)) {
            $key = self::getEtag($extend);
            CacheService::set($key, $extend['etag'], $tagName);
        }

        return $token;
    }

    /**
     * 获取配置config
     * @param string|null $name - 名称
     * @param $default - 默认值
     * @return mixed
     * @author cdyun(121625706@qq.com)
     */
    public static function getConfig(?string $name = null, $default = null): mixed
    {
        if (!is_null($name)) {
            return config('jwt.' . $name, $default);
        }
        return config('jwt');
    }

    /**
     * 获取令牌标识
     * @param array $extend - 扩展数据
     * @param bool $isSuffix - 是否添加时间戳
     * @return string
     * @author cdyun(121625706@qq.com)
     */
    private static function getEtag(array $extend, bool $isSuffix = false): string
    {
        $pre = self::getConfig('etag_pre', 'jwt:');
        $client = $extend['client'] ?? self::TOKEN_CLIENT;
        $uid = (string)$extend['id'];
        if ($isSuffix) {
            return sprintf('%s%s:%s:%s', $pre, $client, $uid, time());
        }
        return sprintf('%s%s:%s', $pre, $client, $uid);
    }

    /**
     * 生成令牌载体数据
     * @param array $config - 配置信息
     * @param array $extend - 扩展数据
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    private static function generatePayload(array $config, array $extend): array
    {
        $nowTime = time();
        $payload = [
            'iss' => $config['iss'], // 签发者
            'aud' => $config['iss'], // 接收该JWT的一方
            'iat' => $nowTime, // 签发时间
            'nbf' => $nowTime + (int)($config['nbf'] ?? 0), // 某个时间点后才能访问
            'exp' => $nowTime + (int)$config['access_exp'], // 过期时间
            'extend' => $extend // 自定义扩展信息
        ];
        $data['access'] = $payload;

        $payload['exp'] = $nowTime + (int)$config['refresh_exp'];
        $data['refresh'] = $payload;
        return $data;
    }

    /**
     * 根据签名算法获取【私钥】签名值
     * @param array $config - 配置信息
     * @param int $type - 令牌类型
     * @return string
     * @author cdyun(121625706@qq.com)
     */
    private static function getPrivateKey(array $config, int $type = self::ACCESS_TYPE): string
    {
        $isAccess = $type === self::ACCESS_TYPE;
        if (in_array($config['alg'], ['HS512', 'HS384', 'HS256'], true)) {
            return $isAccess ? $config['access_secret'] : $config['refresh_secret'];
        }

        return $isAccess ? $config['access_private_key'] : $config['refresh_private_key'];

    }

    /**
     * 生成令牌
     * @param array $payload - 令牌载荷信息
     * @param string $secretKey - 密钥
     * @param string $alg - 签名算法
     * @return string
     * @author cdyun(121625706@qq.com)
     */
    private static function makeToken(array $payload, string $secretKey, string $alg): string
    {
        return JWT::encode($payload, $secretKey, $alg);
    }

    /**
     * 获取缓存滚动最大过期时间配置
     * @return array{day: int, hour: int} 返回天数和小时配置
     * @author cdyun(121625706@qq.com)
     */
    private static function getRollingMaxExp(): array
    {
        $config = self::getConfig('cache_rolling_max_exp', []);
        $day = isset($config['day']) && is_int($config['day']) && $config['day'] > 0
            ? $config['day']
            : 30;
        $hour = isset($config['hour']) && is_int($config['hour']) && $config['hour'] >= 0 && $config['hour'] < 24
            ? $config['hour']
            : 3;
        return [
            'day' => $day,
            'hour' => $hour,
        ];
    }

    /**
     * 计算下一次执行时间戳
     * @param int $day 天数偏移（从当前日期开始计算）
     * @param int $hour 目标小时（0-23）
     * @return int 返回目标时间的时间戳
     * @author cdyun(121625706@qq.com)
     */
    private static function calculateNextRunTime(int $day, int $hour): int
    {
        $nowHour = (int)date('H');

        // 如果当前小时大于等于目标小时，需要额外增加一天
        $actualDay = $nowHour >= $hour ? $day + 1 : $day;

        // 构建时间字符串并转换为时间戳
        return strtotime("+{$actualDay} day {$hour}:00:00");
    }

    /**
     * 刷新令牌
     * @param array $_extend - 扩展数据
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    public static function refreshToken(array &$_extend = []): array
    {
        $token = self::getJwtToken();
        $config = self::getConfig();
        try {
            $extend = self::parseToken($token, self::REFRESH_TYPE);
            if (!empty($extend['extend'])) {
                $_extend = $extend['extend'];
            }
            $oldEtag = $extend['extend']['etag'];
            $tagName = $extend['extend']['client'] ?? self::TOKEN_CLIENT;
        } catch (SignatureInvalidException $signatureInvalidException) {
            throw new \RuntimeException('刷新令牌无效', 401021);
        } catch (BeforeValidException $beforeValidException) {
            throw new \RuntimeException('刷新令牌尚未生效', 401022);
        } catch (ExpiredException $expiredException) {
            throw new \RuntimeException('刷新令牌会话已过期，请再次登录！', 401023);
        } catch (\UnexpectedValueException $unexpectedValueException) {
            throw new \RuntimeException('刷新令牌获取的扩展字段不存在', 401024);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage(), 401025);
        }

        $extend['extend']['etag'] = self::getEtag($extend['extend'], true);
        $newToken['etag'] = $extend['extend']['etag'];

        $payload = self::generatePayload($config, $extend['extend']);
        $secretKey = self::getPrivateKey($config);
        $extend['exp'] = time() + $config['access_exp'];
        $newToken['access_token'] = self::makeToken($extend, $secretKey, $config['alg']);

        // 开启双Token
        if (isset($config['double_token']) && ($config['double_token'] === true)) {
            $newToken['refresh_token'] = $token;

            // 开启Refresh令牌轮换
            if (isset($config['refresh_rolling']) && ($config['refresh_rolling'] === true)) {
                $refreshSecretKey = self::getPrivateKey($config, self::REFRESH_TYPE);
                $newToken['refresh_token'] = self::makeToken($payload['refresh'], $refreshSecretKey, $config['alg']);

                $value = CacheService::pull($oldEtag);
                CacheService::set($payload['refresh']['extend']['etag'], $value, $tagName);
            }
        }

        // 开启单点登录
        if (isset($config['single_enable']) && ($config['single_enable'] === true)) {
            $key = self::getEtag($extend['extend']);
            CacheService::set($key, $newToken['etag'], $tagName);
        }
        return $newToken;
    }

    /**
     * 获取JWT令牌，请求头获取并兼容GET请求
     * @return string
     * @author cdyun(121625706@qq.com)
     */
    private static function getJwtToken(): string
    {
        $tokenField = self::getConfig('jwt_key', 'authorization');
        $authorization = request()->header($tokenField);
        if (!$authorization || 'undefined' == $authorization) {
            $isGet = self::getConfig('is_support_get');
            if (null === $isGet || false === $isGet) {
                throw new \RuntimeException('请求未携带' . $tokenField . '信息', 401000);
            }
            $authorization = request()->get($tokenField);
            if (empty($authorization)) {
                throw new \RuntimeException('请求未携带' . $tokenField . '信息', 401000);
            }
            $authorization = 'Bearer ' . $authorization;
        }

        if (2 != substr_count($authorization, '.')) {
            throw new \RuntimeException('非法的' . $tokenField . '信息', 401001);
        }

        if (2 != count(explode(' ', $authorization))) {
            throw new \RuntimeException('Bearer验证中的凭证格式有误，中间必须有个空格', 401000);
        }

        [$type, $token] = explode(' ', $authorization);
        if ('Bearer' !== $type) {
            throw new \RuntimeException('接口认证方式需为Bearer', 401000);
        }
        if (!$token || 'undefined' === $token) {
            throw new \RuntimeException('尝试获取的' . $tokenField . '信息不存在', 401000);
        }

        return $token;
    }

    /**
     * 解析令牌
     * @param string $token - 令牌
     * @param int $type - 令牌类型
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    private static function parseToken(string $token, int $type): array
    {
        $config = self::getConfig();
        $publicKey = self::ACCESS_TYPE == $type ? self::getPublicKey($config) : self::getPublicKey($config, self::REFRESH_TYPE);
        JWT::$leeway = $config['leeway'];

        $decoded = JWT::decode($token, new Key($publicKey, $config['alg']));
        $decodeToken = json_decode(json_encode($decoded), true);

        // 刷新令牌轮换验证
        if (self::REFRESH_TYPE == $type) {
            if (isset($config['refresh_rolling']) && ($config['refresh_rolling'] === true)) {
                $rs = CacheService::get($decodeToken['extend']['etag']);
                if (empty($rs)) {
                    throw new \RuntimeException('刷新令牌无效', 401011);
                }
                if ($rs < time()) {
                    CacheService::delete($decodeToken['extend']['etag']);
                    throw new \RuntimeException('身份验证会话已过期，请重新登录！', 401013);
                }
            }
        }
        // 单点登录
        if (isset($config['single_enable']) && ($config['single_enable'] === true)) {
            $etag = self::getEtag($decodeToken['extend']);
            $rs = CacheService::get($etag);
            if (empty($rs)) {
                throw new \RuntimeException('身份验证令牌无效', 401011);
            }
            if ($rs != $decodeToken['extend']['etag']) {
                CacheService::delete($decodeToken['extend']['etag']);
                throw new \RuntimeException('账号在其他地方登录，请重新登录！', 401013);
            }
        }
        return $decodeToken;
    }

    /**
     * 根据签名算法获取【公钥】签名值
     * @param array $config - 配置信息
     * @param int $type - 令牌类型
     * @return string
     * @author cdyun(121625706@qq.com)
     */
    private static function getPublicKey(array $config, int $type = self::ACCESS_TYPE): string
    {
        $isAccess = $type === self::ACCESS_TYPE;
        if (in_array($config['alg'], ['HS512', 'HS384', 'HS256'], true)) {
            return $isAccess ? $config['access_secret'] : $config['refresh_secret'];
        }

        return $isAccess ? $config['access_public_key'] : $config['refresh_public_key'];
    }

    /**
     * 清空指定端下的所有Token
     * @param string|array $tagName - 标签名称
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    public static function clearClientUser(string|array $tagName): bool
    {
        return CacheService::clear($tagName);
    }

    /**
     * 注销自己的token
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    public static function logoutSelfToken(): bool
    {
        $extend = self::getExtend();
        CacheService::delete(self::getEtag($extend));
        if (!empty($extend['etag'])) {
            CacheService::delete($extend['etag']);
        }
        return true;
    }

    /**
     * 注销某个用户的token，单端令牌状态或Refresh令牌状态
     * @param array $extend
     * @return bool
     * @author cdyun(121625706@qq.com)
     */
    public static function clearUserToken(array $extend): bool
    {
        $key = self::getEtag($extend);
        return CacheService::delete($key);
    }

    /**
     * 获取令牌扩展信息
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    public static function getExtend(): array
    {
        return (array)self::verify()['extend'];
    }

    /**
     * 验证令牌
     * @param int $type - 令牌类型
     * @param string|null $token - 令牌
     * @return array
     * @author cdyun(121625706@qq.com)
     */
    public static function verify(int $type = self::ACCESS_TYPE, ?string $token = null): array
    {
        $token = $token ?? self::getJwtToken();
        try {
            return self::parseToken($token, $type);
        } catch (SignatureInvalidException $signatureInvalidException) {
            throw new \RuntimeException('身份验证令牌无效', 401011);
        } catch (BeforeValidException $beforeValidException) {
            throw new \RuntimeException('身份验证令牌尚未生效', 401012);
        } catch (ExpiredException $expiredException) {
            throw new \RuntimeException('身份验证会话已过期，请重新登录！', 401013);
        } catch (\UnexpectedValueException $unexpectedValueException) {
            throw new \RuntimeException('获取的扩展字段不存在', 401014);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage(), 401015);
        }
    }

    /**
     * 获取令牌剩余有效时间
     * @param int $type - 令牌类型
     * @return int
     * @author cdyun(121625706@qq.com)
     */
    public static function getTokenExp(int $type = self::ACCESS_TYPE): int
    {
        $exp = (int)self::verify($type)['exp'] - time();
        return max($exp, 0);
    }

    /**
     * 获取指定令牌扩展内容字段的值
     * @param string $val
     * @return mixed
     * @author cdyun(121625706@qq.com)
     */
    public static function getExtendVal(string $val): mixed
    {
        return self::getExtend()[$val] ?? '';
    }
}