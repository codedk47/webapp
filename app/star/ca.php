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
		$sum = [];
		$merge = [];
		for ($i = 0; $i < 24; ++$i)
		{
			$fields = [];
			foreach ($statistics as $field) {
				if ($i === 0)
				{
					$sum[] = "SUM(`{$field}`) `{$field}`";
				}
				$fields[] = "'{$field}'";
				$fields[] = sprintf('SUM(JSON_EXTRACT(hourdata,"$[%d].%s"))', $i, $field);
			}
			$merge[] = sprintf('JSON_OBJECT(%s)', join(',', $fields));
		}
		$recordlog = $this->webapp->mysql(
			'SELECT cid,?? FROM recordlog WHERE date>=?s AND date<=?s AND cid=?s',
			join(',', $sum), $datefrom, $dateto, $this->channel['hash']
		);

		$table = $this->main->table($recordlog, function($table, $log, $statistics)
		{
			$node = [$table->row()];
			$table->cell([$log['cid'], 'rowspan' => 26]);
			$table->cell(['访问', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();
			$table->cell(['点击', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();
			$table->cell(['日活', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();
			$table->cell(['新增', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();
			$table->cell(['充值', 'rowspan' => 6]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('新充值');
			$node[] = $table->row();
			$table->cell('老充值');
			$node[] = $table->row();
			$table->cell('金币');
			$node[] = $table->row();
			$table->cell('VIP总');
			$node[] = $table->row();
			$table->cell('VIP新');
			$node[] = $table->row();
			$table->cell(['订单', 'rowspan' => 6]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('成功');
			$node[] = $table->row();
			$table->cell('苹果总计');
			$node[] = $table->row();
			$table->cell('苹果成功');
			$node[] = $table->row();
			$table->cell('安卓总计');
			$node[] = $table->row();
			$table->cell('安卓成功');


			// $hourdata = json_decode($log['hourdata'], TRUE);
			// foreach ($statistics as $i => $field)
			// {
			// 	$node[$i]->append('td', number_format($log[$field] ?? 0));
			// 	foreach (range(0, 23) as $hour)
			// 	{
			// 		$node[$i]->append('td', number_format($hourdata[$hour][$field] ?? 0));
			// 	}
			// }
		}, $statistics);
		$table->xml['class'] .= '-statistics';
		$table->fieldset('渠道', '分类', '详细', '总计', ...range(0, 23));

		$table->header($this->channel['hash']);
		$table->bar->append('input', ['type' => 'date', 'value' => $datefrom, 'onchange' => 'g({datefrom:this.value||null})']);
		$table->bar->append('input', ['type' => 'date', 'value' => $dateto, 'onchange' => 'g({dateto:this.value||null})']);
	}


}