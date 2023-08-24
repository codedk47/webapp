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
			if ($this->webapp->mysql->records->insert($record = [
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
			])) return $record;
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
	//余额提现
	function exchange(array $transfer):array
	{
		return $this->id && $this->webapp->mysql->sync(function($transfer) use(&$record)
		{
			return $this->cond()->update('balance=balance-?i', $transfer['fee']) === 1
				&& ($record = $this->record('exchange', ['vtid' => 'user_exchange'] + $transfer));
		}, $transfer) ? $record : [];
	}
	// function incr_balance(int $fee):bool
	// {
	// 	if ($this->id && $this->cond()->update('balance=balance+?i', $fee) === 1)
	// 	{
	// 		$this['balance'] += $fee;
	// 		return TRUE;
	// 	}
	// 	return FALSE;
	// }
	// function incr_vip(int $day):bool
	// {
	// 	if ($this->id && $this->cond()->update('expire=IF(expire>?i,expire,?i)+?i',
	// 		$this->webapp->time, $this->webapp->time, $sec = $day * 86400) === 1) {
	// 		$this['expire'] += $sec;
	// 		return TRUE;
	// 	}
	// 	return FALSE;
	// }
	// function incr_coin(int $num):bool
	// {
	// 	if ($this->id && $this->cond()->update('coin=coin+?i', $num) === 1)
	// 	{
	// 		$this['coin'] += $num;
	// 		return TRUE;
	// 	}
	// 	return FALSE;
	// }
	// function incr_game(int $coin):bool
	// {
	// 	return FALSE;
	// }
	//刷新用户最后登录状态
	function sign_in():bool
	{
		return $this->id && $this->cond()->update([
			'lasttime' => $this->webapp->time,
			'lastip' => $this->webapp->iphex($this->webapp->ip)
		]) === 1;
	}
	function fid():string
	{
		return ($this['fid'] % 255) ? "/faces/{$this['fid']}" : sprintf('/face/%s?%s',
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
	//用户观看历史记录最多50个
	function historys():array
	{
		return $this->id && $this['historys'] ? str_split($this['historys'], 12) : [];
	}
	//用户增加历史记录
	function history(string $hash):bool
	{
		if ($this->id && strlen($hash) === 12 && trim($hash, webapp::key) === '')
		{
			$historys = $this->historys();
			if (is_int($index = array_search($hash, $historys, TRUE)))
			{
				array_splice($historys, $index, 1);
			}
			$historys[] = $hash;
			$historys = join(array_slice($historys, -50));
			if ($this->cond()->update('ctime=?i,historys=?s', $this->webapp->time, $historys) === 1)
			{
				$this->webapp->mysql->videos('WHERE hash=?s LIMIT 1')->update('view=view+1');
				$this['historys'] = $historys;
				return TRUE;
			}
		}
		return FALSE;
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
			if ($this->webapp->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->update('`like`=`like`+?i', $result) === 1)
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
		return $this->webapp->mysql->videos(...$sync === 'all'
			? ['WHERE userid=?s AND type=?s AND sync IN("finished","allow","deny") ORDER BY sort DESC', $this->id, $type]
			: ['WHERE userid=?s AND type=?s AND sync=?s ORDER BY sort DESC', $this->id, $type, $sync])
				->paging($page, $size)->column('hash');
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
			if ($this->webapp->mysql->users('WHERE id=?s LIMTI 1', $id)->update('followed_num=followed_num+?i', $result) === 1)
			{
				$followed_ids = join(array_slice($followed_ids, -50));
				if ($this->cond()->update('ctime=?i,followed_ids=?s', $this->webapp->time, $followed_ids) === 1)
				{
					$this['followed_ids'] = $followed_ids;
					return TRUE;
				}
			}
		}
		return FALSE;
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
		return array_column($this->webapp->mysql->records('WHERE userid=?s AND type="video" and ext->>"$.type"=?s ORDER BY mtime DESC', $this->id, $type)
			->select('ext->>"$.hash"')->paging($page, $size)->all(), 'ext->>"$.hash"');
	}

	//发起话题（帖子）
	function reply_topic(string $content, string $phash, string $title = NULL):bool
	{
		return $this->webapp->mysql->topics('WHERE hash=?s LIMIT 1', $phash)->update('`count`=`count`+`') === 1
			&& $this->webapp->mysql->topics->insert([
				'hash' => $this->webapp->random_hash(FALSE),
				'mtime' => $this->webapp->time,
				'ctime' => $this->webapp->time,
				'userid' => $this->id,
				'phash' => $phash,
				'title' => $title,
				'check' => 'pending',
				'count' => 0,
				'sort' => 0,
				'content' => $content]);
	}

	static function create(webapp $webapp, array $user = []):?static
	{
		$userdata = [];
		do
		{
			$did = isset($user['did']) ? substr(md5($user['did']), -16) : $webapp->did;
			if ($did && $webapp->mysql->users('WHERE did=?s LIMIT 1', $did)->fetch($userdata))
			{
				break;
			}
			$tid = $user['tid'] ?? NULL;
			if ($tid && $webapp->mysql->users('WHERE tid=?s LIMIT 1', $tid)->fetch($userdata))
			{
				break;
			}
			$device = $user['device'] ?? $webapp->request_device();
			$nickname = $user['nickname'] ?? NULL;
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
				'fid' => 1,
				'uid' => $user['uid'] ?? 0,
				'cid' => $user['cid'] ?? $webapp->cid,
				'did' => $did,
				'tid' => $tid,
				'gender' => 'none',
				'descinfo' => '',
				'historys' => '',
				'favorites' => '',
				'followed_ids' => '',
				'follower_num' => 0,
				'video_num' => 0,
				'like_num' => 0
			];
			$i = 0;
			do
			{
				$id = $webapp->random_time33();
				$user['id'] = $webapp->time33hash($id, TRUE);
				$user['nickname'] = $nickname ?? $webapp->time33hash($id);
				if ($webapp->mysql->users->insert($user))
				{
					$userdata = $user;
					break;
				}
			} while ($webapp->mysql->errno === 1062 && ++$i < 3);
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