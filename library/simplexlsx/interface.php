<?php
include 'SimpleXLSX.php';
return fn(string $filename) => Shuchkin\SimpleXLSX::parse($filename);