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
		$showable = array_values(array_filter($ads, $this->adshowable(...)));
		if ($more)
		{
			return $showable;
		}
		$weight = array_column($showable, 'weight');
		$count = array_sum($weight) - 1;
		if ($count > 0)
		{
			$random = random_int(0, $count);
			$current = 0;
			foreach ($weight as $index => $value)
			{
				if ($random >= $current && $random < $current + $value)
				{
					break;
				}
			}
			return $showable[$index];
		}
		return $showable[0] ?? [];
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
	function request_unitcode():string
	{
		return is_string($unitcode = $this->request_header('Unit-Code')) && preg_match('/^\w{4}$/', $unitcode) ? $unitcode : '0000';
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





	//一下是实验测试函数
	function play(string $resource, string $signature):array
	{
		return is_object($play = $this->get("play/{$resource}{$signature}")) && isset($play->play) ? $play->play->getattr() : [];
	}
}
