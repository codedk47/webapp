<?php
class game
{
	private readonly webapp_client_http $api;
	function __construct(string $api,	//平台API地址
		private readonly string $aid,	//代理ID
		private readonly string $acc,	//API账号
		private readonly string $ase,	//参数加密秘钥
		private readonly string $key	//请求签名秘钥
	) {
		$this->api = new webapp_client_http($api, ['autoretry' => 1, 'timeout' => 8]);
	}
	function request(string $action, array $params = []):array
	{
		$query = [];
		foreach ($params as $key => $value)
		{
			$query[] = "{$key}={$value}";
		}
		$data = base64_encode(openssl_encrypt(join('&', $query), 'AES-128-ECB', $this->ase, OPENSSL_RAW_DATA));
		return $this->api->request('GET', "{$this->api->path}/{$action}?" . http_build_query([
			'a' => $this->acc,
			't' => $time = time(),
			'p' => $data,
			'k' => md5("{$data}{$time}{$this->key}")
		])) && is_array($body = $this->api->content()) ? $body : [];
	}
	//测试
	function ping():array
	{
		return $this->request('ping', ['text' => 'Hello PHP']);
	}
	//注册账号
	// function register(string $uid, string $channel):array
	// {
	// 	return $this->request('register', ['uid' => $uid, 'channel' => $channel]);
	// }
	//进入游戏，不用注册可以直接进入
	function enter(string $uid, int $game = 0):?string
	{
		return is_array($game = $this->request('enterGame', ['uid' => $uid, 'game' => $game]))
			&& $game['code'] === 0 ? $game['data']['gameUrl'] : NULL;
	}
	//强制登出玩家
	function kick(string $uid):bool
	{
		return $this->request('kick', ['uid' => $uid])['code'] === 0;
	}
	//余额，参数为空查商户，否则查用户余额
	function balance(string $uid = NULL):int
	{
		return $this->request(...$uid === NULL ? ['getAgentBalance'] : ['getBalance', ['uid' => $uid]])['data']['balance'] ?? 0;
	}
	//划拨
	function transfer(string $uid, float $credit, ?string &$orderid):bool
	{
		// Array
		// (
		// 	[code] => 0
		// 	[data] => Array
		// 		(
		// 			[status] => 1
		// 			[reason] => ok
		// 		)
		// )
		$orderid = $this->aid . '_' . (new DateTime)->format('YmdHisv');
		return is_array($status = $this->request('transfer', ['orderId' => "{$orderid}_{$uid}", 'uid' => $uid, 'credit' => $credit]))
			&& isset($status['data']['reason'])
			&& $status['data']['reason'] === 'ok';
	}
}