<?php
class webapp_router_upto extends webapp_echo_html
{
	const up = [
		'test' => ['pwd' => '486251'],
		'test1' => ['pwd' => '268451']
	];
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->title('Upto');
		$this->footer[0] = '';
		if (empty($this->webapp->upto()))
		{
			return $webapp->break($this->post_home(...));
		}

		$this->nav([
			['Home', '?upto'],
			['Upload', '?upto/upload'],
			//['注销', "javascript:void(document.cookie='upto=0',location.href='?upto');"]
		]);

		$this->xml->head->append('script', ['src' => '/webapp/res/js/backer.js']);
		// $this->footer->append('a', ['注销登录状态', 'href' => "javascript:void(document.cookie='unit=0',location.href='?unit');"]);
	}

	function post_home()
	{
		if ($this->webapp['request_method'] === 'post'
			&& webapp_echo_html::form_sign_in($this->webapp)->fetch($data)
			&& $this->webapp->upto($sign = $this->webapp->signature($data['username'], $data['password']))) {
			$this->webapp->response_cookie('upto', $sign);
			$this->webapp->response_location('?upto');
			return 302;
		}
		webapp_echo_html::form_sign_in($this->main);
		return 401;
	}
	function get_home()
	{
		
	}
	function get_upload()
	{
		$this->xml->head->append('script', <<<'JS'
let upwait = false;
function upload(form)
{
	if (upwait) return false;
	upwait = true;
	const progress = form.querySelector('progress');
	backer('?upto-upload', form['resources[]'].files, value => progress.value = value).then(status =>
	{
		upwait = false;
		alert('所有资源上传结束');
		location.reload();
	});
	return false;
}
JS);
		$form = new webapp_form($this->main, '?upto/upload');
		$form->xml['onsubmit'] = 'return upload(this)';
		$form->progress()['style'] = 'width:42rem';
		$form->fieldset();
		$form->field('resources', 'file', ['accept' => 'video/mp4', 'style' => 'width:42rem', 'required' => NULL, 'multiple' => NULL]);
		$form->fieldset();
		$form->button('Start uploading', 'submit');
	}
}