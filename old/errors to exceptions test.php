<?php

include_once("cept.php");

try
{	$x = $testWarning;
}catch(Exception $e)
{	echo $e;
}

@$x = MOBIBOIOB;


$fp = fopen('php://stdin', 'r');
fgets($fp, 2);

?>
