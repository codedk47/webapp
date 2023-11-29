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
	function report(string $question):bool
	{
		return $this->id && $this->webapp->mysql->reports->insert([
			'hash' => $this->webapp->random_hash(TRUE),
			'time' => $this->webapp->time,
			'userid' => $this->id,
			'clientip' => $this->webapp->iphex($this->webapp->request_ip(TRUE)),
			'question' => $question
		]);
	}
	//操作记录
	private function record(string $type, array $data, bool $result = FALSE):array
	{
		if ($this->id && $data)
		{
			[$time33, $fee, $ext] = match ($type)
			{
				'vip', 'coin', 'game', 'prod' => [$this->webapp->time, $data['price'], $data],
				'video' => [$this->webapp->hashtime33($data['hash']), $data['require'], [
					'hash' => $data['hash'], 
					'type' => $data['type']]],
				default => [$this->webapp->time, $data['fee'], $data]
			};
			$record = [
				'hash' => $this->id . $this->webapp->time33hash($time33, TRUE),
				'userid' => $this->id,
				'ctime' => $this->webapp->time,
				'mtime' => $this->webapp->time,
				'result' => $result ? 'success' : 'pending',
				'type' => $type,
				'log' => 'pending',
				'cid' => $this['cid'],
				'fee' => $fee,
				'ext' => json_encode($ext, JSON_UNESCAPED_UNICODE)
			];
			if (in_array($type, ['video', 'exchange']))
			{
				if ($this->webapp->mysql->records->insert($record))
				{
					return $record;
				}
			}
			else
			{
				if ($this->webapp->mysql->sync(fn() => $this->webapp->recordlog($this['cid'], match ($this['device'])
					{
						'android' => 'order_android',
						'ios' => 'order_ios',
						default => 'order'
					}) && $this->webapp->mysql->records->insert($record))) {
					return $record;
				};
			}
		}
		return [];
	}
	//用户（可选条件）表操作
	private function cond(string ...$conds):webapp_mysql_table
	{
		return $this->webapp->mysql->users(...[$conds
			? sprintf('WHERE id=?s %s LIMIT 1', array_shift($conds))
			: 'WHERE id=?s LIMIT 1', $this->id, ...$conds]);
	}
	function update(string $field, string $value):bool
	{
		return $this->id
			&& isset($this[$field])
			&& $this->webapp->redis->hSet("user:{$this->id}", $field, $value) === 0;
	}
	//余额提现
	function exchange(array $transfer):array
	{
		return $this->id && $this->webapp->mysql->sync(function($transfer) use(&$record)
		{
			return $this->cond()->update('balance=balance-?i', $transfer['fee']) === 1
				&& ($record = $this->record('exchange', ['vtid' => 'user_exchange'] + $transfer));
		}, $transfer) ? $record : [];
	}
	//头像地址
	function fid():string
	{
		return ($this['fid'] % 255) ? "/faces/{$this['fid']}" : sprintf('/face/%s?mask%s',
			$this->webapp->time33hash($this->webapp->hashtime33($this['id'])), $this['ctime']);
	}
	//修改头像
	function change_fid(int $faceid):bool
	{
		if ($this->id && $this->cond()->update('ctime=?i,fid=?i', $this->webapp->time, $faceid) === 1)
		{
			$this['fid'] = $faceid;
			return TRUE;
		}
		return FALSE;
	}
	//修改昵称
	function change_nickname(string $nickname):bool
	{
		if ($this->id && $this->cond()->update('ctime=?i,nickname=?s', $this->webapp->time, $nickname) === 1)
		{
			$this['nickname'] = $nickname;
			return TRUE;
		}
		return FALSE;
	}
	//修改性别（'male，female）
	function change_gender(string $gender):bool
	{
		if ($this->id && $this->cond()->update('ctime=?i,gender=?s', $this->webapp->time, $gender) === 1)
		{
			$this['gender'] = $gender;
			return TRUE;
		}
		return FALSE;
	}
	//修改简介
	function change_descinfo(string $descinfo):bool
	{
		if ($this->id && $this->cond()->update('ctime=?i,descinfo=?s', $this->webapp->time, $descinfo) === 1)
		{
			$this['descinfo'] = $descinfo;
			return TRUE;
		}
		return FALSE;
	}
	//绑定手机
	function bind_tid(string $tid):bool
	{
		if ($this->id && $this->cond('AND tid IS NULL')->update('tid=?s', $tid) === 1)
		{
			$this['tid'] = $tid;
			return TRUE;
		}
		return FALSE;
	}
	//观看行为
	function watch(string $hash):bool
	{
		if ($this->id && $this->webapp->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->update('view=view+1') === 1)
		{
			$historys = $this['historys'] ? str_split($this['historys'], 12) : [];
			if (is_int($index = array_search($hash, $historys, TRUE)))
			{
				array_splice($historys, $index, 1);
			}
			array_unshift($historys, $hash);
			$historys = join(array_slice($historys, 0, 50));
			return $this->cond()->update('watch=watch+1,historys=?s', $historys) === 1
				&& $this->update('historys', $this['historys'] = $historys);
		}
		return FALSE;
	}
	//清除
	function clear(string $action):bool
	{
		return match ($action)
		{
			'historys' => $this->cond()->update('historys=""') === 1
				&& $this->update('historys', $this['historys'] = ''),
			default => FALSE
		};
	}
	//观看记录
	function historys():array
	{
		$videos = [];
		foreach (str_split($this['historys'], 12) as $hash)
		{
			if ($video = $this->webapp->fetch_video($hash))
			{
				$videos[] = $video;
			}
		}
		return $videos;
	}

	//用户收藏的视频最多50个
	function favorites():array
	{
		return $this->id && $this['favorites'] ? str_split($this['favorites'], 12) : [];
	}
	//用户收藏视频 -1取消 0无操作 +1收藏
	function favorite(string $hash):int
	{
		if ($this->id && strlen($hash) === 12 && trim($hash, webapp::key) === '')
		{
			$result = 0;
			$favorites = $this->favorites();
			if (is_int($index = array_search($hash, $favorites, TRUE)))
			{
				--$result;
				array_splice($favorites, $index, 1);
			}
			else
			{
				++$result;
				$favorites[] = $hash;
			}
			if ($this->webapp->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->update('`view`=`view`+1,`like`=`like`+?i', $result) === 1)
			{
				$favorites = join(array_slice($favorites, -50));
				if ($this->cond()->update('ctime=?i,favorites=?s', $this->webapp->time, $favorites) === 1)
				{
					$this['favorites'] = $favorites;
					return $result;
				}
			}
		}
		return 0;
	}
	//用户上传的视频（只返回HASH）
	function videos(string $type, string $sync, int $page, int $size = 10):array
	{
		$data = $this->webapp->mysql->videos(...$sync === 'all'
			? ['WHERE userid=?s AND type=?s AND sync IN("finished","allow","deny") ORDER BY sort DESC', $this->id, $type]
			: ['WHERE userid=?s AND type=?s AND sync=?s ORDER BY sort DESC', $this->id, $type, $sync])
				->paging($page, $size);
		return $page > $data->paging['max'] ? [] : $data->column('hash');
	}
	//用户关注UP主（再次关注即可取消）
	function follow_uploader_user(string $id):bool
	{
		if (strlen($id) === 10 && trim($id, webapp::key) === '')
		{
			$result = 0;
			$followed_ids = $this['followed_ids'] ? str_split($this['followed_ids'], 10) : [];
			if (is_int($index = array_search($id, $followed_ids, TRUE)))
			{
				--$result;
				array_splice($followed_ids, $index, 1);
			}
			else
			{
				++$result;
				$followed_ids[] = $id;
			}
			//if ($this->webapp->mysql->users('WHERE id=?s AND uid!=0 LIMIT 1', $id)->update('follower_num=follower_num+?i', $result) === 1)
			//{
				$this->webapp->mysql->users('WHERE id=?s AND uid!=0 LIMIT 1', $id)->update('follower_num=follower_num+?i', $result);
				$followed_ids = join(array_slice($followed_ids, -50));
				if ($this->cond()->update('ctime=?i,followed_ids=?s', $this->webapp->time, $followed_ids) === 1)
				{
					$this['followed_ids'] = $followed_ids;
					return TRUE;
				}
			//}
		}
		return FALSE;
	}
	//获取关注的UP主上传影片（参数横竖影片）
	function follow_uploader_videos(string $type, int $page = 1, int $size = 10):array
	{
		if ($followed_ids = $this['followed_ids'] ? str_split($this['followed_ids'], 10) : [])
		{
			$videos = $this->webapp->mysql->videos('WHERE userid IN(?S) AND sync="allow" AND type=?s ORDER BY mtime DESC,hash ASC', $followed_ids, $type)->paging($page, $size);
			if ($page <= $videos->paging['max'])
			{
				return $videos->column('hash');
			}
		}
		return [];
	}
	//获取关注的UP主发布的帖子
	function follow_uploader_posts(int $page = 1, int $size = 10):array
	{
		if ($followed_ids = $this['followed_ids'] ? str_split($this['followed_ids'], 10) : [])
		{
			$videos = $this->webapp->mysql->comments('WHERE userid IN(?S) AND type="post" AND `check`="allow" ORDER BY mtime DESC,hash ASC', $followed_ids)->paging($page, $size);
			if ($page <= $videos->paging['max'])
			{
				return $videos->column('hash');
			}
		}
		return [];
	}
	//购买商品
	function buy_prod(string $hash):array
	{
		return $this->id
			&& $this->webapp->mysql->prods('WHERE hash=?s and count>0 LIMIT 1', $hash)->fetch($prod)
			&& $this->webapp->mysql->sync(function(array $prod) use(&$record)
			{
				return $this->webapp->mysql->prods('WHERE hash=?s LIMIT 1', $prod['hash'])->update('count=count-1,sales=sales+1') === 1
					&& ($record = $this->record(match (TRUE)
					{
						str_starts_with($prod['vtid'], 'prod_vtid_vip') => 'vip',
						str_starts_with($prod['vtid'], 'prod_vtid_coin') => 'coin',
						str_starts_with($prod['vtid'], 'prod_vtid_game') => 'game',
						default => 'prod'
					}, $prod));
			}, $prod) ? $record : [];
	}
	//已购买视频
	function buy_video_already(string $hash):bool
	{
		return $this->id
			&& $this->webapp->mysql->records('WHERE hash=?s LIMIT 1',
				$this->id . $this->webapp->time33hash($this->webapp->hashtime33($hash), TRUE))->fetch();
	}
	//通过金币购买视频
	function buy_video_by_coin(string $hash):bool
	{
		return $this->id
			&& $this->buy_video_already($hash) === FALSE
			&& $this->webapp->mysql->videos('WHERE hash=?s AND userid!=?s AND sync="allow" AND `require`>0 LIMIT 1', $hash, $this->id)->fetch($video)
			&& $this['coin'] >= $video['require']
			&& $this->webapp->mysql->sync(function() use($video)
			{
				return $this->record('video', $video, TRUE)
					&& $this->cond()->update('coin=coin-?i', $video['require']) === 1
					&& $this->webapp->mysql->videos('WHERE hash=?s LIMIT 1', $video['hash'])->update('sales=sales+1') === 1
					&& $this->webapp->mysql->users('WHERE id=?s LIMIT 1', $video['userid'])
						->update('balance=balance+?i', $video['require'] * 0.5) === 1;
			}) && is_int($this['coin'] -= $video['require']);
	}
	//通过观影券购买视频
	function buy_video_by_ticket(string $hash):bool
	{
		return $this->id
			&& $this->buy_video_already($hash) === FALSE
			&& $this->webapp->mysql->videos('WHERE hash=?s AND userid!=?s AND sync="allow" AND `require`>0 LIMIT 1', $hash, $this->id)->fetch($video)
			&& $this->webapp->mysql->sync(function() use($video)
			{
				$video['require'] = 0;
				return $this->record('video', $video, TRUE)
					&& $this->cond()->update('ticket=ticket-1') === 1
					&& $this->webapp->mysql->videos('WHERE hash=?s LIMIT 1', $video['hash'])->update('sales=sales+1') === 1;
			}) && is_int(--$this['ticket']);
	}
	//购买的视频数据（只返回HASH）
	function buy_videos(string $type, int $page, int $size = 10):array
	{
		$data = $this->webapp->mysql->records('WHERE userid=?s AND type="video" and ext->>"$.type"=?s ORDER BY mtime DESC', $this->id, $type)
			->select('ext->>"$.hash"')->paging($page, $size);
		return $page > $data->paging['max'] ? [] : array_column($data->all(), 'ext->>"$.hash"');
	}
	function comment_video(string $hash, string $content):bool
	{
		return $this->webapp->mysql->videos('WHERE hash=?s AND sync="allow" LIMIT 1', $hash)->array()
			&& $this->webapp->mysql->comments->insert([
				'hash' => $this->webapp->random_hash(FALSE),
				'phash' => $hash,
				'userid' => $this->id,
				'mtime' => $this->webapp->time,
				'ctime' => $this->webapp->time,
				'sort' => 0,
				'type' => 'video',
				'check' => 'pending',
				'count' => 0,
				'view' => 0,
				'title' => $this['nickname'],
				'images' => strstr($this->fid(), '?', TRUE),
				'content' => $content
		]);
	}
	//发起话题、帖子、回复
	function comment(string $phash, string $content, $type = 'reply', string $title = NULL, string $images = NULL, string $videos = NULL, bool $check = FALSE):bool
	{
		return $this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $phash)->update('`count`=`count`+1') === 1
			&& $this->webapp->mysql->comments->insert([
				'hash' => $this->webapp->random_hash(FALSE),
				'phash' => $phash,
				'userid' => $this->id,
				'mtime' => $this->webapp->time,
				'ctime' => $this->webapp->time,
				'sort' => 0,
				'type' => $type,
				'check' => $check ? 'allow' : 'pending',
				'count' => 0,
				'view' => 0,
				'title' => $type === 'reply' ? $this['nickname'] : $title,
				'images' => $type === 'reply' ? strstr($this->fid(), '?', TRUE) : $images,
				'videos' => $videos,
				'content' => $content]);
	}
	//兑换码
	function giftcode(string $code):bool
	{
		// return $this->id
		// && $this->webapp->mysql->prods('WHERE hash=?s AND count>0 AND price=0 LIMIT 1', $code)->fetch($prod)
		// && $this->webapp->mysql->sync(function(array $prod) use(&$record)
		// {
		// 	return $this->webapp->mysql->prods('WHERE hash=?s LIMIT 1', $prod['hash'])->update('count=count-1,sales=sales+1') === 1
		// 		&& ($record = $this->record(match (TRUE)
		// 		{
		// 			str_starts_with($prod['vtid'], 'prod_vtid_vip') => 'vip',
		// 			str_starts_with($prod['vtid'], 'prod_vtid_coin') => 'coin',
		// 			str_starts_with($prod['vtid'], 'prod_vtid_game') => 'game',
		// 			default => 'prod'
		// 		}, $prod));
		// }, $prod) ? $record : [];


		return FALSE;
	}

	static function create(webapp $webapp, array $user = [], ?bool &$created = NULL):?static
	{
		$userdata = [];
		$created = FALSE;
		do
		{
			if (isset($user['did'])
				&& is_string($did = $user['did'])
				&& strlen($did) === 16
				&& $webapp->mysql->users('WHERE did=?s LIMIT 1', $did)->fetch($userdata)) {
				break;
			} else $did = NULL;
			if (isset($user['tid'])
				&& is_string($tid = $user['tid'])
				&& preg_match('/^\d{8,16}$/', $tid)
				&& $webapp->mysql->users('WHERE tid=?s LIMIT 1', $tid)->fetch($userdata)) {
				break;
			} else $tid = NULL;
			$id = $webapp->random_time33();
			$device = $user['device'] ?? $webapp->request_device();
			if ($webapp->mysql->users->insert($userdata = [
				'id' => $webapp->time33hash($id, TRUE),
				'date' => date('Y-m-d', $webapp->time),
				'ctime' => $webapp->time,
				'mtime' => $webapp->time,
				'login' => 0,
				'watch' => 0,
				'lasttime' => $webapp->time,
				'lastip' => $webapp->iphex($webapp->request_ip(TRUE)),
				'device' => match (1) {
					preg_match('/pad/i', $device) => 'pad',
					preg_match('/iphone/i', $device) => 'ios',
					preg_match('/android/i', $device) => 'android',
					default => 'pc'},
				'balance' => 0,
				'expire' => $webapp->time,
				'coin' => 0,
				'ticket' => 0,
				'fid' => 1,
				'uid' => 0,
				'cid' => $webapp->cid($user['cid'] ?? NULL),
				'did' => $did,
				'tid' => $tid,
				'nickname' => $webapp->time33hash($id),
				'gender' => 'none',
				'descinfo' => '',
				'historys' => '',
				'favorites' => '',
				'followed_ids' => '',
				'follower_num' => 0,
				'video_num' => 0
			])) {
				$created = TRUE;
			}
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
	//游戏进入（游戏ID）
	function game_enter(int $id = 0):?string
	{
		return $this->id ? $this->webapp->game->enter($this->id, $id) : NULL;
	}
	//游戏余额
	function game_balance():int
	{
		return $this->id ? $this->webapp->game->balance($this->id) : 0;
	}
	//游戏提现
	function game_exchange(array $exchange, &$error):array
	{
		$result = [];
		$error = '用户信息错误！';
		while ($this->id)
		{
			if (isset($exchange['fee'],
				$exchange['account'],
				$exchange['account_type'],
				$exchange['account_name'],
				$exchange['account_tel'],
				$exchange['account_addr']) === FALSE) {
				$error = '缺少关键字段！';
				break;
			}
			foreach ($exchange as $datatype)
			{
				if (is_string($datatype) === FALSE)
				{
					$error = '字段类型错误！';
					break 2;
				}
			}
			$exchange['fee'] = intval($exchange['fee']);
			if ($exchange['fee'] < 1)
			{
				$error = '金额错误！';
				break;
			}
			if ($exchange['account_type'] === 'bank')
			{
				if (isset($exchange['account_bank']) === FALSE)
				{
					$error = '缺少银行名称！';
					break;
				}
			}
			$exchange['vtid'] = 'game_exchange';
			if ($this->webapp->game->transfer($this->id, -$exchange['fee'], $exchange['orderid']) === FALSE)
			{
				$error = '提现失败，远程错误！';
				break;
			}
			if (empty($result = $this->record('exchange', $exchange)))
			{
				$error = '提现失败，内部错误！';
				$this->webapp->game->transfer($this->id, $exchange['fee'], $exchange['orderid']);
			}
			$error = '';
			break;
		}
		return $result;
	}



}