<?php
// +----------------------------------------------------------------------
// | JWT配置文件
// | 开启时Refresh令牌会跟随Access令牌刷新时自动轮换，否则仅生成一次过期失效。
// | Refresh令牌轮换须在服务端有状态存储（数据库或 Redis），以便进行轮换、废除和重用检测。
// | 无论是否轮换，Refresh Token 都应该有一个“绝对过期时间”（例如：无论怎么刷新，登录 30 天后必须重新输入密码）
// +----------------------------------------------------------------------

return [
    // 是否开启Refresh令牌
    'refresh_enable' => true,
    // 是否开启Refresh令牌轮换,默认 false,开启时，需要引入缓存处理Refresh令牌状态
    'refresh_Rolling' => false,
    // Access令牌秘钥
    'access_secret' => 'kchka6x1sucafq7z01wfyy3n79ikhfa3',
    // Refresh令牌秘钥
    'refresh_secret' => 'npfcq3l78d9gcmy8p35ii82uyesam0qt',
    // JWT签发方标识
    'iss' => 'cdyun',
    // Access令牌过期时间,默认 2 小时
    'access_exp' => 7200,
    // Refresh令牌过期时间,默认 7 天
    'refresh_exp' => 60480,
    // JWT加密算法, HS256、HS384、HS512、RS256、RS384、RS512、ES256、ES384、ES512、PS256、PS384、PS512
    'alg' => 'HS256',
    // 令牌生效时间,默认 0 秒后
    'nbf' => 0,
    // 令牌允许的误差时间,默认 60 秒
    'leeway' => 60,
    // 是否开启单点登录,默认 false,开启时，需要引入缓存处理
    'single_enable' => false,
    // 缓存令牌时间，单位：秒。默认 7 天
    'cache_jwt_exp' => 604800,
    // 缓存令牌前缀，默认 token:
    'cache_jwt_pre' => 'token:',
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
