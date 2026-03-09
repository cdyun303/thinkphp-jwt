<?php
// +----------------------------------------------------------------------
// | JWT配置文件
// +----------------------------------------------------------------------

return [
    // 是否开启双Token
    'double_token' => true,
    // 是否开启Refresh令牌轮换,默认 false,开启时，需要引入缓存处理Refresh令牌状态
    'refresh_rolling' => true,
    // Access令牌秘钥
    'access_secret' => 'kchka6x1sucafq7z01wfyy3n79ikhfa3',
    // Refresh令牌秘钥
    'refresh_secret' => 'npfcq3l78d9gcmy8p35ii82uyesam0qt',
    // JWT签发方标识
    'iss' => 'cdyun',
    // Access令牌过期时间(单位秒),默认3600（1小时）
    'access_exp' => 3600,
    // Refresh令牌过期时间(单位秒),默认 7 天
    'refresh_exp' => 604800,
    // JWT加密算法, HS256、HS384、HS512、RS256、RS384、RS512、ES256、ES384、ES512、PS256、PS384、PS512
    'alg' => 'HS256',
    // 令牌生效时间,默认 0 秒后
    'nbf' => 0,
    // 令牌允许的误差时间,默认 60 秒
    'leeway' => 60,
    // 是否开启单点登录,默认 false,开启时，需要引入缓存处理
    'single_enable' => true,
    // 缓存类型
    'cache_store' => 'redis',
    // 令牌标识前缀
    'etag_pre'=> 'jwt:',
    // Refresh令牌轮换状态持续时长（强制失效时间）
    'cache_rolling_max_exp' => [
        // 默认 30 天
        'day' => 30,
        // 默认 3 时,及凌晨3点整
        'hour' => 3,
    ],
    // 是否支持 get 请求获取令牌
    'is_support_get' => false,
    // get/header获取令牌字段名，默认 'authorization'
    'jwt_key' => 'authorization',
    //access令牌私钥
    'access_private_key' => <<<EOD
-----BEGIN RSA PRIVATE KEY-----
...
-----END RSA PRIVATE KEY-----
EOD,
    // access令牌公钥
    'access_public_key' => <<<EOD
-----BEGIN PUBLIC KEY-----
...
-----END PUBLIC KEY-----
EOD,
    //refresh令牌私钥
    'refresh_private_key' => <<<EOD
-----BEGIN RSA PRIVATE KEY-----
...
-----END RSA PRIVATE KEY-----
EOD,
    //refresh令牌公钥
    'refresh_public_key' => <<<EOD
-----BEGIN PUBLIC KEY-----
...
-----END PUBLIC KEY-----
EOD,
];
