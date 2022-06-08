<?php
interface webapp_pay
{
	static function paytype():array;						//可支付类型
	function __construct(array $context);					//支付上下文
	function create(array $order, ?array &$result):bool;	//创建订单，订单字段统一，结果字段统一
	function notify(mixed $input, ?array &$result):bool;	//订单通知地址回调函数，结果字段统一
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
	function create(array $order, &$result):bool
	{
		if (is_array($data = webapp_client_http::open('https://localhost/', [
			'method' => 'POST',		//提交方法
			'data' => [
				...$order			//请看订单数据内容和支付接口需要数据进行组合后提交
			]])->content())) {		//判断接口返回类型，或者更多内容判断
			$result = [
				...$data			//结果数据
			];
			return TRUE;
		}
		return FALSE;
	}
	function notify(mixed $input, ?array &$result):bool
	{
		$result = [
			'type' => 'text/plain',	//返回数据类型
			'data' => 'success'		//返回数据内容
		];
		return TRUE;
	}
}
final class webapp_pay_newbee implements webapp_pay
{
	static function paytype():array
	{
		return [
			'wechat' => '微信',
			'alipay' => '支付宝',
			'alipay_h5' => '支付宝H5',
			'wechat_qrcode' => '微信扫码'
		];
	}
	function __construct(array $context)
	{
		$this->ctx = $context;
	}
	function create(array $order, &$result):bool
	{
		$data = [
			'channel' => 98,
			'type' => $order['pay_type'],
			'money' => $order['order_fee'],
			'orderno' => $order['order_no'],
			'notifyurl' => $order['notify_url']
		];
		ksort($data);
		$query = [];
		foreach ($data as $k => $v)
		{
			$query[] = "{$k}={$v}";
		}
		$query[] = "key={$this->ctx['key']}";
		//var_dump(join('&', $query));
		$data['sign'] = strtoupper(md5(join('&', $query)));
		print_r($data);
		if (is_array($content = webapp_client_http::open('http://47.75.104.122:8081/gate/take_order.do', [
			'method' => 'POST',
			'data' => $data])->content())) {
			$result = [];
			print_r($content);
			return TRUE;
		}
		return FALSE;
	}
	function notify(mixed $input, ?array &$result):bool
	{
		$result = [
			'code' => 200,			//返回状态码
			'type' => 'text/plain',	//返回数据类型
			'data' => 'success'		//返回数据内容
		];
		return TRUE;
	}
}
final class webapp_router_pay extends webapp_echo_xml
{
	// function __construct(webapp $webapp)
	// {
	// 	parent::__construct($webapp, 'pay');
	// }
	function create(?array &$result, ?string &$error):bool
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
		//订单编号
		$form->field('order_no', 'text', ['maxlength' => 32, 'pattern' => '[0-9a-zA-Z]+', 'required' => NULL]);
		//订单费用
		$form->field('order_fee', 'number', ['min' => 0, 'required' => NULL], fn($v)=>floatval($v));

		while ($form->fetch($data, $error))
		{
			//授权认证（现在使用webapp内部认证。以后公开后另外使用）
			if (empty($auth = $this->webapp->admin($data['pay_auth'])))
			{
				$error = '支付认证失败！';
				break;
			}
			$data['pay_from'] = intval($auth[2]);
			if (array_key_exists($data['pay_name'], $this->webapp['app_pay']) === FALSE)
			{
				$error = '支付名称不存在！';
				break;
			}
			$channel = "webapp_pay_{$data['pay_name']}";
			if (array_key_exists($data['pay_type'], $channel::paytype()) === FALSE)
			{
				$error = '支付类型不存在！';
				break;
			}
			$data['notify_url'] = "https://kenb.cloud/?pay/notify,channel:{$data['pay_name']}";
			$data['notify_url'] = "https://localhost/test";
			return (new $channel($this->webapp['app_pay'][$data['pay_name']]))->create($data, $result);
		}
		return FALSE;
	}
	function get_home()
	{
		foreach ($this->webapp['app_pay'] as $channel => $context)
		{
			$pay = $this->xml->append('pay', ['value' => $channel, 'name' => $context['name']]);
			foreach ("webapp_pay_{$channel}"::paytype() as $type => $name)
			{
				$pay->append('type', ['value' => $type, 'name' => $name]);
			}
		}
	}
	function post_home()
	{
		if ($this->create($result, $error))
		{
			$this->xml->import($result);
			return;
		}
		$this->xml->append('error')->cdata($error ?? '未知错误');
	}
	function notify(string $name, $data)
	{
		if (class_exists($channel = "webapp_pay_{$name}", FALSE)
			&& (new $channel($this->webapp['app_pay'][$name]))->notify($data, $result)) {
			http_response_code($result['code']);
			header("Content-Type: {$result['type']}");
			echo $result['data'];
			print_r($result);
			return;
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