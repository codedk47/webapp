<?php
require '../../webapp_stdio.php';
require 'home.php';
require 'mask.php';
require 'admin.php';
class webapp_router_home extends webapp_extend_demo_home
{
}
class webapp_router_mask extends webapp_extend_demo_mask
{
}
class webapp_router_admin extends webapp_extend_demo_admin
{
}
new class extends webapp
{
	function __construct()
	{
		parent::__construct();
	}
};