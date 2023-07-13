<?php
class webapp_router_uploader
{
	private readonly array $userids;
	private readonly user $user;
	private readonly webapp_echo_html|webapp_echo_json $echo;
	function __construct(private readonly webapp $webapp)
	{
		$this->userids = $webapp->authorization(function($uid, $pwd)
		{
			return $this->webapp->mysql->uploaders('WHERE uid=?s AND pwd=?s LIMIT 1', $uid, $pwd)->fetch($uploader)
				&& $uploader['userids'] ? str_split($uploader['userids'], 10) : [];
		});
		$this->user = $this->userids
			? user::from_id($this->webapp, in_array($userid = $webapp->request_cookie('userid'), $this->userids, TRUE)
				? $userid : $this->userids[0]) : new user($this->webapp, []);
	}
	function __toString():string
	{
		return $this->echo instanceof webapp_echo_htmlmask
			? (string)$this->echo : $this->webapp->maskdata((string)$this->echo);
	}
	function html(string $title = 'Uploader'):webapp_echo_htmlmask
	{
		$this->echo = new webapp_echo_htmlmask($this->webapp);
		if ($this->echo->entry)
		{
			$this->echo->script(['src' => '/webapp/res/js/uploader.js']);
			$this->echo->script(['src' => '/webapp/app/star/uploader.js']);
		}
		$this->echo->title($title);
		if (in_array($this->webapp->method, ['get_home', 'get_auth', 'get_play']) === FALSE)
		{
			if ($this->user->id && count($users = $this->webapp->mysql->users('WHERE id IN(?S)', $this->userids)->column('nickname', 'id')))
			{
				$this->echo->nav([
					['用户信息', '?uploader/info'],
					['视频列表', '?uploader/videos'],
					['上传视频', '?uploader/uploading'],
					['测试', '?uploader/watch,hash:123', 'target' => 'sandbox'],
					['注销登录', 'javascript:top.location.reload(localStorage.removeItem("token"));', 'style' => 'color:maroon']
				])->ul->insert('li', 'first')->setattr(['style' => 'margin-left:1rem'])->select($users)->selected($this->user->id)
					->setattr(['onchange' => 'top.location.reload(top.document.cookie=`userid=${this.value}`)']);
			}
			else
			{
				$this->echo->main->append('h4', '至少需要绑定一个操作用户，联系客服进行绑定认证。');
			}
		}
		return $this->echo;
	}
	function json(array $data = []):webapp_echo_json
	{
		$this->echo = new webapp_echo_json($this->webapp, $data);
		$this->webapp->response_content_type('@application/json');
		return ($this->webapp)($this->echo);
	}
	function get_home()
	{
		$frame = $this->html()->xml->body->iframe;
		$frame['data-authorization'] = '?uploader/auth';
		$frame['data-load'] = '?uploader/uploading';

	}
	function get_auth(string $token = NULL)
	{
		if ($token === NULL)
		{
			webapp_echo_html::form_sign_in($this->html()->main)->xml['onsubmit'] = 'return top.uploader.auth(this)';
			return 200;
		}
		$this->json();
		if ($uploader = $this->webapp->authorize($token, fn($uid, $pwd) =>
			$this->webapp->mysql->uploaders('WHERE uid=?s AND pwd=?s LIMIT 1', $uid, $pwd)->array())) {
			// $this->webapp->mysql->uploaders('WHERE uid=?s LIMIT 1', $uploader['uid'])->update([
			// 	'lasttime' => $this->webapp->time,
			// 	'lastip' => $this->webapp->ip
			// ]);
			$this->echo['token'] = $token;
		}
	}
	function post_auth()
	{
		$this->json();
		do
		{
			if ((is_array($data = json_decode($this->webapp->request_maskdata(), TRUE))
				&& isset($data['username'], $data['password'])
				&& is_string($data['username'])
				&& is_string($data['password'])) === FALSE) {
				$this->echo->error('无效数据！');
				break;
			}
			if ($this->webapp['captcha_length'])
			{
				if ((isset($data['captcha_encrypt'], $data['captcha_decrypt'])
					&& is_string($data['captcha_encrypt'])
					&& is_string($data['captcha_decrypt'])
					&& $this->webapp->captcha_verify($data['captcha_encrypt'], $data['captcha_decrypt'])) === FALSE) {
					$this->echo->error('验证码无效！');
					break;
				}
			}
			$this->echo['token'] = $this->webapp->signature($data['username'], $data['password']);
		} while (FALSE);
	}
	function get_watch(string $hash)
	{
		$html = $this->html();
	}
	function get_info()
	{
		$html = $this->html();
		if ($this->user->id)
		{
			$form = $html->main->form();
			$form->fieldset('用户昵称：');
			$form->field('nickname', 'text');
			$form->fieldset('提现余额：');
			$form->field('balance', 'number', ['disabled' => NULL]);
			$form->fieldset->append('button', ['提现',
				'type' => 'button',
				'onclick' => 'top.framer("?uploader/exchange")'
			]);
			//print_r((array)$this->user);
			$form->echo($this->user->getArrayCopy());
		}
	}

	function get_videos(int $page = 1)
	{
		$html = $this->html();
		if ($this->user->id === NULL) return 401;

		$this->user->videos($page);


		$table = $html->main->table();
		$table->fieldset('封面', '信息');
		$table->header('视频列表');
		//$table->bar->append('button', '上传视频')[''];



	}
	

	function get_uploading()
	{
		$table = $this->html()->main->table();
		$table->fieldset('哈希', '大小（字节）', '类型', '名称', '上传进度');
		$table->header('上传视频（上传中请不要切换页面，直到上传完成。）');
		$table->bar->append('input', [
			'type' => 'file',
			//'accept' => 'video/mp4',
			'data-uploadurl' => "?uploading/{$this->user}",
			'onchange' => 'top.uploader.uploadlist(this.files,document.querySelector("main>table.webapp:first-child>tbody"))',
			'multiple' => NULL
		]);
		$table->bar->append('button', ['开始上传',
			'onclick' => 'top.uploader.uploading(document.querySelector("main>table.webapp:first-child"))'
		]);

		

		// $ctrl = $html->main->append('div')->append('span', ['style' => 'padding:.1rem;border:.1rem solid black;display:inline-block']);
		// $ctrl->append('input', ['type' => 'file', 'data-uploadurl' => '?uploader/test', 'multiple' => NULL]);
		// $ctrl->append('button', ['开始上传', 'onclick' => 'top.uploader.uploading(this.previousElementSibling,this)']);



		//$html->main->append('h4', '单次选择上传尽量保持6个以内文件');

		//$this->
	}





	function get_exchange()
	{
		$html = $this->html();
	}
}