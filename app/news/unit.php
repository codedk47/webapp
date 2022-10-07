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
		$this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/app/news/admin.css']);
		$this->xml->head->append('script', ['src' => '/webapp/app/news/admin.min.js']);
		$this->footer->append('a', ['注销登录状态', 'href' => "javascript:void(document.cookie='unit=0',location.href='?unit');"]);
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
	function get_home(string $ym = '')
	{
		[$y, $m] = preg_match('/^\d{4}(?=\-(\d{2}))/', $ym, $pattren) ? $pattren : explode('-', $ym = date('Y-m'));
		$days = range(1, date('t', strtotime($ym)));

		$owns = [$this->unit['unit']];
		if ($this->unit['owns'])
		{
			array_push($owns, ...str_split($this->unit['owns'], 4));
			$units = $this->webapp->mysql->unitsets('WHERE unit IN(?S)', $owns)->column('price', 'unit');
		}
		else
		{
			$units = [];
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
				$t5->append('td', '下载');
				$t6->append('td', '激活');
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

			$t7->append('td', '订单');
			$t8->append('td', '金额');
			$t9->append('td', '费用');
			$t10->append('td', [sprintf('类型: %s, 单价: %0.2f', $type, $units[$stat['unit']] ?? 0),
				'colspan' => count($days) + 2,
				'style' => 'text-align:left']);

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

			if (isset($unitorders[$stat['unit']]))
			{
				$t7->append('td', number_format($unitorders[$stat['unit']][0]['count']));
				$t8->append('td', number_format($unitorders[$stat['unit']][0]['fee'] * 0.01));
			}
			else
			{
				$t7->append('td', 0);
				$t8->append('td', 0);
			}

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
				if (isset($unitorders[$stat['unit']]))
				{
					$t7->append('td', number_format($unitorders[$stat['unit']][$i]['count']));
					$t8->append('td', number_format($unitorders[$stat['unit']][$i]['fee'] * 0.01));
				}
				else
				{
					$t7->append('td', 0);
					$t8->append('td', 0);
				}
				$t9->append('td', number_format($stat["\$5\${$i}"] * $price));
				$pt += $stat["\$5\${$i}"] * $price;
			}
			$tp[0] = number_format($pt);

		}, $days, $unitorders, $units, $types);
		
		$table->fieldset('单位', '统计', '总和', ...$days);
		$table->header('')->append('input', ['type' => 'month', 'value' => "{$ym}", 'onchange' => 'g({ym:this.value})']);
		$table->xml['class'] = 'webapp-stateven';
	}
}