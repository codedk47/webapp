<?php
class user extends ArrayObject
{
	public readonly ?string $id;
	function __construct(private readonly webapp $webapp, array $user)
	{
		parent::__construct($user, ArrayObject::STD_PROP_LIST);
		$this->id = $user['id'] ?? NULL;
	}
	function sign_in():bool
	{
		return $this->id && $this->webapp->mysql->users('WHERE id=?s LIMIT 1', $this->id)->update([
			'lasttime' => $this->webapp->time,
			'lastip' => $this->webapp->iphex($this->webapp->ip)
		]) === 1;
	}
	//绑定手机
	function bind_tid(string $tid):bool
	{
		return $this->webapp->mysql->users('WHERE id=?s AND tid IS NULL LIMIT 1', $this->id)->update('tid=?s', $tid) === 1;
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
				



			});
		}
		return FALSE;
		// do
		// {
			
		// 	if ($prod['price'] > $this[])


		// } while (FALSE);
		// return FALSE;
	}


	function record()
	{

	}


	//购买视频
	function buy_video(string $hash):bool
	{
		return FALSE;
	}




	function test()
	{
		print_r($this->buy_prod('123456789012'));
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
			'name' => $webapp->time33hash($id),
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
class interfaces extends webapp
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



}