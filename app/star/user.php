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
		return $this->id ? $this->webapp->signature($this->id, $this['cid']) : '';
	}
	function save()
	{

	}
	//用户（可选条件）表操作
	function cond(string ...$conds):webapp_mysql_table
	{
		return $this->webapp->mysql->users(...[$conds
			? sprintf('WHERE id=?s %s LIMIT 1', array_shift($conds))
			: 'WHERE id=?s LIMIT 1', $this->id, ...$conds]);
	}
	//刷新用户最后登录状态
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
	//用户关注UP主
	function follow_uploader_user(string $id):bool
	{
		return FALSE;
	}


	//点赞视频
	function like_video(string $hash):bool
	{

		return FALSE;
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
	//通过金币购买视频
	function buy_video_by_coin(string $hash):bool
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
	//通过观影券购买视频
	function buy_video_by_ticket(string $hash):bool
	{
		return FALSE;
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


	static function create(webapp $webapp, array $user = []):?static
	{
		$userdata = [];
		do
		{
			if (isset($user['did']) && $webapp->mysql->users('WHERE did=?s LIMIT 1', $user['did'])->fetch($userdata))
			{
				break;
			}
			if (isset($user['tid']) && $webapp->mysql->users('WHERE tid=?s LIMIT 1', $user['tid'])->fetch($userdata))
			{
				break;
			}
			$device = $user['device'] ?? $webapp->request_device();
			$user = [
				'date' => date('Y-m-d', $webapp->time),
				'ctime' => $webapp->time,
				'mtime' => $webapp->time,
				'lasttime' => $webapp->time,
				'lastip' => $webapp->iphex($webapp->ip),
				'device' => match (1) {
					preg_match('/pad/i', $device) => 'pad',
					preg_match('/iphone/i', $device) => 'ios',
					preg_match('/android/i', $device) => 'android',
					default => 'pc'},
				'balance' => 0,
				'expire' => $webapp->time,
				'coin' => 0,
				'ticket' => 0,
				'fid' => ord(random_bytes(1)),
				'uid' => 0,
				'cid' => $user['cid'] ?? $webapp->cid,
				'did' => $user['did'] ?? $webapp->did,
				'tid' => $user['tid'] ?? NULL,
				'historys' => '',
				'favorites' => '',
				'followed_ids' => '',
				'follower_num' => 0,
				'video_num' => 0,
				'like_num' => 0
			];
			do
			{
				$id = $webapp->random_time33();
				$user['id'] = $webapp->time33hash($id, TRUE);
				$user['nickname'] = $webapp->time33hash($id);
				if ($webapp->mysql->users->insert($user))
				{
					$userdata = $user;
					break;
				}
			} while ($webapp->mysql->errno === 1062);

		} while (FALSE);
		return new static($webapp, $userdata);
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