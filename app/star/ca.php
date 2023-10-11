<?php
class webapp_router_ca extends webapp_echo_html
{
	private array $channel;
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->footer[0] = NULL;
		$this->title('CA');
		if ($this->channel = $webapp->authorize($webapp->request_cookie('ca'), $this->sign_in_auth(...)))
		{
			$this->link(['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/app/star/base.css']);
			$this->script(['src' => '/webapp/app/star/base.js']);
			$this->nav([
				['数据', '?ca/home'],
				//['添加渠道', '?ca/add'],
				['注销登录', "javascript:location.reload(document.cookie='ca=0');", 'style' => 'color:maroon']
			]);
			return;
		}
		$webapp->method === 'post_sign_in' || $webapp->break(function()
		{
			webapp_echo_html::form_sign_in($this->main)->xml['action'] = '?ca/sign-in';
			return 401;
		});
	}
	function sign_in_auth(string $uid, string $pwd):array
	{
		return $this->webapp->mysql->channels('WHERE hash=?s LIMIT 1', $uid, $pwd)->array();
	}
	function post_sign_in()
	{
		$this->webapp->response_location($this->webapp->request_referer('?ca'));
		if (webapp_echo_html::form_sign_in($this->webapp)->fetch($admin)
			&& $this->webapp->authorize($signature = $this->webapp->signature(
				$admin['username'], $admin['password']), $this->sign_in_auth(...))) {
			$this->webapp->response_cookie('ca', $signature);
			return 200;
		}
		return 401;
	}
	function get_home(string $datefrom = '', string $dateto = '')
	{
		if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $datefrom))
		{
			$datefrom = date('Y-m-01');
		}
		if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateto))
		{
			$dateto = date('Y-m-t');
		}

		$statistics = [
			'dpv',
			'dpv_ios',
			'dpv_android',
			'dpc',
			'dpc_ios',
			'dpc_android',
			'signin',
			'signin_ios',
			'signin_android',
			'signup',
			'signup_ios',
			'signup_android',
			'recharge',
			'recharge_new',
			'recharge_old',
			'recharge_coin',
			'recharge_vip',
			'recharge_vip_new',
			'order',
			'order_ok',
			'order_ios',
			'order_ios_ok',
			'order_android',
			'order_android_ok'
		];
		$all = array_combine($statistics, array_fill(0, count($statistics), 0));
		$logs = $this->webapp->mysql->recordlogs('WHERE cid=?s AND date>=?s AND date<=?s ORDER BY date ASC',
			$this->channel['hash'], $datefrom, $dateto);
		$table = $this->main->table($logs, function($table, $log, $statistics) use(&$all)
		{
			foreach ($statistics as $field)
			{
				$all[$field] += $log[$field];
			}
			$table->row();
			$table->cell($log['date']);
			$table->cell(number_format(ceil($log['dpv'])));
			$table->cell(number_format(ceil($log['dpc'])));
			$table->cell(number_format(ceil($log['signup'])));
			$table->cell(number_format(ceil($log['signin'])));
			if ($this->channel['type'] === 'cpa')
			{
				$table->cell(number_format(ceil($log['recharge'])));
			}

		}, $statistics);
		$table->row();
		$table->cell('总计');
		$table->cell(number_format(ceil($all['dpv'])));
		$table->cell(number_format(ceil($all['dpc'])));
		$table->cell(number_format(ceil($all['signup'])));
		$table->cell(number_format(ceil($all['signin'])));
		if ($this->channel['type'] === 'cpa')
		{
			$table->cell(number_format(ceil($all['recharge'])));
		}
		$table->xml['class'] .= '-statistics';
		$table->fieldset(...['日期', '访问', '点击', '新增', '登录',
			...$this->channel['type'] === 'cpa' ? ['充值'] : []]);

		$table->header($this->channel['hash']);
		$table->bar->append('input', ['type' => 'date', 'value' => $datefrom, 'onchange' => 'g({datefrom:this.value||null})']);
		$table->bar->append('input', ['type' => 'date', 'value' => $dateto, 'onchange' => 'g({dateto:this.value||null})']);
	}


}