<?php
	require_once("cept.php"); 
	require_once("SQOOL_0.6.php");
	sqool::killMagicQuotes();
	
	echo "WTF";
	
	class comment extends sqool
	{	function sclass()
		{	return
			'string: 	name
			 string: 	comment
			'.//string:	page
			'int:		time
			';
		}
	}
	
	$db = sqool::connect("frencheneesz", "frenchy1", "frencheneesz", "FRESHSQL.COM");
	$db->debug(false);
	
	if(isset($_POST['submitComment']))
	{	$comment = new comment();
		$comment->name = $_POST['name'];
		$comment->comment = $_POST['comment'];
		//$comment->page = $_POST['page'];
		$comment->time = time();
		
		$db->insert($comment);
	}
	
	$db->fetch("comment");
	
	var_dump($db);
	
	foreach($db->comment as $c)
	{	?>
		<div class="wideoffwhitebox">
			<div class="whitebox"><?php echo "<b>".$c->name."</b> ".date("m/d/Y H:j:s", $c->time);?></div>
			<?php echo $c->comment;?>
		</div>
		<?php
	}
	?>
