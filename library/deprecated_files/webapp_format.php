<?php
class webapp_format implements Stringable
{
	function __construct(private readonly string $input)
	{
		
	}
	function __toString():string
	{
		return $this->input;
	}
	function date(string $format):string
	{
		return date($format, $this->input);
	}
	static function from(string $input)
	{
		return webapp_format::from('1232465487')->date('Y-m-d');
	}
}