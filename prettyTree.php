<?php

// requires jquery.js

function prettyTree($variable)		// prints a variable with arrays and objects in it, in tree form
{	prettyTreeStylesAndJs();
	prettyTreeBackend($variable);
}
function prettyTreeBackend($variable)
{	echo '<div class="prettyTree">' . buildTree("", $variable, 0) . "</div>\n";
}

// function used by the function prettyTree
function prettyTreeStylesAndJs()
{	?>
	<script type="text/javascript" src="jquery.js"></script>
	<style type="text/css">	<!--if a strict mode doctype is on then we dont need some werid ie fixes-->
		.prettyTree.hoverLine:hover 
		{	text-decoration:underline;
		}
		
		.prettyTreecolorMeRed
		{	color:red;
		}
		.prettyTreecolorMeBlue
		{	color:rgb(20,30,255);
		}
		.prettyTreecolorMeBlueL
		{	color:rgb(30,50,255);
		}
		
		.prettyTreecolorMeRed:hover 
		{	text-decoration:underline;
		}		
		.prettyTreecolorMeBlueL:hover 
		{	text-decoration:underline;
		}
	</style>
	
	<script type="text/javascript">
		// will toggle an element visible or invisible on the click of another element
		function prettyTreeshowHide(clickID, openID){$(document).ready(function()
		{	$(clickID).bind("click", function(e)
			{	if( "none" == $(openID).css("display") )
				{	$(openID).css({'display' : 'block'});
				}
				else
				{	$(openID).css({'display' : 'none'});
				}
			});
		});}
		
		function prettyTreetoggleElipsis(clickID, id, word){$(document).ready(function()
		{	$(clickID).bind("click", function(e)
			{	if($(id).html() == word)
				{	$(id).html(word+" ...");
				}
				else
				{	$(id).html(word);
				}
			});
		});}
		
		// toggles the color of something that has one of these classes
		function prettyTreetoggleColor(clickID, id){$(document).ready(function()
		{	$(clickID).bind("click", function(e)
			{	$(id).toggleClass("prettyTreecolorMeRed");
				$(id).toggleClass("prettyTreecolorMeBlueL");
				/*if($(id).css("color")=="red")
				{	$(id).css({"color":"rgb(30,50,255)"});
				}
				else
				{	$(id).css({"color":"red"});
				}*/
			});
		});}
		
		function prettyTreerawTestRequest(url, id)
		{	if(typeof rawTestRequest.uniqueID == 'undefined' )		// static uniqueID
			{	countMyself.counter = 0;	// It has not... perform the initilization
			}
			
			$.get("receive_variables.php",{"url":url},
			function(returned_data)
			{	$(id).html( + returned_data + $(id).html());
			});
		}

	</script>
	<?php
}

function buildTree($prefix, $var, $indent)			// builds the tree to print
{	$result = "";
	$tab="&nbsp;&nbsp;&nbsp; ";
	$tabMinusOne = "&nbsp;&nbsp; ";
	if("array" == gettype($var) || "object" == gettype($var))
	{	if("array" == gettype($var))
		{	$word = "array";
		}else
		{	$word = get_class($var)." object";
		}
		
		if(count($var) == 0)
		{	return $prefix.$word." { }";
		}
		
		$id1 = getUniqueID();
		$id2 = getUniqueID();
		
		$result .= $prefix."<span class='prettyTreecolorMeBlueL prettyTreehoverLine' id='".$id1."'>".$word." ...</span>\n";
		$result .= strMult($tab,$indent)."<span id='".$id2."' style='display:none'>";
		$result .= strMult($tab,$indent)."{";
		
		$onceAlready = false;
		foreach($var as $key => $value)
		{	if($onceAlready === false)
			{	$nextPrefix = $tabMinusOne."[$key] => ";
				$onceAlready = true;
			}else
			{	$nextPrefix = strMult($tab,$indent+1)."[$key] => ";
			}
			
			$result .= buildTree($nextPrefix, $value, $indent+1) . "<br>\n";
		}
		$result .= strMult($tab,$indent)."}</span>\n";
		
		$result .= '
			<script type="text/javascript">
				prettyTreeshowHide("#'.$id1.'", "#'.$id2.'");
				prettyTreetoggleElipsis("#'.$id1.'", "#'.$id1.'", "'.$word.'");
				prettyTreetoggleColor("#'.$id1.'", "#'.$id1.'");
			</script>
			';
		
		return $result;
	}
	else
	{	if("boolean" == gettype($var))
		{	if($var === false)
			{	$varDisplay = "false";
			}else
			{	$varDisplay = "true";
			}
		}else if("string" == gettype($var))
		{	$varDisplay = '"'.$var.'"';
		}else if("resource" == gettype($var))
		{	$varDisplay = 'resource: '.$var;	// prettyTree doensn't really support resources right now
		}else if($var === null)
		{	$varDisplay = "null";
		}else
		{	$varDisplay = $var;
		}
		
		return $prefix.$varDisplay;
	}
}
function strMult($c, $num)	//  copies a character or string on itself a number of times
{	$result = "";
	for($n=0; $n<$num; $n++)
	{	$result .= $c;
	}
	return $result;
}
function getUniqueID()
{	static $uniqueIDnum=0;	// number for creating unique non-conflicting ids (like unique_id_3 etc)
	$uniqueIDnum += 1;
	return "unique_id_".$uniqueIDnum;
}

?>
