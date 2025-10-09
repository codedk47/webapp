# *webapp*
---
*`v4.7.1b (PHP >= 8.3)`*
### 简单说明
- 它是一个全局配置的 I/O 路由对象
- 它可以嵌入在常驻内存型 PHP 逻辑服务端运行
- 它可以嵌入在其他框架内运行并不影响其他框架行为
- 它可以当作框架来运行构建简单 Web 项目并没有太多的约束
- **建议有一定经验的程序员使用!**

---

### 大概功能介绍
- Streams 的 TCP Client 包括 HTTP、SMTP、WebSocket 协议
- SimpleXMLElement 的 DOM 操作，比如 XML、SVG、HTML
- Locale 区域设置、环境、多国语言实现支持
- GdImage 扩展简单的图像处理，生成验证码、二维码
- HTML5 原生表单生成和验证输入
- MySQL 扩展和单表操作以及一些功能
- MySQL Admin 扩展，管理和维护 MySQL 数据库
- NFS 扩展，支持 Cloudflare R2、Amazon S3、Aliyun OSS
- 一套简单的 JavaScript 与服务端交互操作，以及一些常用函数

#### 让我们看一个简单的列子

```PHP
<?php
require 'webapp/webapp_stdio.php';
return new class extends webapp
{
	#默认的路由请求是 GET home 当然你也可以修改 webapp 默认的路由地址
	function get_home()
	{
		$this->echo('Hi Web!');
	}
};
```

#### 使用 MySQL Admin 扩展

```PHP
<?php
require 'webapp/webapp_stdio.php';
require 'webapp/extend/mysql_admin/echo.php';
#webapp是单一文件入口为一个项目整体，下面这段代码也可以放在现有项目入口文件上使用
class webapp_router_mysql_admin extends webapp_extend_mysql_admin_echo{
    #这里不用写任何代码，只是路由此类，当然你也可以扩展此类实现自己的路由功能
    #当前扩展路由的名字是 mysql_admin 也就是 mysql-admin
    #在浏览器上访问 http://localhost/{入口PHP文件}?mysql-admin
}
return new class extends webapp;
```

*开始测试*
