<?php
class webapp_echo_simple_tools extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->script(['src' => '/webapp/extend/simple_tools/echo.js']);
	
		$dl = $this->aside->append('dl');
		$dl->append('dt', '服务端（远程）PHP工具');
		foreach ([
			'hash' => 'Hash 散列算法',
			'qrcode-create' => 'QRCode 二维码创建'
		] as $anchor => $function) {
			$dl->append('dd')->append('a', [$function, 'href' => "?simple-tools/{$anchor}"]);
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
	function message(string $message = NULL)
	{
		$this->main->append('h1', $message ?? '无效数据!');
	}
	function get_home()
	{
		$this->main->append('h4', '欢迎使用基于WebApp框架开发的小工具，喜欢点个赞！');
		$this->main->append('h4', '⬅从这边选择需要的功能');
	}



	function get_hash()
	{
		$this->title('Hash 散列算法');
		$form = $this->main->form();
		$form->xml['onsubmit'] = 'return tools.hash(this)';
		$form->field('content', 'textarea', ['placeholder' => '需要散列算法的原始内容', 'rows' => 8, 'cols' => 64]);
		$form->fieldset();
		$form->field('method', 'select', ['options' => array_combine($algos = hash_algos(), $algos)]);
		$form->button('开始哈希', 'submit');
		$form->fieldset();
		$form->field('result', 'text', ['style' => 'width:36rem', 'placeholder' => '哈希结果会显示在这里', 'readonly' => NULL]);
		$form->button('')->setattr(['onclick' => 'tools.write_clipboard(this.previousElementSibling.value)'])->svg()->icon('copy');
		$form->echo(['method' => 'md5']);

	}
	function form_qrcode_create(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->xml['target'] = '_blank';
		$form->fieldset('生成类型 / 纠错等级 / 像素大小');
		$form->field('type', 'select', ['options' => [
			'svg' => 'svg(矢量图)',
			'png' => 'png(高质量)',
			'jpg' => 'jpg(压缩失真)',
		
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
		$form->echo && $form->echo(['type' => 'png', 'size' => 8]);
		return $form;
	}
	function get_qrcode_create()
	{
		$this->form_qrcode_create($this->main);
	}
	function post_qrcode_create()
	{
		if ($this->form_qrcode_create()->fetch($qrcode))
		{

			$draw = $this->webapp::qrcode($qrcode['data'], $qrcode['ecc']);
			//$this->echo_svg()->xml->qrcode($draw, $this['qrcode_size']);
			$this->webapp->response_content_type("image/{$qrcode['type']}");
			webapp_image::qrcode($draw, $qrcode['size'])->{$qrcode['type']}($this->webapp->buffer);
			//print_r($qrcode);
		}


		// $this->form_qrcode_create()->fetch($qrcode);


		// if ($this['qrcode_echo'] && is_string($decode = $this->decrypt($encode)) && strlen($decode) < $this['qrcode_maxdata'])
		// {
		// 	if ($this->nonematch($decode . $type, TRUE))
		// 	{
		// 		$draw = static::qrcode($decode, $this['qrcode_ecc']);
		// 		in_array($type, ['png', 'jpeg'], TRUE)
		// 			? $this->response_content_type("image/{$type}")
		// 				|| webapp_image::qrcode($draw, $this['qrcode_size'])->{$type}($this->buffer)
		// 			: $this->echo_svg()->xml->qrcode($draw, $this['qrcode_size']);
		// 		$filename && $this->response_content_download($filename);
		// 		return 200;
		// 	}
		// 	return 304;
		// }
		// return 404;


		// var_dump([1]);

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
		$form->field('result', 'textarea', ['placeholder' => '生成结果会显示在这里', 'rows' => 10, 'cols' => 64, 'readonly' => NULL]);
	}
	function get_js_qrcode_reader()
	{
		$this->title('JS QRCode 二维码读取');
		$this->script(['src' => '/webapp/static/js/zxing-browser.min.js']);
		$form = $this->main->form();
		$form->field('qrcode', 'file', ['onchange' => 'tools.qrcode_reader(this.form,new ZXingBrowser.BrowserQRCodeReader())']);
		$form->fieldset('二维码结果如下：');
		$form->field('result', 'textarea', ['placeholder' => '读取错误或成功的内容在这里显示',
			'rows' => 8, 'cols' => 64, 'readonly' => NULL]);
	}
}