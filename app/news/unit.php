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
		$stat = $this->webapp->mysql->unitrates('WHERE left(date,7)=?s AND unit=?s', $ym, $this->unit['unit'])->statmonth($ym, 'unit', 'right(date,2)', [
			'SUM(IF({day}=0 OR right(date,2)={day},pv,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},ua,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},lu,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},ru,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},dc,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},ia,0))',
		]);
		
		$table = $this->main->table($stat, function($table, $stat, $days)
		{
			$t1 = $table->tbody->append('tr');
			$t2 = $table->tbody->append('tr');
			$t3 = $table->tbody->append('tr');
			$t4 = $table->tbody->append('tr');
			$t5 = $table->tbody->append('tr');
			$t6 = $table->tbody->append('tr');

			$t1->append('td', [$stat['unit'] ?? '汇总', 'rowspan' => 6]);
				
			$t1->append('td', '浏览');
			$t2->append('td', '独立');
			$t3->append('td', '登录');
			$t4->append('td', '注册');
			$t5->append('td', '下载');
			$t6->append('td', '激活');

			$t1->append('td', number_format($stat['$0$0']));
			$t2->append('td', number_format($stat['$1$0']));
			$t3->append('td', number_format($stat['$2$0']));
			$t4->append('td', number_format($stat['$3$0']));
			$t5->append('td', number_format($stat['$4$0']));
			$t6->append('td', number_format($stat['$5$0']));

			foreach ($days as $i)
			{
				$t1->append('td', number_format($stat["\$0\${$i}"]));
				$t2->append('td', number_format($stat["\$1\${$i}"]));
				$t3->append('td', number_format($stat["\$2\${$i}"]));
				$t4->append('td', number_format($stat["\$3\${$i}"]));
				$t5->append('td', number_format($stat["\$4\${$i}"]));
				$t6->append('td', number_format($stat["\$5\${$i}"]));
			}

		}, $days);
		$table->fieldset('单位', '统计', '总和', ...$days);
		$table->header('')->append('input', ['type' => 'month', 'value' => "{$ym}", 'onchange' => 'g({ym:this.value})']);
		$table->xml['class'] = 'webapp-stateven';
	}
}