<?php
class webapp_echo_simple_tools extends webapp_echo_html
{
	const passphrase = NULL;
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->script(['src' => '/webapp/extend/simple_tools/echo.js']);
	
		$dl = $this->aside->append('dl');
		$dl->append('dt', 'OpenSSL');
		foreach ([
			'pkey-new' => '生成新的私钥',
			'csr-new' => '生成新证书签名请求',
			'csr-sign-x509' => '签署并且生成一个x509证书',
			'zeroneta-user-x509' => '一键签署一个用户证书'
		] as $anchor => $function) {
			$dl->append('dd')->append('a', [$function, 'href' => "?simple-tools/openssl-{$anchor}"]);
		}

		$dl = $this->aside->append('dl');
		$dl->append('dt', '服务端（远程）PHP工具');
		foreach ([
			'hash' => 'Hash 散列算法',
			'qrcode-create' => 'QRCode 二维码创建',
			'apple-mobile-config' => '创建苹果书签'
		] as $anchor => $function) {
			$dl->append('dd')->append('a', [$function, 'href' => "?simple-tools/php-{$anchor}"]);
		}

		$dl = $this->aside->append('dl');
		$dl->append('dt', '客户端（本地）JS工具');
		foreach ([
			
			'js-base64' => 'Base64 编码/解码',
			'js-hash' => 'Hash 散列算法',
			'js-websocket' => 'WebSocket 回显测试',
			'js-generate-password' => '生成随机密码',
			'js-generate-uuid' => '生成UUID',
			'js-qrcode-reader' => 'QRCode 二维码读取'
		] as $anchor => $function) {
			$dl->append('dd')->append('a', [$function, 'href' => "?simple-tools/{$anchor}"]);
		}

	}
	// function message(string $message = NULL)
	// {
	// 	$this->main->append('h1', $message ?? '无效数据!');
	// }
	function get_home()
	{
		$this->main->append('h4', '欢迎使用基于WebApp框架开发的小工具，喜欢点个赞！');
		$this->main->append('h4', '⬅从这边选择需要的功能');
	}

	const openssl = [
		'config'			=> __DIR__ . '/openssl.cnf',
		'digest_alg'		=> 'sha256',
		'x509_extensions'	=> 'v3_ca',
		'req_extensions'	=> 'v3_req',
		'private_key_bits'	=> 2048,
		'private_key_type'	=> OPENSSL_KEYTYPE_RSA,
		'encrypt_key'		=> TRUE,
		'encrypt_key_cipher'=> OPENSSL_CIPHER_3DES
	];
	function form_openssl_pkey_new(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->xml['onsubmit'] = 'return tools.openssl_submit(this)';
		$form->field('passphrase', 'text', ['placeholder' => '可选密码保护']);
		$form->button('生成 KEY 内容', 'submit');
		$form->fieldset();
		$form->field('content', 'textarea', ['style' => 'font-family:consolas,monospace', 'placeholder' => '生成 KEY 内容显示在这里', 'rows' => 32, 'cols' => 64, 'readonly' => NULL]);
		$form->fieldset()->append('a', ['保存', 'href' => 'javascript:;', 'download' => 'private.key']);
		return $form;
	}
	function get_openssl_pkey_new()
	{
		$this->title('OpenSSL 生成新的私钥');
		$this->form_openssl_pkey_new($this->main);
	}
	function post_openssl_pkey_new()
	{
		$this->echo($this->form_openssl_pkey_new()->fetch($data)
			&& openssl_pkey_export(openssl_pkey_new(static::openssl),
				$output, $data['passphrase'] ? $data['passphrase'] : NULL, static::openssl) ? $output : openssl_error_string());
	}
	function form_openssl_csr_new(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->xml['onsubmit'] = 'return tools.openssl_submit(this)';
		$form->field('key', 'file', ['title' => '请选择私钥文件', 'accept' => '.key', 'required' => NULL]);
		$form->field('passphrase', 'text', ['placeholder' => '私钥密码']);
		$form->fieldset('必要字段');
		$form->field('common_name', 'text', ['placeholder' => '通用名称或主要域名', 'required' => NULL]);
		$form->button('生成 CSR 内容', 'submit');
		$form->fieldset('附加字段（可以自行删除不必要的信息）');
		$form->field('extdata', 'textarea', ['rows' => 8, 'cols' => 64]);
		$form->fieldset();
		$form->field('content', 'textarea', ['style' => 'font-family:consolas,monospace', 'placeholder' => '生成 CSR 内容显示在这里', 'rows' => 8, 'cols' => 64, 'readonly' => NULL]);
		$form->fieldset()->append('a', ['保存', 'href' => 'javascript:;', 'download' => 'private.csr']);
		return $form;
	}
	function get_openssl_csr_new()
	{
		$this->title('OpenSSL 生成新证书签名请求');
		$this->form_openssl_csr_new($this->main)->echo([
			'common_name' => $this->webapp->request_host(),
			'extdata' => <<<extdata
			countryName: 国家2位代码（大写字母）
			stateOrProvinceName: 地区（省）名称
			localityName: 城市名称
			organizationName: 注册名称
			organizationalUnitName: 组织（单位）名称
			emailAddress: 电子邮件
			extdata
		]);
	}
	function post_openssl_csr_new()
	{
		if ($this->form_openssl_csr_new()->fetch($data)
			&& count($key = $this->webapp->request_uploadedfile('key'))
			&& is_object($key = openssl_pkey_get_private('file://' . $key->file(), $data['passphrase']))) {
			$names = ['commonName' => $data['common_name']];
			foreach (explode("\n", $data['extdata']) as $line)
			{
				if (count($line = explode(':', $line, 2)) === 2)
				{
					$names[trim($line[0])] = trim($line[1]);
				}
			}
			$this->echo(openssl_csr_export(openssl_csr_new($names, $key, static::openssl), $output) ? $output : openssl_error_string());
		}
		else
		{
			$this->echo('数据效验失败！');
		}
	}
	function form_openssl_csr_sign_x509(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->xml['onsubmit'] = 'return tools.openssl_submit(this)';
		$form->fieldset('签名证书文件（可选，如果有私钥对应签名证书私钥）');
		$form->field('cer', 'file', ['title' => '签名证书文件', 'accept' => '.cer,.der,.pem']);
		$form->fieldset('私钥文件 / 私钥密码');
		$form->field('key', 'file', ['title' => '请选择私钥文件', 'accept' => '.key', 'required' => NULL]);
		$form->field('passphrase', 'text', ['placeholder' => '私钥密码']);
		$form->fieldset('证书签名请求文件 / 有效天数 / 序列号');
		$form->field('csr', 'file', ['title' => '请选择证书签名请求文件', 'accept' => '.csr', 'required' => NULL]);
		$form->field('days', 'number', ['min' => 7, 'max' => 0xfff, 'value' => 365, 'placeholder' => '有效天数', 'required' => NULL]);
		$form->field('serial', 'number', ['min' => 0, 'max' => 0xffffff, 'value' => 0, 'placeholder' => '序列号', 'required' => NULL]);
		$form->button('生成 CER 内容', 'submit');
		$form->fieldset('附加备用 IP / DNS');
		$form->field('ip', 'text', ['placeholder' => '备用IP用"," 分割多个']);
		$form->field('dns', 'text', ['placeholder' => '备用DNS用"," 分割多个']);
		$form->fieldset();
		$form->field('content', 'textarea', ['style' => 'font-family:consolas,monospace', 'placeholder' => '生成 CER 内容显示在这里', 'rows' => 16, 'cols' => 64, 'readonly' => NULL]);
		$form->fieldset()->append('a', ['保存', 'href' => 'javascript:;', 'download' => 'private.cer']);
		return $form;
	}
	function get_openssl_csr_sign_x509()
	{
		$this->title('OpenSSL 签署并且生成一个x509证书');
		$this->form_openssl_csr_sign_x509($this->main);
	}
	function post_openssl_csr_sign_x509()
	{
		if ($this->form_openssl_csr_sign_x509()->fetch($data)
			&& count($key = $this->webapp->request_uploadedfile('key'))
			&& count($csr = $this->webapp->request_uploadedfile('csr'))
			&& is_object($key = openssl_pkey_get_private('file://' . $key->file(), $data['passphrase']))) {
			$openssl = static::openssl;
			if (count($cer = $this->webapp->request_uploadedfile('cer')))
			{
				$openssl['x509_extensions'] = 'usr_cert';
				$cer = 'file://' . $cer->file();
				$altnames = [];
				foreach (array_filter(explode(',', $data['ip'])) as $i => $ip)
				{
					$altnames[] = "IP.{$i} = {$ip}";
				}
				foreach (array_filter(explode(',', $data['dns'])) as $i => $dns)
				{
					$altnames[] = "DNS.{$i} = {$dns}";
				}
				if ($altnames
					&& is_resource($config = tmpfile())
					&& fwrite($config, str_replace('[ alt_names ]',
						"subjectAltName = @alt_names\n[ alt_names ]\n" . join("\n", $altnames),
						file_get_contents(static::openssl['config'])))
					&& fflush($config)
					&& rewind($config)) {
					$openssl['config'] = stream_get_meta_data($config)['uri'];
				}
			}
			else
			{
				$cer = NULL;
			}
			$this->echo(openssl_x509_export(openssl_csr_sign('file://' . $csr->file(), $cer, $key,
				$data['days'], $openssl, $data['serial']), $output) ? $output : openssl_error_string());
		}
		else
		{
			$this->echo('数据效验失败！');
		}
	}
	function form_openssl_zeroneta_user_x509(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->xml['onsubmit'] = 'return tools.openssl_zeroneta(this)';
		$form->fieldset('通用名称 / 有效天数');
		$form->field('common_name', 'text', ['placeholder' => '通用名称或主要域名', 'required' => NULL]);
		$form->field('days', 'number', ['min' => 7, 'max' => 0xfff, 'value' => 365, 'placeholder' => '有效天数', 'required' => NULL]);
		$form->button('一键生成', 'submit');
		$form->fieldset('附加备用 IP / DNS');
		$form->field('ip', 'text', ['placeholder' => '备用IP用"," 分割多个']);
		$form->field('dns', 'text', ['placeholder' => '备用DNS用"," 分割多个']);
		$form->fieldset();
		$form->field('key', 'textarea', ['style' => 'font-family:consolas,monospace', 'placeholder' => '生成 KEY 内容显示在这里', 'rows' => 8, 'cols' => 64, 'readonly' => NULL]);
		$form->fieldset()->append('a', ['保存私钥', 'href' => 'javascript:;', 'download' => 'user.key']);
		$form->fieldset();
		$form->field('cer', 'textarea', ['style' => 'font-family:consolas,monospace', 'placeholder' => '生成 CER 内容显示在这里', 'rows' => 8, 'cols' => 64, 'readonly' => NULL]);
		$form->fieldset()->append('a', ['保存证书', 'href' => 'javascript:;', 'download' => 'user.cer']);
		return $form;
	}
	function get_openssl_zeroneta_user_x509()
	{
		$this->title('OpenSSL 一键签署一个用户证书');
		$this->form_openssl_zeroneta_user_x509($this->main)->echo([
			'ip' => '127.0.0.1',
			'dns' => 'localhost',
			'common_name' => $this->webapp->request_host()
		]);
	}
	function post_openssl_zeroneta_user_x509()
	{
		$openssl = static::openssl;
		$this->echo($this->form_openssl_zeroneta_user_x509()->fetch($data)
			&& is_object($key = openssl_pkey_new($openssl))
			&& openssl_pkey_export($key, $keydata, NULL, $openssl)
			&& is_object($csr = openssl_csr_new([
				'organizationalUnitName' => 'WebApp', 'commonName' => $data['common_name']], $key, $openssl))
			&& openssl_x509_export(openssl_csr_sign($csr,
				sprintf('file://%s/zeroneta.cer', __DIR__),
				openssl_pkey_get_private(sprintf('file://%s/zeroneta.key', __DIR__), '801462'),
				$data['days'], $openssl, $this->webapp->random_int(0, 0xffffff)), $cerdata)
				? "{$keydata}\n\n{$cerdata}" : "数据效验失败！\n\n" . openssl_error_string());
	}




	function form_hash(array $algos = [], webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		
		$form->field('content', 'textarea', ['placeholder' => '需要散列算法的原始内容', 'rows' => 8, 'cols' => 64]);
		$form->fieldset();
		$form->field('algos', 'select', ['options' => $algos]);
		$form->button('开始哈希', 'submit');
		$form->fieldset();
		$form->field('result', 'text', ['style' => 'width:36rem', 'placeholder' => '哈希结果会显示在这里', 'readonly' => NULL]);
		$form->button('')->setattr(['onclick' => 'tools.write_clipboard(this.previousElementSibling.value)'])->svg()->icon('copy');
		return $form;
	}
	function get_php_hash()
	{
		$this->title('Hash 散列算法');
		$form = $this->form_hash(array_combine($algos = hash_algos(), $algos), $this->main);
		$form->echo(['algos' => 'md5']);
		$form->xml['onsubmit'] = 'return tools.php_hash(this)';
		
		// $form = $this->main->form();
		// $form->xml['onsubmit'] = 'return tools.php_hash(this)';
		// $form->field('content', 'textarea', ['placeholder' => '需要散列算法的原始内容', 'rows' => 8, 'cols' => 64]);
		// $form->fieldset();
		// $form->field('method', 'select', ['options' => array_combine($algos = hash_algos(), $algos)]);
		// $form->button('开始哈希', 'submit');
		// $form->fieldset();
		// $form->field('result', 'text', ['style' => 'width:36rem', 'placeholder' => '哈希结果会显示在这里', 'readonly' => NULL]);
		// $form->button('')->setattr(['onclick' => 'tools.write_clipboard(this.previousElementSibling.value)'])->svg()->icon('copy');
		// $form->echo(['method' => 'md5']);
	}
	function post_php_hash()
	{
		$this->echo($this->form_hash(array_combine($algos = hash_algos(), $algos))->fetch($hash)
			? hash($hash['algos'], $hash['content'], FALSE)
			: '无效数据！');
	}


	function form_qrcode_create(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->xml['onsubmit'] = 'return tools.php_qrcode_create(this)';
		$form->fieldset('生成类型 / 纠错等级 / 像素大小');
		$form->field('type', 'select', ['options' => [
			'svg' => 'SVG(矢量图)',
			'png' => 'PNG(高质量)',
			'jpeg' => 'JPG(压缩失真)',
		
		], 'required' => NULL]);
		$form->field('ecc', 'select', ['options' => [
			'Low 7%',
			'Medium 15%',
			'Quartile 25%',
			'High 30%'
		], 'required' => NULL]);
		$form->field('size', 'select', ['options' => [
			2 => 2,
			4 => 4,
			8 => 8,
			10 => 10
		], 'required' => NULL]);
		$form->fieldset('内容信息');
		$form->field('data', 'textarea', ['placeholder' => '必要并且少于256个字符',
			'rows' => 4,
			'cols' => 64,
			'maxlength' => 256,
			'required' => NULL])->text($this->webapp->request_origin());

		$form->fieldset();
		$form->button('提交生成', 'submit');
		$form->fieldset()->append('img', ['name' => 'result']);

		$form->echo && $form->echo(['size' => 8]);
		return $form;
	}
	function get_php_qrcode_create()
	{
		$this->form_qrcode_create($this->main);
	}
	function post_php_qrcode_create()
	{
		$this->echo();
		if ($this->form_qrcode_create()->fetch($qrcode))
		{
			$draw = $this->webapp::qrcode($qrcode['data'], $qrcode['ecc']);
			if ($qrcode['type'] === 'svg')
			{
				$this->webapp->echo_svg()->xml->qrcode($draw, $qrcode['size']);
			}
			else
			{
				$this->webapp->response_content_type("image/{$qrcode['type']}");
				webapp_image::qrcode($draw, $qrcode['size'])->{$qrcode['type']}($this->webapp->buffer);
			}
		}
	}



	function get_js_base64()
	{
		$this->title('JS Base64 解码/编码');
		$form = $this->main->form();
		$form->xml['onsubmit'] = 'return tools.base64(this)';
		$form->field('content', 'textarea', ['placeholder' => '需要编码或者解码的原始内容', 'rows' => 8, 'cols' => 64]);
		$form->fieldset();
		$form->field('method', 'select', ['options' => ['Encode 编码', 'Decode 解码']]);
		$form->button('开始转换', 'submit');
		$form->fieldset();
		$form->field('result', 'textarea', ['placeholder' => '编码或者解码的结果显示在这里', 'rows' => 8, 'cols' => 64, 'readonly' => NULL]);
	}
	function get_js_hash()
	{
		$this->title('JS Hash 散列算法');
		$form = $this->main->form();
		$form->xml['onsubmit'] = 'return tools.hash(this)';
		$form->field('content', 'textarea', ['placeholder' => '需要散列算法的原始内容', 'rows' => 8, 'cols' => 64]);
		$form->fieldset();
		$form->field('method', 'select', ['options' => [
			'sha3-224' => 'Secure Hash Algorithm 3-224',
			'sha3-256' => 'Secure Hash Algorithm 3-256',
			'sha3-384' => 'Secure Hash Algorithm 3-384',
			'sha3-512' => 'Secure Hash Algorithm 3-512',
			'md5' => 'Message-Digest Algorithm 5']]);
		$form->button('开始哈希', 'submit');
		$form->fieldset();
		$form->field('result', 'text', ['style' => 'width:36rem', 'placeholder' => '哈希结果会显示在这里', 'readonly' => NULL]);
		$form->button('')->setattr(['onclick' => 'tools.write_clipboard(this.previousElementSibling.value)'])->svg()->icon('copy');
		$form->echo(['method' => 'md5']);
	}
	function get_js_websocket()
	{
		$this->title('JS WebSocket 回显测试');
		$form = $this->main->form();
		$form->xml['onsubmit'] = 'return tools.websocket(this)';
		$form->field('socket', 'text', ['style' => 'width:32rem', 'placeholder' => 'ws://或者wss://协议地址',
		'value' => 'wss://echo.websocket.org']);
		$form->button('开始连接', 'submit');
		$form->fieldset();
		$form->field('echo', 'textarea', ['placeholder' => '回显的消息显示在这里', 'rows' => 16, 'cols' => 64, 'readonly' => NULL]);
		$form->fieldset();
		$form->field('send', 'textarea', ['placeholder' => '请输入需要发送的消息', 'rows' => 2, 'cols' => 56, 'disabled' => NULL]);
		$form->button('发送');
	}
	function get_js_generate_password()
	{
		$this->title('JS 生成随机密码');
		$form = $this->main->form();
		$form->xml['onsubmit'] = 'return tools.generate_password(this)';
		$form->fieldset('包含字符 / 长度 / 数量');
		$form->field('charset', 'text', ['value' => webapp::key . '!@#$%^&*()_+{}[]:;<>,.?/', 'placeholder' => '字符集', 'required' => NULL]);
		$form->field('length', 'number', ['min' => 4, 'max' => 32, 'value' => 16, 'required' => NULL]);
		$form->field('count', 'number', ['min' => 1, 'max' => 10, 'value' => 4, 'required' => NULL]);
		$form->button('开始生成', 'submit');
		$form->fieldset();
		$form->field('result', 'textarea', ['style' => 'font-family:consolas, monospace', 'placeholder' => '生成结果会显示在这里', 'rows' => 10, 'cols' => 64, 'readonly' => NULL]);
	}
	function get_js_generate_uuid()
	{
		$this->title('JS 生成UUID');
		$form = $this->main->form();
		$form->xml['onsubmit'] = 'return tools.generate_uuid(this)';
		$form->fieldset('数量');
		$form->field('count', 'number', ['min' => 1, 'max' => 10, 'value' => 4, 'required' => NULL]);
		$form->button('开始生成', 'submit');
		$form->fieldset();
		$form->field('result', 'textarea', ['style' => 'font-family:consolas, monospace', 'placeholder' => '生成结果会显示在这里', 'rows' => 10, 'cols' => 64, 'readonly' => NULL]);
	}
	function get_js_qrcode_reader()
	{
		$this->title('JS QRCode 二维码读取');
		$this->script(['src' => '/webapp/extend/simple_tools/zxing-browser.min.js']);
		$form = $this->main->form();
		$form->field('qrcode', 'file', ['onchange' => 'tools.qrcode_reader(this.form,new ZXingBrowser.BrowserQRCodeReader())']);
		$form->fieldset('二维码结果如下：');
		$form->field('result', 'textarea', ['placeholder' => '读取错误或成功的内容在这里显示',
			'rows' => 8, 'cols' => 64, 'readonly' => NULL]);
	}
}