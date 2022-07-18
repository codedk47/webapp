<?php
interface webapp_pay
{
	static function paytype():array; //可支付类型
	function __construct(array $context); //支付上下文
	function create(array &$order, ?string &$error):bool; //创建订单，订单字段统一，结果字段统一
	function notify(mixed $input, ?array &$result):bool; //订单通知地址回调函数，结果字段统一
}
final class webapp_pay_test implements webapp_pay
{
	static function paytype():array
	{
		return [
			'wxpay' => '微信',
			'alipay' => '支付宝',
			'card' => '信用卡'
		];
	}
	function __construct(array $context)
	{
		$this->ctx = $context;
	}
	function create(array &$order, ?string &$error):bool
	{
		do
		{
			if (is_array($result = webapp_client_http::open('https://httpbin.org/post', [
				'method' => 'POST',					//提交方法
				'data' => $order					//请看订单数据内容和支付接口需要数据进行组合后提交
				])->content()) === FALSE) {			//判断接口返回类型，或者更多内容判断
				break;
			}
			$order['trade_no'] = 0;					//远程交易号
			$order['type'] = 'data';				//data 数据，goto 跳转，qrcode 二维码，post转移提交，html展示代码
			$order['data'] = json_encode($result);	//操作数据
			return TRUE;
		} while (0);
		return FALSE;
	}
	function notify(mixed $result, ?array &$status):bool
	{
		$status = [
			'code' => 200,			//返回状态码
			'type' => 'text/plain',	//返回数据类型
			'data' => 'SUCCESS',	//返回数据内容
			'hash' => 0,			//订单哈希（获取订单号，提交订单的时使用订单哈希）
			'actual_fee' => 0		//实际费用（可选）
		];
		return TRUE;
	}
}
final class webapp_pay_cj implements webapp_pay
{
	static function paytype():array
	{
		return ['203' => '微信原生', 'zfbwap' => '支付宝快充'];
		//return ['203' => '微信原生', 'wxwap' => '微信快充', 'zfbwap' => '支付宝快充'];
	}
	function __construct(array $context)
	{
		$this->ctx = $context;
	}
	function create(array &$order, ?string &$error):bool
	{
		do
		{
			$data = [
				'merId' => $this->ctx['id'],
				'orderId' => $order['hash'],
				'orderAmt' => $order['order_fee'] * 0.01,
				'channel' => $order['pay_type'],
				'desc' => 'news',
				'attch' => '',
				'smstyle' => 1,
				'userId' => '',
				'ip' => $order['client_ip'],
				'notifyUrl' => $order['notify_url'],
				'returnUrl' => $order['return_url'],
				'nonceStr' => md5(random_bytes(16))
			];
			ksort($data);
			reset($data);
			$query = [];
			foreach ($data as $k => $v)
			{
				if ($v !== '')
				{
					$query[] = "{$k}={$v}";
				}
			}
			$query[] = "key={$this->ctx['key']}";
			//var_dump(join('&', $query));
			$private = 'MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAMaS/D3G2o3bxH66sCxoe6FXnpE7HiNyLWJXsvPxK0XbsEWjSgHchiKB5uDiUeM4oc4G+ZCPTgCOjgg5uA8SGpl5YlEdla+TFvhALu4YDD91SM5l6mTRaIBej0o6p0mfchliRlEWZi+r/uVvB+eZ8T6tEeY3QT87hUfXcM7sMna3AgMBAAECgYEAjCjfZfNf/FUsoo6/Hvk4mi8wOy5RHY/PvORN6aVGd+6SwvR4nku7Wcv63KyiRBGLE9MUgBbGZdo5IlErO2f54S3Pwnc1FqBi01q9ZJylrZRt6BHYoXcSS3OiKQMK1bqAZWn1md/EhSNAP/0bLtimo/uP/8Mmm9jcH4pn9Y2qcuECQQD8x0HW9PHhm100QlnkVxjscPNy9bNsHjm9lYRpUaawgB3uKwq97Kr5IswirKOT6C9bSTvlGIjfkRiiO6qCMwI/AkEAyRrgIWKl/7S4RPo1KGTnWD6wSCrPWSkZ5BL+cqVE3foNMbOtB71q7sxdI8jU5fCjuQ08zePfaiebE71ZgtL9iQJAcJBKwW5SSCTnXF4vqX8fmiqyPn8rZvoOvF3YmQ3DLNXgfi6smebKPCdCwC4gqby7WetCwMIsMWJrldL8Gv6cAQJAEtzNdvQsw74spnOddst4E4PVvv8c8az0O7s4WIJ94iApCqdirF4s4HcUqV2V8ndOs/W05U7hTrCmUASrl6S4mQJBALVOSzDSM7/qfEDnTRCWKEOINFBZihlmw4rqTXuesNIqpcthaBx7Y3GUjP2y6Q9Urb34yXPXoFlQtLvVKc6kF40=';
			$key = openssl_pkey_get_private("-----BEGIN PRIVATE KEY-----\n" . chunk_split($private, 64, "\n") . "-----END PRIVATE KEY-----\n");
			if ($key === FALSE
				|| openssl_sign(strtoupper(md5(join('&', $query))), $sign, $key, OPENSSL_ALGO_SHA256) === FALSE) {
				$error = '签名失败！';
				break;
			}
			$data['sign'] = base64_encode($sign);
			//print_r($data);
			if (is_array($result = webapp_client_http::open('http://a.cjpay.xyz/api/pay', [
				'method' => 'POST',
				'type' => 'application/json',
				'data' => $data])->content()) === FALSE) {
				break;
			}
			//var_dump($result);
			if ((array_key_exists('code', $result) && $result['code'] === 1) === FALSE)
			{
				$error = '远程支付失败！';
				break;
			}
			$order['trade_no'] = $result['data']['sysorderno'];
			$order['type'] = 'goto';
			$order['data'] = $result['data']['payurl'];
			return TRUE;
		} while (0);
		//var_dump($result);
		return FALSE;
	}
	function notify(mixed $result, ?array &$status):bool
	{
		if (is_array($result)
			&& isset($result['status'], $result['orderId'], $result['orderAmt'])
			&& intval($result['status']) === 1) {
			$status = [
				'code' => 200,
				'type' => 'text/plain',
				'data' => 'success',
				'hash' => $result['orderId'],
				'actual_fee' => $result['orderAmt'] * 100
			];
			return TRUE;
		}
		return FALSE;
	}
}
final class webapp_pay_yk implements webapp_pay
{
	static function paytype():array
	{
		return ['ALIPAY_H5' => '支付宝H5'];
	}
	function __construct(array $context)
	{
		$this->ctx = $context;
	}
	function create(array &$order, ?string &$error):bool
	{
		do
		{
			if (is_array($result = webapp_client_http::open('http://feisf.xzongkj.cn:18088/sfjoin/orderdata', [
				'method' => 'POST',
				'type' => 'application/json',
				'data' => [
					'appId' => $this->ctx['id'],
					'orderNo' => $order['hash'],
					'channelNo' => $order['pay_type'],
					'amount' => $fee = $order['order_fee'] * 0.01,
					'notifyCallback' => $order['notify_url'],
					'payType' => 1,
					'sign' => strtolower(md5(join([
						$this->ctx['key'],
						$order['hash'],
						$this->ctx['id'],
						$fee,
						$order['notify_url']
					])))
				]])->content()) === FALSE) {
				break;
			}
			//var_dump($result);
			if ((array_key_exists('code', $result) && $result['code'] === '1') === FALSE)
			{
				$error = '远程支付失败！';
				break;
			}
			$order['trade_no'] = $result['ownOrderNo'];
			$order['type'] = 'goto';
			$order['data'] = $result['payUrl'];
			return TRUE;
		} while (0);
		return FALSE;
	}
	function notify(mixed $result, ?array &$status):bool
	{
		if (is_array($result)
			&& isset($result['status'], $result['orderNo'], $result['amount'])
			&& intval($result['status']) === 1) {
			$status = [
				'code' => 200,
				'type' => 'text/plain',
				'data' => 'success',
				'hash' => $result['orderNo'],
				'actual_fee' => $result['amount'] * 100
			];
			return TRUE;
		}
		return FALSE;
	}
}
final class webapp_pay_pp implements webapp_pay
{
	static function paytype():array
	{
		return [
			'808' => '微信原生'
		];
	}
	function __construct(array $context)
	{
		$this->ctx = $context;
	}
	function create(array &$order, ?string &$error):bool
	{
		do
		{
			$data = [
				'mch_id' => $this->ctx['id'],
				'pass_code' => $order['pay_type'],
				'subject' => $order['order_no'],
				'out_trade_no' => $order['hash'],
				'amount' => $order['order_fee'] * 0.01,
				'notify_url' => $order['notify_url'],
				'return_url' => $order['return_url'],
				'timestamp' => date('Y-m-d H:i:s')
			];
			ksort($data);
			reset($data);
			$query = [];
			foreach ($data as $k => $v)
			{
				if ($v !== '')
				{
					$query[] = "{$k}={$v}";
				}
			}
			$data['sign'] = strtoupper(md5(join('&', $query) . $this->ctx['key']));

			if (is_array($result = webapp_client_http::open('http://www.pipi2023.com/api/unifiedorder', [
				'method' => 'POST',
				'type' => 'application/json',
				'data' => $data
				])->content()) === FALSE) {
				break;
			}
			//var_dump($result);
			if ((array_key_exists('data', $result) && array_key_exists('code', $result) && $result['code'] === 0) === FALSE)
			{
				$error = '远程支付失败！';
				break;
			}
			$order['trade_no'] = $result['data']['trade_no'];
			//$order['actual_fee'] = $result['money'] * 100;
			if (array_key_exists('pay_url', $result['data']) === FALSE)
			{
				break;
			}
			$order['type'] = 'goto';
			$order['data'] = $result['data']['pay_url'];
			return TRUE;
		} while (0);
		return FALSE;
	}
	function notify(mixed $result, ?array &$status):bool
	{
		if (is_array($result)
			&& isset($result['status'], $result['out_trade_no'], $result['money'])
			&& intval($result['status']) === 1) {
			$status = [
				'code' => 200,
				'type' => 'text/plain',
				'data' => 'SUCCESS',
				'hash' => $result['out_trade_no'],
				'actual_fee' => $result['money'] * 100
			];
			return TRUE;
		}
		return FALSE;
	}
}
final class webapp_router_pay extends webapp_echo_xml
{
	private array $channels = [
		//111111111111111111111111
	];
	#为了更好的兼容回调地址请改写地址重写
	#const notify = 'https://kenb.cloud/?pay/notify,channel:';
	const notify = 'https://kenb.cloud/notify';
	// function __construct(webapp $webapp)
	// {
	// 	parent::__construct($webapp, 'pay');
	// }
	function create(?array &$order, ?string &$error):bool
	{
		$form = new webapp_form($this->webapp);
		//授权认证（现在使用webapp内部认证。以后公开后另外使用）
		$form->field('pay_auth', 'text', ['required' => NULL]);
		//支付类型
		$form->field('pay_type', 'text', ['pattern' => '[a-z]+@.+', 'required' => NULL]);
		//付款说明
		$form->field('pay_desc', 'text', ['maxlength' => NULL]);
		//订单编号
		$form->field('order_no', 'text', ['maxlength' => 32, 'pattern' => '[0-9a-zA-Z]+', 'required' => NULL]);
		//订单费用
		$form->field('order_fee', 'number', ['min' => 0, 'required' => NULL], fn($v)=>floatval($v));
		//回调地址
		$form->field('notify_url', 'text', ['required' => NULL]);
		//跳转地址（可选）
		$form->field('return_url', 'text');

		if ($this->webapp->request_content_type() === 'application/json')
		{
			$form->xml['enctype'] = 'application/json';
		}

		while ($form->fetch($order, $error))
		{
			//授权认证（现在使用webapp内部认证。以后公开后另外使用）
			if (empty($auth = $this->webapp->admin($order['pay_auth'])))
			{
				$error = '支付认证失败！';
				break;
			}
			//这里正对内部订单
			$order['pay_user'] = intval($auth[2]);

			[$order['pay_name'], $order['pay_type']] = explode('@', $order['pay_type']);
			if (array_key_exists($order['pay_name'], $this->webapp['app_pay']) === FALSE
				|| $this->webapp['app_pay'][$order['pay_name']]['open'] === FALSE) {
				$error = '支付名称不存在！';
				break;
			}
			$channel = "webapp_pay_{$order['pay_name']}";
			if (array_key_exists($order['pay_type'], $channel::paytype()) === FALSE)
			{
				$error = '支付类型不存在！';
				break;
			}
			if ($this->webapp->mysql->orders->insert([
				'hash' => $order['hash'] = $this->webapp->randhash(),
				'time' => $this->webapp->time,
				'last' => $this->webapp->time,
				'tym' => date('Ym', $this->webapp->time),
				'day' => date('d', $this->webapp->time),
				'status' => 'unpay',
				'actual_fee' => $order['order_fee'],
				'order_fee' => $order['order_fee'],
				'pay_user' => $order['pay_user'],
				'pay_name' => $order['pay_name'],
				'pay_type' => $order['pay_type'],
				'order_no' => $order['order_no'],
				'trade_no' => '',
				'notify_url' => $order['notify_url']
			]) === FALSE) {
				$error = '订单创建失败，请重试！';
				break;
			}
			//客户端IP
			$order['client_ip'] = $this->webapp->clientip();
			//回调通知地址
			$order['notify_url'] = self::notify . $order['pay_name'];
			return (new $channel($this->webapp['app_pay'][$order['pay_name']]))->create($order, $error);
		}
		return FALSE;
	}
	function get_home()
	{
		foreach ($this->webapp['app_pay'] as $channel => $context)
		{
			if ($context['open'])
			{
				foreach ("webapp_pay_{$channel}"::paytype() as $type => $name)
				{
					$this->xml->append('pay', [
						'type' => "{$channel}@{$type}",
						'name' => "{$channel}@{$type}" === 'yk@ALIPAY_H5' ? "{$name} " : $name
					]);
				}
			}
		}
	}
	function post_home()
	{
		if ($this->create($order, $error)
			&& $this->webapp->mysql->orders('WHERE hash=?s LIMIT 1', $order['hash'])
			->update('trade_no=?s', $order['trade_no'])) {
			$this->xml->cdata($order['data']);
			$this->xml['type'] = $order['type'];
			$this->xml['hash'] = $order['hash'];
			return;
		}
		$this->xml->cdata($error ?? '未知错误');
		$this->xml['type'] = 'error';
	}
	//内部订单
	function insidebuy(array $order):bool
	{
		//这里根据订单号进商品行逻辑处理
		//{E:会员|B:金币}{YYYYMMDD}{增加数字}
		return $order['status'] !== 'notified'
			&& preg_match('/(E|B)(\d{8})(\d+)/', $order['order_no'], $goods)
			&& $this->webapp->mysql->sync(fn() =>
				$this->webapp->mysql->accounts('WHERE uid=?s LIMIT 1', $order['notify_url'])->update(...match ($goods[1])
					{
						'E' => ['expire=IF(expire>?i,expire,?i)+?i', $this->webapp->time, $this->webapp->time, $goods[3]],
						'B' => ['balance=balance+?i', $goods[3]],
						default => []
					})
				&& $this->webapp->mysql->bills->insert([
					'hash' => $this->webapp->randhash(TRUE),
					'site' => $order['pay_user'],
					'time' => $this->webapp->time,
					'type' => 'undef',
					'tym' => date('Ym', $this->webapp->time),
					'day' => date('d', $this->webapp->time),
					'fee' => intval($order['order_fee'] * 0.01),
					'account' => $order['notify_url'],
					'describe' => match ($goods[1])
					{
						'E' => sprintf('购买会员: %d天', $goods[3] / 86400),
						'B' => sprintf('购买金币: %d个', $goods[3]),
						default => '??'
					}])
				&& is_numeric($this->webapp->site = $order['pay_user'])
				&& $this->webapp->call('saveUser', $this->webapp->account_xml($this->webapp->mysql
					->accounts('WHERE uid=?s LIMIT 1', $order['notify_url'])->array()))
			);
	}
	function notify(string $name, $result)
	{
		//file_put_contents('d:/n.txt', json_encode($result, JSON_UNESCAPED_UNICODE));
		// $result = [
		// 	'status' => '1',
		// 	'orderNo' => 'A8NU7DMJFHH9',
		// 	'amount' => 10000
		// ];
		if (class_exists($channel = "webapp_pay_{$name}", FALSE)
			&& (new $channel($this->webapp['app_pay'][$name]))->notify($result, $status)
			&& $this->webapp->mysql->orders('WHERE hash=?s LIMIT 1', $status['hash'])->fetch($order)
			&& $order['status'] !== 'notified') {
			$updata = ['last' => $this->webapp->time];
			if (array_key_exists('actual_fee', $status))
			{
				$updata['actual_fee'] = $order['actual_fee'] = $status['actual_fee'];
			}
			if (is_numeric($order['pay_user'])
				&& strlen($order['notify_url']) === 10
				&& trim($order['notify_url'], webapp::key) === '') {
				//内部交易处理
				$updata['status'] = $this->insidebuy($order) ? 'notified' : 'payed';
			}
			else
			{
				$updata['status'] = 'payed';
				//这里处理外部订单通知
			}
			if ($this->webapp->mysql->orders('WHERE hash=?s LIMIT 1', $status['hash'])->update($updata) > 0)
			{
				http_response_code($status['code']);
				header("Content-Type: {$status['type']}");
				echo $status['data'];
				return;
			}
		}
		http_response_code(500);
		header("Content-Type: text/plain");
		echo 'FAILURE';
	}
	function post_notify(string $channel)
	{
		$this->notify($channel, $this->webapp->request_content());
	}
	function get_notify(string $channel)
	{
		$this->notify($channel, $this->webapp->request_content());
	}
}