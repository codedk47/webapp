<?php
class news_driver extends webapp
{
	function post_upapk()
	{
		if ($this->authorization)
		{
			$pwadir = __DIR__ . '/../../../pwa';
			if ($this->request_content_length() && file_put_contents($apkdst = "{$pwadir}/apk.zip", $this->request_content()) === $this->request_content_length())
			{
				$zip = new ZipArchive;
				return $zip->open($apkdst) && $zip->extractTo("$pwadir/dir") ? 200 : 500;
			}
		}
		return 401;
	}
	//控制端远程调用接口（请勿非本地调用）
	function post_sync(string $method)
	{
		if ($this->authorization)
		{
			if (method_exists($this, $method))
			{
				$params = $this->request_content();
				foreach ($params as &$value)
				{
					if (str_starts_with($value, '<') && str_ends_with($value, '>'))
					{
						$value = $this->xml($value);
					}
				}
				return current($this->{$method}(...$params)
					? [200, $this->echo('SUCCESS')]
					: [500, $this->echo('FAILURE')]);
			}
			return 404;
		}
		return 401;
	}
	//打包数据
	function packer(string $data):string
	{
		$bin = random_bytes(8);
		$key = array_map(ord(...), str_split($bin));
		$len = strlen($data);
		for ($i = 0; $i < $len; ++$i)
		{
			$data[$i] = chr(ord($data[$i]) ^ $key[$i % 8]);
		}
		return $bin . $data;
	}
	//随机散列
	function randhash(bool $care = FALSE):string
	{
		return $this->hash($this->random(16), $care);
	}
	//获取客户端IP
	function clientip():string
	{
		return $this->request_header('X-Client-IP')	//内部转发客户端IP
			?? $this->request_header('CF-Connecting-IP') //cloudflare客户端IP
			?? (is_string($ip = $this->request_header('X-Forwarded-For')) //标准代理客户端IP
				? explode(',', $ip, 2)[0] : $this->request_ip()); //默认请求原始IP
	}
	//获取客户端IP十六进制32长度
	function clientiphex():string
	{
		return $this->iphex($this->clientip);
	}
	//数据同步对象
	function sync():webapp_client_http
	{
		return (new webapp_client_http($this['app_syncurl'], ['autoretry' => 2]))->headers([
			'Authorization' => 'Bearer ' . $this->signature($this['admin_username'], $this['admin_password'], (string)$this['app_sid']),
			'X-Client-IP' => $this->clientip
		]);
	}
	//数据同步GET方法（尽量不要去使用）
	function get(string $router):string|webapp_xml
	{
		return $this->sync->goto("/index.php?{$router}")->content();
	}
	//数据同步POST方法（尽量不要去使用）
	function post(string $router, array $data = []):string|webapp_xml
	{
		return $this->sync->goto("/index.php?{$router}", [
			'method' => 'POST',
			'type' => 'application/json',
			'data' => $data
		])->content();
	}
	//数据同步DELETE方法（尽量不要去使用）
	function delete(string $router):string|webapp_xml
	{
		return $this->sync->goto("/index.php?{$router}", ['method' => 'POST'])->content();
	}
	//统一拉取数据方法
	function pull(string $router, int $size = 1000):iterable
	{
		for ($max = 1, $index = 0; $max > $index++;)
		{
			if (is_object($xml = $this->get("{$router},page:{$index},size:{$size}")))
			{
				$max = (int)$xml['max'];
				foreach ($xml->children() as $children)
				{
					yield $children;
				}
			}
		}
	}
	//是否显示这条广告
	function adshowable(array $ad):bool
	{
		do
		{
			if ($ad['timestart'] > $this->time || $this->time > $ad['timeend'])
			{
				break;
			}
			if ($ad['weekset'])
			{
				[$time, $week] = explode(',', date('Hi,w', $this->time));
				if (date('Hi', $ad['timestart']) > $time
					|| $time > date('Hi', $ad['timeend'])
					|| in_array($week, explode(',', $ad['weekset']), TRUE) === FALSE) {
					break;
				}
			}
			return $ad['count'] ? ($ad['click'] < abs($ad['count']) || $ad['view'] < $ad['count']) : TRUE;
		} while (0);
		return FALSE;
	}
	function adshowables(array $ads, bool $more = FALSE):array
	{
		$showable = array_filter($ads, $this->adshowable(...));
		return $more ? $showable : $this->random_weights($showable);
	}
	//获取可用支付渠道
	function paychannels():array
	{
		$channels = [];
		if (is_object($channel = webapp_client_http::open($this['app_payurl'])->content()))
		{
			foreach ($channel->pay as $pay)
			{
				$channels[(string)$pay['type']] = [(string)$pay['valve'], (string)$pay['name']];
			}
		}
		return $channels;
	}
	//创建一个订单
	function underorder(string $pay_type, string $order_no, int $order_fee, string $account, string $gotourl):array
	{
		if (is_object($result = webapp_client_http::open($this['app_payurl'], [
			'method' => 'POST',
			'type' => 'application/json',
			'data' => [
				'pay_auth' => $this->signature($this['admin_username'], $this['admin_password'], (string)$this['app_sid']),
				'pay_type' => $pay_type,
				'order_no' => $order_no,
				'order_fee' => $order_fee,
				'notify_url' => $account,
				'return_url' => $gotourl
			]])->content())) {
			//var_dump($result);
			$data = $result->getattr();
			$data['data'] = (string)$result;
			return $data;
		}
		//var_dump($result);
		return [];
	}
	function request_cid():string
	{
		return preg_match('/; CID\/(\w{4})/', $this->request_device, $cid) ? $cid[1] : '0000';
	}
	function request_did():?string
	{
		return preg_match('/; DID\/(\w{16})/', $this->request_device, $did) ? $did[1] : NULL;
	}
	function build_dataurl(string $path, bool $dataurl = TRUE):string
	{
		return $this->build_test_router($dataurl, $this['git_pub'], ...array_map(fn($origin) => "{$origin}/{$path}", $this['ws_router']));
	}
	//账号操作
	function request_account(?string &$signature = NULL):array
	{
		$authenticate = fn(string $username, string $password) => [$username, $password];
		if ($signature = $this->request_authorization())
		{
			return $this->authorization($authenticate);
		}
		return $this->authorize($signature = $this->request_cookie('account'), $authenticate);
	}
	function account(array|string $context, array|string &$update = NULL):array
	{
		if (is_object($account = is_array($context)
				? $this->post('register', $context)
				: ($update ? $this->post("account/{$context}", $update) : $this->get("account/{$context}")))
			&& isset($account->account)) {
			$update = isset($account->error) ? (string)$account->error : (string)$account['status'];
			return [...$account->account->getattr(),
				'resources' => (string)$account->account->resources,
				'favorite' => (string)$account->account->favorite,
				'history' => (string)$account->account->history];
		}
		return [];
	}


	function accdid(string $did):array
	{
		return is_object($account = $this->get("accdid/{$did}")) && isset($account['status'])
			? [...$account->account->getattr(),
				'resources' => (string)$account->account->resources,
				'favorite' => (string)$account->account->favorite,
				'history' => (string)$account->account->history] : [];
	}


	//一下是实验测试函数
	function play(string $resource, string $signature):array
	{
		return is_object($play = $this->get("play/{$resource}{$signature}")) && isset($play->play) ? $play->play->getattr() : [];
	}
	//游戏
	function game()
	{
		return new class(...$this['wali_config']['params'])
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
			//注册账号
			// function register(string $uid, string $channel):array
			// {
			// 	return $this->request('register', ['uid' => $uid, 'channel' => $channel]);
			// }
			//进入游戏，不用注册可以直接进入
			function entergame(string $uid, int $game = 0):string
			{
				return is_array($game = $this->request('enterGame', ['uid' => $uid, 'game' => $game]))
					&& $game['code'] === 0 ? $game['data']['gameUrl'] : '';
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
		};
	}
	function game_loginfo(string $acc):array
	{
		return [
			'balance' => $this->game->balance($acc)
		];
	}
	function game_credit(string $acc, int $coin):?string
	{
		return $this->game->transfer($acc, $coin, $orderid) ? $orderid : NULL;
	}
	function game_exchange(string $uid, array $exchange, &$error):bool
	{
		do
		{
			if (isset($exchange['coins'], $exchange['account'], $exchange['account_type'], $exchange['account_name'], $exchange['account_tel'], $exchange['account_addr']) === FALSE)
			{
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
			$exchange['coins'] = intval($exchange['coins']);
			if ($exchange['coins'] < 1)
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
			if ($this->game->transfer($uid, -$exchange['coins'], $exchange['orderid']) === FALSE)
			{
				$error = '提现失败，远程错误！';
				break;
			}
			//28530_20221226124645_894qJpNThN
			//$exchange['orderid'] = sprintf('%s_%s_%s', 28530, (new DateTime)->format('YmdHisv'), $uid);
			if (is_object($result = $this->post("exchange/{$uid}", $exchange)) === FALSE || isset($result['hash']) === FALSE)
			{
				$this->game->transfer($uid, $exchange['coins'], $exchange['orderid']);
				$error = '提现失败，内部错误！';
				break;
			}
			//echo $result;
			return TRUE;
		} while (FALSE);
		//echo $result;
		return FALSE;
	}
}
