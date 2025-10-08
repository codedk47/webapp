# *webapp*
---
*`v4.7.1b (PHP >= 8.3)`*
- 它是一个全局配置的I/O路由对象
- 它可以嵌入在常驻内存型PHP逻辑服务端运行
- 它可以嵌入在其他框架内运行并不影响其他框架行为
- 它可以当作框架来运行构建简单WEB项目并没有太多的约束
- **建议有一定经验的程序员使用!**

### 让我们看一个简单的列子吧

```PHP
<?php
require 'webapp/webapp_stdio.php';
return new class extends webapp
{
	#默认的路由请求是 GET home,当然你也可以修改 webapp 默认的路由地址
	function get_home()
	{
		$this->echo('Hi Web!');
	}
};
```

*^^在浏览器上访问你刚刚编写的 php 来看看效果^^*

---
### 大概功能介绍
- Streams 的 TCP Client，包括 HTTP、SMTP、WebSocket
- SimpleXMLElement 的 DOM 操作，XML、SVG、HTML
- GdImage 扩展简单的图像处理，生成验证码、二维码
- HTML5 原生表单生成和验证输入
- MySQL 扩展和单表操作以及一些功能
- MySQL Admin 扩展，管理和维护 MySQL 数据库
- NFS 扩展，支持 Cloudflare R2、Amazon S3、Aliyun OSS
- 一套简单的 JavaScript 与服务端交互操作，以及一些常用函数