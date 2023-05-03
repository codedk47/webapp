<?php
class webapp_router_unit extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->title('Unit');
		$this->footer[0] = '';
		if (empty($this->unit()))
		{
			return $webapp->break($this->post_home(...));
		}
		$this->nav([
			['主页', '?unit'],
			['添加单位', '?unit/add'],
			['管理单位', '?unit/all'],
			['注销登录状态', "javascript:void(document.cookie='unit=0',location.href='?unit');"]
		]);
		$this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/app/news/admin.css']);
		$this->xml->head->append('script', ['src' => '/webapp/app/news/admin.min.js']);
	}
	function unit(string $sign = NULL):array
	{
		return $this->unit = $this->webapp->authorize($sign ?? $this->webapp->request_cookie('unit'),
			fn($unit, $code) => $this->webapp->mysql->unitsets('WHERE unit=?s AND code=?s LIMIT 1', $unit, $code)->array());
	}
	function post_home()
	{
		if ($this->webapp['request_method'] === 'post'
			&& webapp_echo_html::form_sign_in($this->webapp)->fetch($data)
			&& $this->unit($sign = $this->webapp->signature($data['username'], $data['password']))) {
			$this->webapp->response_cookie('unit', $sign);
			$this->webapp->response_location('?unit');
			return 302;
		}
		webapp_echo_html::form_sign_in($this->main);
		return 401;
	}
	function get_home(string $ym = '', string $unit = NULL)
	{
		[$y, $m] = preg_match('/^\d{4}(?=\-(\d{2}))/', $ym, $pattren) ? $pattren : explode('-', $ym = date('Y-m'));
		$days = range(1, date('t', strtotime($ym)));


		$owns = [$this->unit['unit'], ...$this->unit['owns'] ? str_split($this->unit['owns'], 4) : []];
		$units = $this->webapp->mysql->unitsets('WHERE unit IN(?S)', $owns)->column('price', 'unit');
		$unitopt = $this->webapp->mysql->unitsets('WHERE unit IN(?S)', $owns)->column('name', 'unit');
		if ($unit && in_array($unit, $owns))
		{
			$owns = [$this->unit['unit'], $unit];
		}

		$unitorders = [NULL => array_fill(0, 32, ['count'=> 0, 'fee' => 0])];
		foreach ($this->webapp->mysql('SELECT orders.day AS day,orders.order_fee AS fee,accounts.unit AS unit FROM orders INNER JOIN (accounts) ON (accounts.uid=orders.notify_url) WHERE orders.pay_user=?i AND orders.status!="unpay" AND tym=?i AND accounts.unit IN(?S)', $this->webapp->site, $y.$m, $owns) as $order)
		{
			++$unitorders[NULL][0]['count'];
			$unitorders[NULL][0]['fee'] += $order['fee'];
			++$unitorders[NULL][$order['day']]['count'];
			$unitorders[NULL][$order['day']]['fee'] += $order['fee'];
			if (array_key_exists($order['unit'], $unitorders) === FALSE)
			{
				$unitorders[$order['unit']] = array_fill(0, 32, ['count'=> 0, 'fee' => 0]);
			}
			++$unitorders[$order['unit']][0]['count'];
			$unitorders[$order['unit']][0]['fee'] += $order['fee'];
			++$unitorders[$order['unit']][$order['day']]['count'];
			$unitorders[$order['unit']][$order['day']]['fee'] += $order['fee'];
		}


		$types = $this->webapp->mysql->unitsets->column('type', 'unit');

		$stat = $this->webapp->mysql->unitrates('WHERE left(date,7)=?s AND unit IN(?S)', $ym, $owns)->statmonth($ym, 'unit', 'right(date,2)', [
			'SUM(IF({day}=0 OR right(date,2)={day},pv,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},ua,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},lu,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},ru,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},dc,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},ia,0))',
		]);

		$skip = TRUE;
		$table = $this->main->table($stat, function($table, $stat, $days, $unitorders, $units, $types) use(&$skip)
		{
			if ($skip) return $skip = FALSE;
			$t1 = $table->tbody->append('tr');
			$t2 = $table->tbody->append('tr');
			$t3 = $table->tbody->append('tr');
			$t4 = $table->tbody->append('tr');
			$t5 = $table->tbody->append('tr');
			$t6 = $table->tbody->append('tr');
			$t7 = $table->tbody->append('tr');
			$t8 = $table->tbody->append('tr');
			$t9 = $table->tbody->append('tr');
			$t10 = $table->tbody->append('tr');

			$t1->append('td', [$stat['unit'] ?? '汇总', 'rowspan' => 10]);
			$type = $types[$stat['unit']] ?? 'cpc';
			
			if ($type === 'cpc')
			{
				$t1->append('td', '浏览');
				$t2->append('td', '独立');
				$t3->append('td', '登录');
				$t4->append('td', '注册');
				$t5->append('td', '点击');
				$t6->append('td', '下载');
			}
			else
			{
				$t1->append('td', '-');
				$t2->append('td', '-');
				$t3->append('td', '-');
				$t4->append('td', '-');
				$t5->append('td', '-');
				$t6->append('td', '下载');
			}

			// $t7->append('td', '订单');
			// $t8->append('td', '金额');
			$t7->append('td', '-');
			$t8->append('td', '-');
			$t9->append('td', '费用');
			$t10->append('td', ["类型: {$type}", 'colspan' => 2]);
			$t10->append('td', ['colspan' => 3])->append('a', ['点击跳转测试', 'href' => $this->webapp->test_router("PD/{$stat['unit']}")]);
			$t10->append('td', ['colspan' => 3])->append('button', ['点击复制跳转地址', 'onclick' => 'navigator.clipboard.writeText(this.parentNode.previousElementSibling.firstElementChild.href).then(()=>alert("复制成功！"))']);
			$t10->append('td', ['-', 'colspan' => count($days) - 6]);
			
			if ($type === 'cpc')
			{
				$t1->append('td', number_format($stat['$0$0']));
				$t2->append('td', number_format($stat['$1$0']));
				$t3->append('td', number_format($stat['$2$0']));
				$t4->append('td', number_format($stat['$3$0']));
				$t5->append('td', number_format($stat['$4$0']));
			}
			else
			{
				$t1->append('td', '-');
				$t2->append('td', '-');
				$t3->append('td', '-');
				$t4->append('td', '-');
				$t5->append('td', '-');
			}
			$t6->append('td', number_format($stat['$5$0']));

			// if (isset($unitorders[$stat['unit']]))
			// {
			// 	$t7->append('td', number_format($unitorders[$stat['unit']][0]['count']));
			// 	$t8->append('td', number_format($unitorders[$stat['unit']][0]['fee'] * 0.01));
			// }
			// else
			// {
			// 	$t7->append('td', 0);
			// 	$t8->append('td', 0);
			// }
			$t7->append('td', '-');
			$t8->append('td', '-');

			$tp = $t9->append('td');
			$price = $units[$stat['unit']] ?? 0;
			$pt = 0;
			foreach ($days as $i)
			{
				if ($type === 'cpc')
				{
					$t1->append('td', number_format($stat["\$0\${$i}"]));
					$t2->append('td', number_format($stat["\$1\${$i}"]));
					$t3->append('td', number_format($stat["\$2\${$i}"]));
					$t4->append('td', number_format($stat["\$3\${$i}"]));
					$t5->append('td', number_format($stat["\$4\${$i}"]));
				}
				else
				{
					$t1->append('td', '-');
					$t2->append('td', '-');
					$t3->append('td', '-');
					$t4->append('td', '-');
					$t5->append('td', '-');
				}

				$t6->append('td', number_format($stat["\$5\${$i}"]));
				// if (isset($unitorders[$stat['unit']]))
				// {
				// 	$t7->append('td', number_format($unitorders[$stat['unit']][$i]['count']));
				// 	$t8->append('td', number_format($unitorders[$stat['unit']][$i]['fee'] * 0.01));
				// }
				// else
				// {
				// 	$t7->append('td', 0);
				// 	$t8->append('td', 0);
				// }
				$t7->append('td', '-');
				$t8->append('td', '-');
				$t9->append('td', number_format($stat["\$5\${$i}"] * $price));
				$pt += $stat["\$5\${$i}"] * $price;
			}
			$tp[0] = number_format($pt);

		}, $days, $unitorders, $units, $types);
		
		$table->fieldset('单位', '统计', '总和', ...$days);
		$table->header('')->append('input', ['type' => 'month', 'value' => "{$ym}", 'onchange' => 'g({ym:this.value})']);

		$table->header->select(['' => '所有单位'] + $unitopt)
			->setattr(['onchange' => 'g({unit:this.value||null})'])->selected($unit);

		$table->xml['class'] = 'webapp-stateven';
	}
	function form_unitset($ctx):webapp_form
	{

		$form = new webapp_form($ctx);

		$count = ceil(strlen($this->unit['owns']) / 4) + 1;

		$form->fieldset('unit / code / name / price');
		$form->field('unit', 'text', ['placeholder' => '单位编码4位字母数字组合', 'value' => substr(substr($this->unit['unit'], 0, 3) . $count, -4), 'pattern' => '\w{4}', 'maxlength' => 4, 'required' => NULL]);
		$form->field('code', 'number', ['placeholder' => '6位密码', 'value' => random_int(100000, 999999), 'min' => 100000, 'max' => 999999, 'required' => NULL]);
		$form->field('name', 'text', ['placeholder' => '单位名字描述', 'value' => "{$this->unit['name']}{$count}", 'maxlength' => 128, 'required' => NULL]);
		$form->field('price', 'number', ['value' => 0, 'min' => 0, 'max' => 100, 'step' => 0.01, 'style' => 'width:4rem', 'required' => NULL]);

		$form->button('添加单位', 'submit');

		return $form;
	}
	function post_add()
	{
		$input = $this->webapp->request_content() + [
			'time' => $this->webapp->time,
			'site' => $this->unit['site'],
			'rate' => $this->unit['rate'],
			'type' => $this->unit['type'],
			'admin' => $this->unit['admin'],
			'max' => 0,
			'owns' => ''
		];
		if (strlen($this->unit['owns']) < $this->unit['max'] * 4
			&& $this->webapp->mysql->sync(fn() => $this->webapp->mysql->unitsets->insert($input)
				&& $this->webapp->mysql->unitsets('where unit=?s', $this->unit['unit'])->update('owns=concat(owns,?s)', $input['unit'])
				&& $this->webapp->unitincr($input['unit'], $input['time'], ['pv' => 0, 'ua' => 0, 'lu' => 0, 'ru' => 0, 'dv' => 0, 'dc' => 0, 'ia' => 0]))) {
			$this->webapp->response_location('?unit');
			return 302;
		}
		$this->main->append('b', ['添加失败，或已达到最大单位值！']);
	}
	function get_add()
	{
		$this->form_unitset($this->main);
	}
	function get_all()
	{
		if ($this->unit['owns'])
		{

			$table = $this->main->table($this->webapp->mysql->unitsets('where unit in(?S)', str_split($this->unit['owns'], 4)), function($table, $unit)
			{
				$table->row();
				$table->cell(date('Y-m-d H:i:s', $unit['time']));
				$table->cell("{$unit['unit']}:{$unit['code']}");
				$table->cell($unit['type']);

				$table->cell($unit['price']);
				$table->cell($unit['name']);
			});
			$table->fieldset('创建时间', '单位:密码', '类型', '单价', '名称');
			$table->header('单位管理');

			return;
		}
		$this->main->append('b', ['请先添加单位！']);
	}
}