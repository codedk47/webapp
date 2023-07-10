<?php
class user extends ArrayObject
{
	public readonly ?string $id;
	function __construct(private readonly webapp $webapp, array $user)
	{
		parent::__construct($user, ArrayObject::STD_PROP_LIST);
		$this->id = $user['id'] ?? NULL;
	}
	//获取token
	function __toString():string
	{
		return $this->webapp->signature($this->id, $this['cid']);
	}
	//用户（可选条件）表操作
	function cond(string ...$conds):webapp_mysql_table
	{
		return $this->webapp->mysql->users(...[$conds
			? sprintf('WHERE id=?s %s LIMIT 1', array_shift($conds))
			: 'WHERE id=?s LIMIT 1', $this->id, ...$conds]);
	}
	//率刷新用户最后登录状态
	function sign_in():bool
	{
		return $this->id && $this->cond()->update([
			'lasttime' => $this->webapp->time,
			'lastip' => $this->webapp->iphex($this->webapp->ip)
		]) === 1;
	}
	//修改昵称
	function change_nickname(string $nickname):bool
	{
		return $this->id && $this->cond()->update('nickname=?s', $nickname) === 1;
	}
	//绑定手机
	function bind_tid(string $tid):bool
	{
		return $this->id && $this->cond('AND tid IS NULL')->update('tid=?s', $tid) === 1;
	}
	//用户观看历史记录最多50个
	function historys():array
	{
		return $this->id && $this['historys'] ? str_split($this['historys'], 12) : [];
	}
	//用户收藏的视频最多50个
	function favorites():array
	{
		return $this->id && $this['favorites'] ? str_split($this['favorites'], 12) : [];
	}
	//用户收藏视频
	function favorite_video(string $hash):bool
	{
		if (strlen($hash) === 12)
		{
			$favorites = $this->favorites();
			return in_array($hash, $favorites, TRUE)
				|| $this->cond()->update('favorites=?s', $hash . join(array_slice($favorites, 0, 48))) === 1;
		}
		return FALSE;
	}
	//用户上传的视频（只返回HASH）
	function videos(int $page, int $size = 10):webapp_mysql_table
	{
		return $this->webapp->mysql->videos('WHERE userid=?s ORDER BY mtime DESC', $this->id)->paging($page, $size);
	}



	//点赞视频
	function like_video(string $hash):bool
	{

	}
	//购买商品
	function buy_prod(string $hash):bool
	{
		while ($this->id)
		{
			if ($this->webapp->mysql->prods('WHERE hash=?s and count>0 LIMIT 1', $hash)->fetch($prod) === FALSE)
			{
				break;
			}
			// if ($prod['price'] < $this['balance'])
			// {
			// 	break;
			// }
			return $this->webapp->mysql->sync(function() use($prod)
			{

				print_r($prod);



			});
		}
		return FALSE;
		// do
		// {
			
		// 	if ($prod['price'] > $this[])


		// } while (FALSE);
		// return FALSE;
	}

	//已购买视频
	function buy_video_already(string $hash):bool
	{
		return $this->webapp->mysql->records('WHERE userid=?s AND type="video" AND aid=?s LIMIT 1', $this->id, $hash)->fetch();
	}
	//购买视频
	function buy_video(string $hash):bool
	{
		return $this->buy_video_already($hash) === FALSE
			&& $this->webapp->mysql->videos('WHERE hash=?s and `require`>0 LIMIT 1', $hash)->fetch($video)
			&& $this->webapp->mysql->sync(function() use($video)
			{
				return is_string($this->record('video', $video['require'], $video['hash'], TRUE))
					&& $this->cond()->update('coin=coin-?i', $video['require']) === 1
					&& $this->webapp->mysql->videos('WHERE hash=?s LIMIT 1', $video['hash'])->update('sales=sales+1') === 1;
			});
	}
	//购买的视频数据
	function buy_videos(int $page, int $size = 100):array
	{
		return $this->webapp->mysql->records('WHERE userid=?s', $this->id)->paging($page, $size)->all();
	}
	function record(string $type, $fee, string $aid, bool $result = FALSE):?string
	{
		$hash = $this->webapp->random_hash(FALSE);
		$data = [
			'hash' => $hash,
			'userid' => $this->id,
			'result' => $result ? 'success' : 'pending',
			'ctime' => $this->webapp->time,
			'mtime' => $this->webapp->time,
			'type' => $type,
			'cid' => $this['cid'],
			'fee' => $fee,
			'aid' => $aid,
			'ext' => NULL
		];

		return $this->webapp->mysql->records->insert($data) ? $hash : NULL;
	}







	//测试创建上传视频
	function test_up_video(string $type = 'h'):bool
	{
		$duration = $this->webapp->random_int(300, 1800);
		return $this->webapp->mysql->videos->insert([
			'hash' => $this->webapp->random_hash(FALSE),
			'userid' => $this->id,
			'mtime' => $this->webapp->time,
			'ctime' => $this->webapp->time,
			'size' => 0,
			'sync' => 'allow',
			'type' => $type,
			'duration' => $duration,
			'preview' => intval($duration * 0.3) << 16 | 20,
			'require' => $this->webapp->random_weights([
				['weight' => 3, 'coin' => -1],
				['weight' => 1, 'coin' => 0],
				['weight' => 6, 'coin' => [3, 5, 6, 8, 12][$this->webapp->random_int(0, 4)]]
			])['coin'],
			'sales' => 0,
			'view' => 0,
			'like' => 0,
			'name' => $this->webapp->generatetext($this->webapp->random_int(12, 26))
		]);
	}
	static function create(webapp $webapp):static
	{
		$id = $webapp->random_time33();
		$user = [
			'id' => $webapp->time33hash($id, TRUE),
			'time' => $webapp->time,
			'lasttime' => $webapp->time,
			'lastip' => $webapp->iphex($webapp->ip),
			'device' => 'pc',
			'balance' => 0,
			'expire' => $webapp->time,
			'coin' => 0,
			'ticket' => 0,
			'viewtry' => 0,
			'fid' => ord(random_bytes(1)),
			'cid' => $webapp->cid,
			'did' => $webapp->did,
			'tid' => NULL,
			'nickname' => $webapp->time33hash($id),
			'historys' => '',
			'favorites' => ''
		];
		return new static($webapp, $webapp->mysql->users->insert($user) ? $user : []);
	}
	static function from_id(webapp $webapp, string $id):static
	{
		return new static($webapp, $webapp->mysql->users('WHERE id=?s LIMIT 1', $id)->array());
	}
	static function from_did(webapp $webapp, string $did):static
	{
		return new static($webapp, $webapp->mysql->users('WHERE did=?s LIMIT 1', $did)->array());
	}
}