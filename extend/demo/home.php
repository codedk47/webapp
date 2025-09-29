<?php
class webapp_extend_demo_home extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->title('Welcome');
	}
	function get_home()
	{
		$this->main->append('h1', 'Hello World!');
		$this->main->append('a', ['UI Test', 'href' => "?{$this->routename}/uitest"]);
	}
	function get_uitest(int $page = 1)
	{
		$this->title('WebApp Development UI Test');
		$this->meta_pageinfo('WebApp,Formwork,UI,Test', '内部用于测试和展示UI的页面');
		$fields = ['Field 1', 'Field 2', 'Field 3', 'Field 4'];
		$table = $this->main->table([$fields, $fields, $fields, $fields]);
		$table->fieldset(...$fields);
		$table->header('Table UI Disply');
		$table->bar->append('input', ['type' => 'search', 'placeholder' => 'Type keywork to search']);
		$table->bar->append('button', ['Normal']);
		$table->bar->append('button', ['Default', 'class' => 'default']);
		$table->bar->append('button', ['Primary', 'class' => 'primary']);
		$table->bar->append('button', ['Danger', 'class' => 'danger']);
		$table->bar->details_button_popup('Details Button Popup', [
			['Microsoft', [
				['Windows Server 2019 Data Center', '#'],
				['Windows Server 2003', '#'],
				['Windows 2000', '#'],
				['Windows 98', '#']
			]],
			['Apple macOS', [
				['Sequoia 15.6.1', '#'],
				['Sonoma 14.7.8', '#'],
				['Ventura 13.7.8', '#'],
				['Monterey 12.7.6', '#']
			]]

		]);
			//->atree([
			
		// 	['OpenSSL', [
		// 		['生成新的私钥', "?{$this->routename}/openssl-pkey-new"],
		// 		['生成新证书签名请求', "?{$this->routename}/openssl-csr-new"],
		// 		['签署并且生成x509证书', "?{$this->routename}/openssl-csr-sign-x509"],
		// 		['一键生成私钥和用户证书', "?{$this->routename}/openssl-zeroneta-user-cert"]
		// 	]],
		// 	['QRCode', [
		// 		['二维码创建', "?{$this->routename}/qrcode-create"],
		// 		['二维码读取', "?{$this->routename}/qrcode-reader"]
		// 	]],
		// 	['WebSocket 回显测试', "?{$this->routename}/websocket"],
		// 	['Base64 编码/解码', "?{$this->routename}/base64"],
		// 	['PHP 散列算法', "?{$this->routename}/php-hash"],
		// 	['JS 散列算法', "?{$this->routename}/js-hash"],
		// 	['生成随机密码', "?{$this->routename}/generate-password"],
		// 	['生成UUID', "?{$this->routename}/generate-uuid"],
		// 	['创建苹果书签', "?{$this->routename}/apple-mobile-webclip"],
		// 	])['class'] = 'webapp-tree';
		webapp_table::pagination($table->footer(), "?{$this->routename}/uitest,page:", $page, 9);

		$this->main->append('hr');

		// foreach( as $a)
		// {
		// 	$a['href'] = $a[1];
		// 	unset($a[1]);
		// 	$d->append('a', $a);
		// }
		$this->main->select([
			'dwdawd' => '大大大大大1大苏打',
			'aaaaaa' => '啊圣诞袜伟大伟大',
			'dwdawd' => 'dasd打到',
			'awdqdn' => '自行车自行车',
			'dwdawd' => '大王大大伟大',
			'asdwee' => '与空调口语课',
			'dwadaw' => 'iu啊原地复活收到',
			'asdwwa' => '发咯上的飞机哦i是的发',
		], true, 'dwdad', 'wwwwwwwwwwwwwww');
		

		// $this->main->details_listanchor('dwdawdawdawd', [
		// 	['生成新的私钥', "?{$this->routename}/openssl-pkey-new"],
		// 	['生成新证书签名请求', "?{$this->routename}/openssl-csr-new"],
		// 	['签署并且生成x509证书', "?{$this->routename}/openssl-csr-sign-x509"],
		// 	['一键生成私钥和用户证书', "?{$this->routename}/openssl-zeroneta-user-cert"]
		// ])['class'] = 'webapp-listmenu';


		$this->main->append('hr');


		$form = $this->main->form();
		$form->xml['class'] .= '-edge';
		$form->fieldset->text('Form UI Display');

		$form->fieldset();

		// $ds = $form->fieldset->details_button_menu('Details Menu');
		// //$ds['class'] = 'webapp-listmenu';
		// $ds['open'] = NULL;
		// $dl = $ds->append('ul');
		// $dl['class'] = 'webapp-listmenu';
		// $dl->append('li')->append('a', ['Microsoft Windows', 'href' => '#']);
		// $dl->append('li')->append('a', ['Apple OS', 'href' => '#']);
		// $dl->append('li')->append('a', ['Linux', 'href' => '#']);

		// $aa = $dl->append('li')->details_menu('Submenu');
		// $au = $aa->append('ul');
		// $au->append('li')->append('a', ['Windows 2003', 'href' => '#']);
		// $au->append('li')->append('a', ['Windows Server', 'href' => '#']);

		// $dl->append('li')->append('a', ['Windows 2003', 'href' => '#']);
		// $dl->append('li')->append('a', ['Windows Server', 'href' => '#']);

		$form->fieldset();
		$form->field('text', 'text', ['placeholder' => 'Text']);
		$form->field('number', 'number', ['placeholder' => 'Number']);
		$form->fieldset();
		$form->field('url', 'url', ['placeholder' => 'URL']);
		$form->fieldset();
		$form->field('email', 'email', ['placeholder' => 'E-mail']);
		$form->field('password', 'password', ['placeholder' => 'Password']);

		$form->fieldset();
		$form->field('week', 'week');
		$form->field('time', 'time');
		$form->fieldset();
		$form->field('date', 'date');
		$form->field('datetime-local', 'datetime-local');

		$form->fieldset();
		$form->field('range', 'range');
		$form->field('color', 'color');
		
		$form->fieldset();
		$form->field('file', 'file');
		$form->field('select', 'select', ['options' => $fields]);
		$form->button('Button', 'button');


		$form->fieldset();
		$form->field('aaaa', 'text');

		$form->fieldset();
		$form->field('asd', 'radio', ['options' => [
			'asdas' => '的的文化带ihd',
			'dwdwd' => '的的文啊化带ihd'
		]]);

		$form->field('asd', 'checkbox', ['options' => [
			'asdas' => '的伟大伟大为',
			'dwdwd' => '大卫轻轻的'
		]]);

		$form->field('kkk', 'radio', ['options' => [
			'asdas' => '我根本阿达',
			'dwdwd' => '阿尔法违法'
		], 'data-placeholder' => 'dawdawdawd']);

		$form->fieldset();
		$form->button('Submit', 'submit');
		$form->button('Reset', 'reset')['class'] = 'danger';



		

		// $dialog = $this->main->append('dialog', ['class' => 'webapp', 'open' => NULL]);
		// $dialog->append('header', 'dwdawd');
		// $dialog->append('footer', 'dwdawd');
	
	}
}