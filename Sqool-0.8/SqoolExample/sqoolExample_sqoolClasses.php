<?php
require_once('SQooL-0.8.php');

if($example === 'A') {
	$userSclass = 	'string: name';	
} else /* example == 'B' */ {
	$userSclass = 	'string: name
				 	 list comments: comments';		
}

class user extends SqoolObject
{	function sclass()
	{	return  $userSclass;
	}
}
class comment extends SqoolObject
{	function sclass()
	{	return  'user: 		user
				 int:		datestamp
				 string:	comment
				';
	}
	
	static function getClosestToFiveDaysAgo($connection) {
		$recently = time()-5*(24*60*60);	// 5 days ago
		return $connection->get('comment[where: date > ',$recently,'  sort asc: date  ranges: 0 10]');		
	}
}