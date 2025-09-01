<?php
return;
require 'webapp/webapp_stdio.php';
require 'webapp/extend/nfs/base.php';
require 'webapp/extend/vod/base.php';
require 'webapp/extend/vod/admin.php';
require 'webapp/extend/vod/home.php';
if (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['127.0.0.1', 'localhost'], TRUE))
{
	class webapp_router_admin extends webapp_extend_vod_admin
	{}
}
else
{
	class webapp_router_home extends webapp_extend_vod_home
	{
		function __construct(webapp $webapp)
		{
			parent::__construct($webapp);
			//$this->script(['src' => '']);
			//$this->script('');
		}
	}
}
new class extends webapp_extend_vod
{
	public array $origins = ['https://localhost'];
	public string $origin = '';
	function __construct()
	{
		parent::__construct(['copy_webapp' => 'vod']);
	}
	function client():webapp_nfs_client
	{
		return new webapp_nfs_client('amazon_s3', 'AccessKeyID', 'AccessKeySecret', 'BucketName', 'region');
	}
};