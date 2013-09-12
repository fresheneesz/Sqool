<?php // does not change between examples A and B

$connection = sqool::connect("yourUserName", "yourPassword", "aDatabaseName");	
if($page === 'allComments') {
	$comments = comment::getClosestToFiveDaysAgo();
} else /* page == 'userComments' */ {
	$comments = user::getRecent($connection);
	$user = $connection->get('user[where:id=',$userId,']');
}

?>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>

<style>
	
	.comments {
		border: 4px solid lightgray;	
		background-color: lightgray;	
	}
		
		.comments .name {
			font-weight: bold;
		}
		.comments .date {
			color: white;	
			font-size: 12px;
		}
		
		.commentBox {
			background-color: lightgray;	
			padding: 6px;
		}
			.new.commentBox {
				background-color: white;	
			}
	
		.betweenComments {
			background-color: white;
			height: 1px;
		}
	
</style>


<div class='comments'>
	<?php
	foreach($comments as $index => $c) { 
		$timestamp = date('Y-m-d H:i', $c->date);
		if($index > 0) { 
			?><div class='betweenComments'></div><?php 
		} ?>
		
		<div class='commentBox'>
			<?php	
				if($example === 'A' || $page === 'userComments') {
					$name = $c->name;
				} else /* example == 'B' */ {
					$name = "<a href='sqoolExample_getUsersComments.php'>".$c->name."</a>";	
				}
			?>
			
			<div><span class='name'></span> <span class=date><?php echo $timestamp; ?></span></div>
			<div class='commentMessage'>
				<?php echo $c->comment; ?>
			</div>
		</div>
	<?php } ?>
	
</div>