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
			['JavaScript Action', "?{$this->routename}/jsa"],
			['QRCode', "?{$this->routename}/qrcode"]
		]);
	}
	function post_home()
	{
	}
	function get_home()
	{
	}


	function post_jsa()
	{
		$this->json();

		var_dump($this->input());
	}
	function get_jsa()
	{
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
}