<?php
require '../../webapp_stdio.php';
new class extends webapp
{



	function html():webapp_echo_html
	{
		parent::echo_html('Tools');
		$this->echo->nav([
			['Home', '?'],
			['QRCode', '?make-qrcode']
		]);
		return $this->echo;
	}
	function get_home()
	{
		$this->html();

		



	}


	function form_make_qrcode(webapp_html $ctx = NULL):webapp_form
	{
		$form = new webapp_form($ctx ?? $this);
		$form->xml->setattr(['target' => '_blank']);
		$form->field('content', 'textarea', [
			'rows' => 8, 'cols' => 64
		]);
		$form->fieldset();
		$form->button('Submit', 'submit');
		return $form;
	}
	function get_make_qrcode()
	{
		$this->form_make_qrcode($this->html()->main);
	}
	function post_make_qrcode()
	{
		$content = $this->form_make_qrcode()->fetch($input, $error) ? $input['content'] : $error;
		webapp_image::qrcode(static::qrcode($content, $this['qrcode_ecc']), $this['qrcode_size'])->png();

	}
};