# Thinkphp Jwt扩展

> Json web token 是为了在网络应用环境间传递声明而执行的一种基于JSON的开放标准（(RFC 7519)。该token被设计为紧凑且安全的，特别适用于分布式站点的单点登录（SSO）场景。

## 安装

```bash
composer require cdyun/thinkphp-jwt
```

## 特性
- ✅ 支持自定义扩展数据
- ✅ 支持单令牌和双令牌
- ✅ 开启双令牌时，支持是否开启刷新令牌轮换（需要使用缓存）
- ✅ 支持单点登录，单端单点模式和多端单点模式（需要使用缓存）
- ✅ 支持GET方式获取令牌
- ✅ 支持自定义访问令牌和刷新令牌过期时间
- ✅ 支持自定义加密算法, HS256、HS384、HS512、RS256、RS384、RS512、ES256、ES384、ES512、PS256、PS384、PS512
- ✅ 支持注销自己的令牌
- ✅ 支持注销指定用户的令牌
- ✅ 单点登录时，支持注销某端下的所有令牌

## 配置文件

配置文件路径：`config/jwt.php`

```php
return [
    // 是否开启双Token
    'double_token' => true,
    // 是否开启Refresh令牌轮换,默认 false。开启时，需要引入缓存处理Refresh令牌状态。建议开启
    'refresh_rolling' => false,
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
    'single_enable' => false,
    // 缓存类型,需要在自己的缓存配置文件cache.php中配置
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
```

## 使用方法及示例

### 生成 Token

```php
use Cdyun\ThinkphpJwt\JwtEnforcer;

// 扩展数据
$extend = [
    'id'=>303, // id字段必须存在
    'client'=>'mobile', // client客户端类型，非必须。用于单点登录功能，判断登录设备类型
    ... , // 扩展字段
]

// 生成Token
$jwt = JwtEnforcer::generateToken($extend);
return json($jwt);
```

- 输出（json格式）

```json
{
  "type": "Bearer",
  "expires": 3600,
  "etag": "jwt:web:303:1773218983",
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI...",
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUz..."
}

```

- 参数描述

| 参数 | 类型 | 描述 | 示例值 |
|--------|----------|----------|----------|
| type | string | Token 类型 | Bearer |
| expires | int | 凭证有效时间，单位：秒 | 3600 |
| etag | string | 令牌唯一标识 | jwt:web:303:1773218983 |
| access_token | string | 访问令牌 | XXXXXXXXXXXXXXXXXXXX |
| refresh_token | string | 刷新令牌 | XXXXXXXXXXXXXXXXXXXX |

### 刷新 Token

```php
use Cdyun\ThinkphpJwt\JwtEnforcer;

$jwt = JwtEnforcer::refreshToken();
return json($jwt);
```

- 输出（json格式）

```json
{
  "etag": "jwt:web:303:1773221888",
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI...",
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUz..."
}
```

### 验证 Token

```php
use Cdyun\ThinkphpJwt\JwtEnforcer;

$jwt = JwtEnforcer::verify();
return json($jwt);
```

- 输出（json格式）

```json
{
  "iss": "cdyun",
  "aud": "cdyun",
  "iat": 1773218983,
  "nbf": 1773218983,
  "exp": 1773222583,
  "extend": {
    "id": 303,
    "etag": "jwt:web:303:1773218983"
  }
}
```

### 获取令牌扩展信息

```php
use Cdyun\ThinkphpJwt\JwtEnforcer;

$jwt = JwtEnforcer::getExtend();
return json($jwt);
```

- 输出（json格式）

```json
{
  "id": 303,
  "etag": "jwt:web:303:1773218983"
}
```

### 获取扩展信息字段的值

```php
use Cdyun\ThinkphpJwt\JwtEnforcer;

$id = JwtEnforcer::getExtendVal('id');
return $id; // 303
```

### 获取令牌剩余有效时间

```php
use Cdyun\ThinkphpJwt\JwtEnforcer;

$exp = JwtEnforcer::getTokenExp();
return $exp; // 3492
```

### 注销自己的 Token

```php
use Cdyun\ThinkphpJwt\JwtEnforcer;

JwtEnforcer::logoutSelfToken();
```

### 注销某个用户 Token

> 在开启单点登录或 Refresh 令牌轮换时，可以使用此方法

```php
use Cdyun\ThinkphpJwt\JwtEnforcer;

$extend= [
    'id'=>303, // id字段必须存在
    'client'=>'mobile', // 生成令牌时，如果存在此字段，则此字段必须存在
]
JwtEnforcer::clearUserToken($extend);
```

### 清空指定端下的所有 Token

> 在开启单点登录或 Refresh 令牌轮换时，可以使用此方法

```php
use Cdyun\ThinkphpJwt\JwtEnforcer;

JwtEnforcer::clearClientUser('mobile');
```

## ⭐刷新令牌介绍

> - **必须启用双令牌**，在配置文件中 `double_token` 设置为 `true`。
> - 配置文件中 `refresh_rolling` ，表示是否启用Refresh 令牌轮换。
> - 在开启Refresh 令牌轮换时，需要引入缓存处理，需要配置 `cache_store`。
> - 在开启Refresh 令牌轮换时，支持配置 `cache_rolling_max_exp`，表示 Refresh令牌轮换状态持续时长（强制失效时间）。

### 使用建议

- 不需要每次更新：在大多数标准实现中，Access Token 过期时，客户端使用同一个 Refresh Token 去换取新的 Access Token，此时 Refresh Token 本身保持不变（只要它还没过期）。
- 推荐定期轮换：为了更高的安全性，许多现代系统会在每次使用 Refresh Token 换取新 Access Token 时，同时颁发一个新的 Refresh Token，并使旧的 Refresh Token 立即失效。这被称为“刷新令牌轮换”。

### 模式对比

#### 模式 A：刷新令牌静态

- **流程：**
1. 用户登录，获得 Access_A 和 Refresh_A。
2. Access_A 过期。
3. 客户端发送 Refresh_A 请求新令牌。
4. 服务端验证 Refresh_A 有效，返回新的 Access_B，继续使用 Refresh_A。

- **优点：**
1. 实现简单。
2. 用户体验好，长期无需重新登录。

- **缺点（安全风险）：**
1. 泄露风险高：如果 Refresh_A 被黑客窃取，只要它在有效期内，黑客可以无限次地生成新的 Access Token。即使原用户修改了密码或注销，只要黑客手里的 Refresh Token 没过期，攻击依然有效（除非你有全局黑名单机制）。
2. 无法感知异常：很难发现令牌是否被盗用。

#### 模式 B：刷新令牌轮换

- **流程：**
1. 用户登录，获得 Access_A 和 Refresh_A。
2. Access_A 过期。
3. 客户端发送 Refresh_A 请求新令牌。
4. 服务端验证 Refresh_A 有效且未被使用过。
5. 服务端返回新的 Access_B 和新的 Refresh_B。
6. 服务端立即使 Refresh_A 失效（标记为已使用或删除）。
7. 下次刷新必须使用 Refresh_B。
8. 在绝对过期时间到期后，会删除 Refresh_B，防止用户永远不重新认证。

- **优点：**
1. 自动检测盗用：如果黑客窃取了 Refresh_A 并先于真实用户使用它，当真实用户尝试使用 Refresh_A 时，服务端会发现它已经失效（因为被黑客用过了），从而可以触发安全警报（如强制所有设备下线、要求重新认证）。
2. 缩短攻击窗口：每个 Refresh Token 只能使用一次。
- **缺点：**
1. 实现稍复杂，需要服务端存储 Refresh Token 的状态（或使用短效签名+黑名单）。
2. 如果网络波动导致客户端发出了请求但没收到响应（例如发了 Refresh_A 但超时未收到 Refresh_B），客户端重试时可能会因为 Refresh_A 已失效而失败，需要处理这种“并发/重试”场景。

### 场景选择

| 场景 | 建议策略 | 理由 |
|--------|----------|----------|
| **高安全需求 (银行、支付、企业后台)** | **必须轮换 (模式 B)** | 防止令牌泄露后的长期潜伏，支持异常检测。 |
| **普通互联网应用 (社交、资讯)** | **建议轮换** 或 **长有效期 + 短Access** | 平衡安全与体验。如果不想做轮换，务必确保 Refresh Token 有较短的绝对有效期（如 7-14 天），并结合 IP 指纹校验。 |
| **IoT 设备 / 极低频交互** | **静态 (模式 A)** | 设备可能长时间离线，轮换可能导致令牌链断裂，需配合极长的有效期和设备绑定。 |

## ⭐单点登录介绍

> - 支持多端单点登录和单端单点登录，默认多端单点登录。
> - 在配置文件中设置 `single_enable` 为 `true`。
> - 在生成令牌时，扩展数据中添加 `client` 字段，表示当前登录的端，默认为 `web`。

### 多端单点

- 在配置文件中设置 `single_enable` 为 `true` 即可开启多端单点登录功能。

### 单端单点

- 在配置文件中设置 `single_enable` 为 `true` 。
- 必须在给不同的客户端生成令牌时，扩展数据中添加 `client` 字段，表示当前登录的端。


## 相关链接

- [Packagist 仓库](https://packagist.org/packages/cdyun/thinkphp-jwt)
- [GitHub 仓库](https://github.com/cdyun303/thinkphp-jwt)
- [问题反馈](https://github.com/cdyun303/thinkphp-jwt/issues)

# 版本要求

- php: >=8.0
- firebase/php-jwt: ^6.11||^7.0

# 许可证

MIT License