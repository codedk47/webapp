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
final class webapp_pay_changjiang implements webapp_pay
{
	static function paytype():array
	{
		return ['wxwap' => '微信', 'zfbwap' => '支付宝'];
	}
	function __construct(array $context)
	{
		$this->ctx = $context;
	}
	function create(array &$order, ?string &$error):bool
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
			return FALSE;
		}
		$data['sign'] = base64_encode($sign);
		//print_r($data);
		do
		{
			if (is_array($result = webapp_client_http::open('http://a.cjpay.xyz/api/pay', [
				'method' => 'POST',
				'type' => 'application/json',
				'data' => $data])->content()) === FALSE) {
				break;
			}
			//print_r($result);
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
		return FALSE;
	}
	function notify(mixed $result, ?array &$status):bool
	{
		if (is_array($result)
			&& isset($result['status'], $result['orderId'], $result['orderAmt'])
			&& $result['status'] === 1) {
			$status = [
				'code' => 200,
				'type' => 'text/plain',
				'data' => 'success',
				'hash' => $result['orderId'],
				'actual_fee' => $result['orderAmt']
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
				]]) ) === FALSE) {
				break;
			}
			
		} while (0);
		print_r($result);
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
final class webapp_router_pay extends webapp_echo_xml
{
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
		//支付名称
		$channels = [];
		foreach ($this->webapp['app_pay'] as $channel => $context)
		{
			$channels[$channel] = $context['name'];
		}
		$form->field('pay_name', 'select', ['options' => $channels, 'required' => NULL]);
		//支付类型
		$form->field('pay_type', 'text', ['required' => NULL]);
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
				'status' => 'unpay',
				'actual_fee' => $order['order_fee'],
				'order_fee' => $order['order_fee'],
				'pay_user' => $order['pay_user'],
				'pay_name' => $order['pay_name'],
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
				$pay = $this->xml->append('pay', ['value' => $channel, 'name' => $context['name']]);
				foreach ("webapp_pay_{$channel}"::paytype() as $type => $name)
				{
					$pay->append('type', ['value' => $type, 'name' => $name]);
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
	function insidebuy(string $code):bool
	{
		//这里根据订单号进商品行逻辑处理
		return FALSE;
	}
	function notify(string $name, $result)
	{
		if (class_exists($channel = "webapp_pay_{$name}", FALSE)
			&& (new $channel($this->webapp['app_pay'][$name]))->notify($result, $status)
			&& ($order = $this->webapp->mysql->orders('WHERE hash=?s LIMIT 1', $status['hash'])->array())) {
			$updata = ['last' => $this->webapp->time];
			if (is_numeric($order['pay_user']))
			{
				//内部交易处理
				$updata['status'] = $this->insidebuy($order['order_no']) ? 'notified' : 'payed';
			}
			else
			{
				$updata['status'] = 'payed';
				//这里处理外部订单通知
			}
			if (array_key_exists('actual_fee', $status))
			{
				$updata['actual_fee'] = $status['actual_fee'];
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