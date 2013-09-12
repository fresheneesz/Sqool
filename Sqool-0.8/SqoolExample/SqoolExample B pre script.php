<?php	
$example = 'B';	
require_once('sqoolExample_sqoolClasses.php');
	
$connection = sqool::connect("yourUserName", "yourPassword", "aDatabaseName");	
$allUsers = $connection->get('user');		

$connection->queue = true;	// turn queuing on to speed up the updates (todo: time the difference)
foreach($allUsers as $c) {
	$usersComments = $connection->get('comment[where:user=',$c->id,']');	// All objects are defined with a default id if another id name isn't defined
																			// Also, every other parameter to get is automatically escaped ($c->id is escaped)
	$c->comments[] = $usersComments;
	$c->save();
}
$connection->queue = false;	// turn queuing off as good practice (tho it doesn't really matter since this script doesn't continue very far)
$connection->go();	// execute the updates
