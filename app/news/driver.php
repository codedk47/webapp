<?php
class news_driver extends webapp
{
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
		return $this->sync->goto("{$this->sync->path}?{$router}")->content();
	}
	//数据同步POST方法（尽量不要去使用）
	function post(string $router, array $data = []):string|webapp_xml
	{
		return $this->sync->goto("{$this->sync->path}?{$router}", [
			'method' => 'POST',
			'type' => 'application/json',
			'data' => $data
		])->content();
	}
	//数据同步DELETE方法（尽量不要去使用）
	function delete(string $router):string|webapp_xml
	{
		return $this->sync->goto("{$this->sync->path}?{$router}", ['method' => 'POST'])->content();
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









	//一下是实验测试函数
	function account(string $signature = NULL):array
	{
		return is_object($account = $this->get($signature === NULL ? 'register' : "account/{$signature}")) && isset($account->account)
			? [...$account->account->getattr(),
			'favorites' => (string)$account->account->favorites,
			'historys' => (string)$account->account->historys
			] : [];
	}
	function play(string $resource, string $signature):array
	{
		return is_object($play = $this->get("play/{$resource}{$signature}")) && isset($play->play) ? $play->play->getattr() : [];
	}
	function payment(string $signature, array $data)
	{
		print_r( $this->post("payment/{$signature}", $data) );
	}

	
}
