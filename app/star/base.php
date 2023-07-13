<?php
require 'user.php';
class base extends webapp
{
	function ua():string
	{
		return $this->request_device();
	}
	function ip():string
	{
		return $this->request_ip();
	}
	function cid():string
	{
		return is_string($cid = $this->request_cookie('cid') ?? $this->query['cid'] ?? NULL)
			&& preg_match('/^\w{4,}/', $cid) ? substr($cid, 0, 4)
			: (preg_match('/CID\:(\w{4})/', $this->ua, $pattern) ? $pattern[1] : '0000');
	}
	function did():?string
	{
		return preg_match('/DID\:(\w{16})/', $this->ua, $pattern) ? $pattern[1] : NULL;
	}
	function howago(int $mtime):string
	{
		$timediff = $this->time - $mtime;
		return match (TRUE)
		{
			$timediff < 60 => '刚刚',
			$timediff < 600 => '10分钟前',
			$timediff < 3600 => '1小时前',
			$timediff < 10800 => '3小时前',
			$timediff < 21600 => '6小时前',
			$timediff < 43200 => '12小时前',
			$timediff < 86400 => '1天前',
			default => date('Y-m-d', $mtime)
		};
	}
	function user(string $id = '2lg7yhjGn_'):user
	{
		return $id ? user::from_id($this, $id) : new user($this,
			$this->authorize($this->request_authorization($type),
				fn($id) => $this->mysql->users('WHERE id=?s LIMIT 1', $id)->array()));
	}
	//获取所有视频
	function videos():iterable
	{
		foreach ($this->mysql->videos('ORDER BY mtime DESC') as $video)
		{
			$ym = date('ym', $video['mtime']);
			$video['cover'] = "/{$ym}/cover";
			$video['playm3u8'] = "/{$ym}/play";
			$video['timediff'] = $this->howago($video['mtime']);
			yield $video;
		}
	}
	//获取所有广告
	function ads():iterable
	{
		foreach ($this->mysql->ads('ORDER BY mtime DESC') as $ad)
		{
			yield $ad;
		}
	}
	//用户上传接口
	function post_uploading(string $token)
	{
		if ($acc = $this->authorize($token, fn($uid, $cid) => [$uid]))
		{

			$uploading = $this->request_uploading();



			$this->response_content_type('@application/json');
			$this->echo(json_encode(['a' => 1, 'b' => 2], JSON_UNESCAPED_UNICODE));

			return 200;
		}
		return 404;
	}

	function get_test(){

		foreach($this->videos() as $v){
			print_r($v);
		};
	}

}