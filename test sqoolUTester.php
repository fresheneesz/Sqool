<?php
require_once('sqoolUnitTester.php');

function errorHandler($errno, $errstr, $errfile, $errline)
{	throw new Exception($errstr, $errno);
}
set_error_handler('errorHandler');

function getchar()
{	$fp = fopen('php://stdin', 'r');
	return fgetc($fp);
}


class SqoolTests extends SqoolUTester 
{	function testUtilityMethods()
	{	$x = array(1,2,3,4);
		
		self::assert($x[0] === 1);
		self::assert($x[2] === 45);
	}
}

SqoolTests::run();

$fp = fopen('php://stdin', 'r');
fgets($fp, 2);

?>
