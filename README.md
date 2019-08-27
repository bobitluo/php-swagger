php-swagger
===========

Introduction
------------

一个基于 [phpDocumentor](https://www.phpdoc.org/) 生成 [Swagger2.0](https://swagger.io/docs/specification/2-0/basic-structure/) json 的PHP文档生成器

Installation
------------

```bash
composer require bobitluo/php-swagger
```

Usage
-----

```php
$options = [ 
    'title' => 'API title',    // API系统标题
    'description' => 'API description',    // API系统描述
    'version' => '1.0.0',    // API版本号
    'host' => '{yourhost.com}',    // API系统域名
    'schemes' => [    // API支持的SCHEME列表
        'https',
    ],  
    'securityDefinitions' => [    // API支持的认证列表。无需验证时设置为[]。详情见：https://swagger.io/docs/specification/2-0/authentication/
        'ApiKeyAuth' => [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'Authorization',
        ],  
    ],  
    'security' => [    // API默认全局使用的认证定义。无需验证时设置为[]。详情见：https://swagger.io/docs/specification/2-0/authentication/
        [   
            'ApiKeyAuth' => [], 
        ],  
    ], 
    'controller_prefix' => '',    // 控制器类名前缀。默认值为''
    'controller_postfix' => '',    // 控制器类名后缀。默认值为''
    'action_prefix' => '',    // Action方法名前缀。默认值为空''
    'action_postfix' => '',    // Action方法名后缀。默认值为空''
];

\bobitluo\Php\Swagger\Options::getInstance( $options );
$directory = new \bobitluo\Php\Swagger\Directory('{your_controller_dir}', function($uriPath){
    return preg_replace('/(\/controllers)$/', '', $uriPath);
});
echo $directory->build();
```

PHP注释样例
----------

```php
/**
 * @package 用户
 */
class UserController {

    /** 
     * 登录
     *
     * 支持密码和验证码两种方式登录
     *
     * @package 用户
     * @http-method post
     *
     * @param string $login_type* 登录类型(password,sms_code) password
     * @param string $cellphone* 手机号 13800138000
     * @param string $password* 密码 123456
     *
     * @return json 200 成功
     *
     * 字段解释:
     *
     * 名称    | 类型 | 示例 | 描述 
     * ------- | ---- | ---- | ----
     * expires | int | 1565796956 | 凭证过期时间戳
     * type    | string | Bearer | 凭证类型
     * token   | string | xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx | 登录凭证
     *
     * 返回样例:
     *
     * ```
     * {
     *   "ret_code": 200,
     *   "ret_msg": "success",
     *   "result": {
     *     "expires": 1565796956,
     *     "type": "Bearer",
     *     "token": "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
     *   }
     * }
     * ```
     * @return json 401 认证失败
     */
    public function loginAction() {
	  ...
```

PHP注释描述
---------

| name           | description   | example      | comment   |
| :------------- | :------------ | :----------- | :-------- |
| title          | 接口标题 | 登录 | 建议简短 |
| descritpion    | 接口描述 | 支持密码和验证码两种方式登录 | 可多行 |
| @package        | 接口所在的分类 | @package 用户 | SwaggerUI中会根据分类分组显示。<br>可在Controller类注释中添加此类所有Action的默认分类 |
| @http-method    | 接口请求方法 | @http-method post | 目前仅较好的支持get, post |
| @param          | 接口参数 | @param string $cellphone* 手机号 13800138000 | 数据类型 参数名($开头,末尾加*表示必填) 参数描述 默认值 |
| @return         | 接口返回 | @return json 200 成功 <br><br> 字段解释: <br><br> 名称 \| 类型 \| 示例 \| 描述 <br> ------ \| ------ \| ------ \| ----- <br> expires \| int \| 1565796956 \| 凭证过期时间戳 <br> ... | @return 标签后紧跟接口返回类型(如：json, xml)，返回码，返回码描述。后续多行支持[Markdown](https://guides.github.com/features/mastering-markdown/)格式的内容 | 
