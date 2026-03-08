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
    public const TOKEN_CLIENT = 'web';

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
        $payload = self::generatePayload($config, $extend);
        $secretKey = self::getPrivateKey($config);
        $token = [
            'type' => 'Bearer',
            'expires' => $config['access_exp'],
            'access_token' => self::makeToken($payload['access'], $secretKey, $config['alg'])
        ];

        // 开启Refresh令牌，并生成Refresh令牌
        if (isset($config['refresh_enable']) && ($config['refresh_enable'] === true)) {
            $refreshSecretKey = self::getPrivateKey($config, self::REFRESH_TYPE);
            $token['refresh_token'] = self::makeToken($payload['refresh'], $refreshSecretKey, $config['alg']);

            // 开启Refresh令牌轮换，需要引入缓存处理Refresh令牌状态
            if (isset($config['refresh_Rolling']) && ($config['refresh_Rolling'] === true)) {
                //待完善
            }

        }

        // 开启单点登录
        if (isset($config['single_enable']) && ($config['single_enable'] === true)) {
            //待完善
        }

        return $token;
    }
    public static function refreshToken(array &$_extend = []): array
    {
        $token = self::getJwtToken();
        $config = self::getConfig();
        try {
            $extend = self::parseToken($token, self::REFRESH_TYPE);
            if (!empty($extend['extend'])){
                $_extend = $extend['extend'];
            }
        } catch (SignatureInvalidException $signatureInvalidException) {
            throw new \RuntimeException('刷新令牌无效',401021);
        } catch (BeforeValidException $beforeValidException) {
            throw new \RuntimeException('刷新令牌尚未生效',401022);
        } catch (ExpiredException $expiredException) {
            throw new \RuntimeException('刷新令牌会话已过期，请再次登录！',401023);
        } catch (\UnexpectedValueException $unexpectedValueException) {
            throw new \RuntimeException('刷新令牌获取的扩展字段不存在',401024);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage(),401025);
        }
        $payload = self::generatePayload($config, $extend['extend']);
        $secretKey = self::getPrivateKey($config);
        $extend['exp'] = time() + $config['access_exp'];
        $newToken['access_token'] = self::makeToken($extend, $secretKey, $config['alg']);

        if (isset($config['refresh_enable']) && ($config['refresh_enable'] === true)) {
            $refreshSecretKey = self::getPrivateKey($config, self::REFRESH_TYPE);
            $newToken['refresh_token'] = self::makeToken($payload['refresh'], $refreshSecretKey, $config['alg']);
        }

        // 开启单点登录
        if (isset($config['single_enable']) && ($config['single_enable'] === true)) {
            //待完善
//            self::handleSingleDeviceToken($config, $extend, $newToken);
//            // 刷新令牌需要特殊处理，使用refreshToken而不是generateToken
//            if (!isset($config['refresh_disable']) || ($config['refresh_disable'] === false)) {
//                if (isset($config["cache_refresh_token_pre"]) && isset($newToken['refresh_token'])) {
//                    $client = $extend['extend']['client'] ?? self::TOKEN_CLIENT_WEB;
//                    $uid = (string)$extend['extend']['id'];
//                    RedisHandler::refreshToken($config["cache_refresh_token_pre"], $client, $uid, $config['refresh_exp'], $newToken['refresh_token']);
//                }
//            }
        }
        return $newToken;
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

        // 单点登录
        if (isset($config['single_enable']) && ($config['single_enable'] === true)) {
            $cacheTokenPre = $config['cache_jwt_pre'];
            $client = $decodeToken['extend']['client'] ?? self::TOKEN_CLIENT;
            //待完善
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
}