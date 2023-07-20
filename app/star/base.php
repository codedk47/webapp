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
	//获取源
	function fetch_origins():array
	{
		return [];
	}
	//获取所有视频
	function fetch_videos():iterable
	{
		foreach ($this->mysql->videos('ORDER BY mtime DESC') as $video)
		{
			$ym = date('ym', $video['mtime']);
			$video['cover'] = "/{$ym}/cover";
			$video['playm3u8'] = "/{$ym}/play";
			yield $video;
		}
	}
	//获取指定广告（位置）
	function fetch_ads(int $seat):array
	{
		$ads = [];
		foreach ($this->mysql->ads('WHERE seat=?i AND display="show"', $seat) as $ad)
		{
			$ads[$ad['hash']] = [
				'weight' => $ad['weight'],
				'imgurl' => "/news/{$ad['hash']}?{$ad['ctime']}",
				'acturl' => $ad['acturl']
			];
		}
		return $ads;
	}
	//获取开屏广告
	function fetch_adsplashscreen():array
	{
		return empty($ad = $this->random_weights($this->fetch_ads(0))) ? $ad : [
			'duration' => 5,
			'mask' => TRUE,
			'picture' => $ad['imgurl'],
			'support' => $ad['acturl'],
			'autoskip' => TRUE
		];
	}
	//获取标签（级别）
	function fetch_tags(int $level):array
	{
		return $this->mysql->tags('WHERE level=?i ORDER BY sort DESC', $level)->column('name', 'hash');
	}
	//获取产品
	function fetch_prods():array
	{
		$prods = [];
		foreach ($this->webapp->mysql->prods as $prod)
		{

		}
		return [];
	}


	//专题
	function fetch_subjects(string $tagid):array
	{
		$subjects = [];
		foreach ($this->mysql->subjects('WHERE tagid=?s ORDER BY sort DESC', $tagid) as $subject)
		{
			$subjects[$subject['hash']] = [
				'name' => $subject['name'],
				'style' => $subject['style'],
				'videos' => $subject['videos'] ? str_split($subject['videos'], 12) : []
			];
		}
		


		return $subjects;
	}


	//话题
	function fetch_topics()
	{

	}




	//标获取签（参数级别）


	//获取全部专题数据


	//专题获取视频数据



	// //获取首页展示数据结构
	// function topdata()
	// {
		
	// }


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

		print_r($this->fetch_subjects('ayiE'));
		//print_r($this->fetch_indexdata());
		// foreach($this->videos() as $v){
		// 	print_r($v);
		// };
	}

}