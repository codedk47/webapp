<?php
include 'HanziConvert.php';
use sqhlib\Hanzi\HanziConvert;
return function(string $content):string
{
    return HanziConvert::convert($content);
};
