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
			yield $video;
		}
	}
}