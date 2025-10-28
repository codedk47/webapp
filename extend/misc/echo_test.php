<?php
class webapp_extend_misc_echo_test extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->nav([
			['Home', [
				['Back to current home', "?{$this->routename}"],
				['Back to webapp home', '?']
			]],
			['FlexBox', "?{$this->routename}/flexbox"],
			['Form', "?{$this->routename}/form"],
			['JavaScript Action', "?{$this->routename}/jsa"],
			['QRCode', "?{$this->routename}/qrcode"],
			['FFMpeg', "?{$this->routename}/ffmpeg"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
			// ['QRCode', "?{$this->routename}/qrcode"],
		]);
	}
	function post_home()
	{
	}
	function get_home()
	{

		$localed = $this->webapp->locales($this->locale);
		$this->main->select([
			'zh-CN' => 'Chinese',
			'en' => 'English',
			'km-KH' => 'Khmer',
			'ja-JP' => 'Japanese',
			'ko' => 'Korean'
		])->selected($localed)['onchange'] = '$.cookie.refresh("locale",this.value)';
		$this->main->text("{$localed}: {$this['hello']}");
	}
	function get_flexbox()
	{
		$this->title('asd');
		//$this->search();
		$this->submenu([
			['asda', '#'],
			['asda', '#'],
			['asda', '#'],
			['asda', '#'],
			['asda', '#'],
		]);
		//$this->main->append('article', 123);
		// $this->main->append('input', ['type' => 'date']);

		// $this->main->flexbox(function($data)
		// {
	
		// 	$this->figure('/webapp/static/images/favicon.jpg', $data['name']);

		// }, $this->webapp->nfs(0,1)->node('MV2V7VEMI6PI'));

		//$this->main->text('asdasd');
		//static::form_sign_in($this->main);



	}
	function get_form()
	{

		$form = new webapp_form($this->main);

		$form->fieldset('aaaa');
		$form->field('t', 'text');


		$form->fieldset('aaaa');
		$form->field('m', 'checkbox', ['options' => [
			'dasd' => 'wwdddddddddddda',
			'weaeae' => 'wwakljij'
		]]);

		$form->fieldset();
		$form->button('submit');

	}

	function post_jsa()
	{
		$this->json();

		var_dump($this->input());
	}
	function get_jsa()
	{
		$this->main->append('div', ['asd', 'id' => 'ww', 'style' => 'background:silver']);
		$this->main->append('button', ['Image View',
			'onclick' => '$.imageview()'
		]);


		$this->main->append('button', ['Copy to clipboard',
			'onclick' => '$.copytoclipboard(this.textContent)'
		]);


		$this->main->append('button', [
			'Action',
			//'class' => 'webapp-pl-ptbs-fs20 hover:asd',
			'onclick' => 'return $.action(this)',
			'data-action' => "?{$this->routename}/jsa",
			'data-method' => 'post',
			//'data-body' => '',
			'data-message' => 'This message!',
			'data-confirm' => 'This confirm?',
			'data-prompt' => 'This prompt:text:value',
		]);
	}


	function get_qrcode(string $data = NULL)
	{
		if ($data)
		{
			$data = str_pad('!ZERONETA', 1024, '0', STR_PAD_LEFT);

			// $this->webapp->lib('deprecated_files/qrcode.php');
			// $qrcode = new QRCode($data);
			// $image = $qrcode->render_image();
			// imagepng($image);
			// imagedestroy($image);
			// return;

			$this->echo();
			$this->webapp->response_content_type("image/png");
			webapp_image::qrcode($this->webapp->qrcode($data))->png($this->webapp->buffer);
		}


		$this->main->append('img', ['src' => "?{$this->routename}/qrcode,data:asd"]);
	}

	function get_ffmpeg()
	{
		//$f = $this->webapp->ffmpeg('C:\Users\admin\Desktop\youtube\watch.mp4');

		//print_r( $f->resolution(998) );

		//$f->video('D:\wmhp\work\m\m.mp4', 720);

		//$f->m3u8('D:\wmhp\work\m', 540);

		//var_dump( $f->m3u8('D:\wmhp\work\m' ) );

		//var_dump( webapp_ffmpeg_interface::from_m3u8_save_as('http://127.0.0.1/m/play.m3u8', 'D:\wmhp\work\m/play.mp4') );
		

	}
	function get_crypto()
	{


		// $a = openssl_encrypt('abc', 'aes-128-gcm', webapp::key, OPENSSL_RAW_DATA, $iv = webapp::random(12), $tag);


		// var_dump(bin2hex($tag));


		// return;
		//$iv = random_bytes(12);
		//header('iv: ' . bin2hex($iv));

		$key = '1';
		header('key: ' . bin2hex($key));
		echo $this->webapp->encryptdata('妈的法克！', $key);

		// $key = 'fuck';
		// header('key: ' . bin2hex($key) );



		//$data = openssl_encrypt('妈的法克！', 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
		//var_dump(strlen($data), strlen('妈的法克！'));

		//var_dump( openssl_decrypt($data, 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag) );

		
		//var_dump(hash('xxh3', 'asd'));

		//echo $iv .$data . $tag;
		//echo bin2hex( $data . $tag);
		//var_dump(bin2hex($tag));
	}
}