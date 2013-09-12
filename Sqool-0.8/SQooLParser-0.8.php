<?php
/*	See http://www.btetrud.com/Sqool/ for documentation

	Email BillyAtLima@gmail.com if you want to discuss creating a different license.
	Copyright 2009, Billy Tetrud.
	
	This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License.
	as published by the Free Software Foundation; either version 3, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.	
*/

require_once(dirname(__FILE__)."/../cept.php");	// exceptions with stack traces



class SqoolParserExtended extends SqoolParser {

	// gets a variable starting with a-z or A-Z and containing only the characters a-z A-Z 0-9 or _
	// puts the variable in $result
	// discards leading whitespace
	protected function variableKeyWord(&$result) {
		$startsWithANumber = $this->peek()->match("[0-9]");
		$this->unpeek();
		if($startsWithANumber !== false) {
			$this->save();
			$this->reject("Variable names cannot start with a numerical digit");
		}

		return $this->match('[a-zA-Z_][a-zA-Z0-9_]*', $result);
	}

	protected function int(&$result) {
		return $this->match('[0-9]+', $result);
	}


	protected function quote($quoteCharacter, &$result) {
		$this->save();

		if( ! $this->match($quoteCharacter)) {
			return $this->reject("Doesn't start with an open quote ($quoteCharacter)");
		}

		$string = '';
		while(true) {

			// match any characers that don't contain any part of an escape sequence
			if($this->match('[^'.$quoteCharacter.'\\]', $part)) {	// only escape sequence needed is \' because the rest is taken care by the php string it will be in
				$string .= $part;

			} else if($this->match($quoteCharacter)) {
				break;

			} else if($this->match('\\.', $sequence)) {
				$string .= $sequence;
			} else {
				return $this->reject("Expected an end-quote ($quoteCharacter), an escape sequence, or characters that don't contain a backslash or a quote ($quoteCharacter)");
			}
		}

		$result = $string;
		return $this->accept();
	}
}


class SqoolFetchParser extends SqoolParserExtended {

	public function topLevelMemberList(&$result) {
		$this->save();
		$this->memberList($result);	// always succeeds

		if( ! $this->end()) {
			return $this->reject("There's some unparsable junk at the end of your get memberList");
		} else {
			return $this->accept();
		}
	}


	// returns an array like this: array($memberName => $dataControl)
	public function memberList(&$result) {
		$this->save();

		$list = array();
		while($this->member($member)) {
			$list[$member['name']] = $member['dataControl'];
		}

		$result = $list;
		return $this->accept();
	}

	// returns an array like this: array('name'=>$name, ['dataControl'=>$dataControl])
	private function member(&$result) {
		$this->save();

		if($this->dataControlPartStart()) {
			return $this->reject("Start of another data control command");
		}

		if( ! $this->variableKeyWord($member)) {
			return $this->reject("Expected member");
		} else {
			$result = array('name'=>$member);
			if($this->dataControl($dataControl)) {
				$result['dataControl'] = $dataControl;
			} else {
				$result['dataControl'] = array();
			}

			return $this->accept();
		}
	}



	// returns an array like this: array($dataControlName=>$dataControlInfo, ...)
	private function dataControl(&$result) {
		$this->save();

		if( ! $this->match('\[')) {
			return $this->reject("Expected open bracket");
		}

		$parts = array();
		while($this->dataControlPart($part)) {
			$keys = array_keys($part);
			$key = $keys[0];
			if(array_key_exists($key, $parts)) {
				return $this->reject("More than one data control ".$key." in fetch (get) options");
			}
			$parts =  array_merge($parts, $part);
		}

		if( ! $this->match('\]')) {
			return $this->reject("Expected end bracket");
		}

		$result = $parts;
		return $this->accept();
	}

	private function dataControlPart(&$result) {
		$this->save();

		if($this->memberDataControl($list))				$result = array('members'=>$list);
		else if($this->condition($condition))			$result = array('cond'=>$condition);
		else if($this->sortExpression($sortExpression))	$result = array('sort'=>$sortExpression);
		else if($this->range($ranges))					$result = array('range'=>$ranges);
		else											return $this->reject("Didn't find a data control part");

		return $this->accept();
	}

	private function dataControlPartStart() {
		$this->peek();
		$result = $this->memberDataControlStart() || $this->conditionStart() || $this->sortExpressionStart() || $this->rangeStart();
		$this->unpeek();
		return $result;
	}

	// members: <memberList>
	private function memberDataControlStart() {
		return $this->match("members") && $this->match(":");
	}
	private function memberDataControl(&$result) {
		$this->save();

		if($this->memberDataControlStart()) {
			if($this->memberList($result)) {
				return $this->accept();
			} else {
				return $this->reject("Expected member list");
			}
		} else {
			return $this->reject("Didn't find a data control member list");
		}
	}

	// parses something like:
		// range: 0:10 34:234

	private function rangeStart() {
		return ($this->match("range") || $this->match("ranges")) && $this->match(":");
	}
	private function range(&$result) {
		$this->save();

		if( ! $this->rangeStart()) {
			return $this->reject("Didn't find a data control range");
		}

		$ranges = array();
		while($this->int($number1) && $this->match(":") && $this->int($number2)) {
			$ranges[] = array($number1, $number2);
		}

		if(count($ranges) === 0) {
			return $this->reject("Expected a range");
		}

		$result = $ranges;
		return $this->accept();
	}

	// parses something like:
		// sort <direction>: member1 member2 member3  <direction>: memberetc
	// returns an array of the form:
		// array(array('type'=>$type, 'value'=>$value), ...)
		// where $type is either 'member' or 'direction'
	private function sortExpressionStart() {
		if($this->match("sort")) {
			if($this->match(":")) {
				return true;
			}else {
				$result = $this->peek()->sortDirectionChange();
				$this->unpeek();
				return $result;
			}
		} else {
			return false;
		}
	}
	private function sortExpression(&$result) {
		$this->save();

		if( ! $this->sortExpressionStart()) {
			return $this->reject("Didn't find a sort control range");
		}

		$expression = array();
		while(true) {

			if($this->dataControlPartStart()) {
				break;

			} else if($this->sortDirectionChange($direction)) {
				$expression[] = array('type'=>'direction', 'value'=>$direction);

			} else if($this->variableKeyWord($member)) {
				$expression[] = array('type'=>'member', 'value'=>$member);

			} else {
				break;
			}
		}

		if(count($expression) === 0) {
			$this->reject('Expected to find members to sort by in sort expression');
		}

		$result = $expression;
		return $this->accept();
	}
	private function sortDirectionChange(&$result = null) {
		return ($this->match('asc', $result) || $this->match('desc', $result))
				&& $this->match(':');
	}


	// cond: <expression>
	private function conditionStart() {
		return ($this->match("cond") || $this->match("where")) && $this->match(":");
	}
	private function condition(&$result) {
		$this->save();

		if( ! $this->conditionStart()) {
			return $this->reject("Didn't find a data control condition");
		}

		if($this->condExpression($condition)) {
			if($condition == '') {
				return $this->reject('Didn\t find any condition');
			} else {
				$result = $condition;
				return $this->accept();
			}
		} else {
			return $this->reject("Expected cond expression");
		}
	}

	// after successful parsing, $result will contain an array of the form:
		// array(array('type'=> $type, 'part'=> $string), ...)
		// where $type is either 'word' (if its a variable name) or 'other' (if its not a variable name)
	public function condExpression(&$result) {
		$this->save();

		$condition = array();
		while(true) {

			if($this->dataControlPartStart()) {
				break;
			}

			if( $this->functionLikeCall($conditionPart)
				|| $this->quote('"', $conditionPart)
				|| $this->quote("'", $conditionPart)
				|| $this->specialCondionalSyntax($conditionPart)
				|| $this->otherConditionCharacters($conditionPart)
				|| $this->match("[=!]", $conditionPart)
			) {
				$type = 'other';
			} else if($this->variableKeyWord($conditionPart)) {
				$type = 'word';
			} else {
				break;
			}

			$condition[] = array('type'=>$type, 'string'=>$conditionPart);
		}

		$result = $condition;
		return $this->accept();
	}

	private function functionLikeCall(&$result) {
		$this->save();

		if($this->variableKeyWord($function) && $this->match("\\(") && $this->condExpression($condition) && $this->match("\\)")) {
			$result = $function."(".$condition.")";
			return $this->accept();
		} else {
			return $this->reject("");
		}
	}

	private function specialCondionalSyntax(&$result) {
		$this->save();
		if($this->match("=") && $this->match("null")) {
			$result = " is null ";
			return $this->accept();
		} else if($this->match("!=") && $this->match("null")) {
			$result = " is not null ";
			return $this->accept();
		} else {
			return $this->reject("Didn't find a null comparison");
		}
	}

	private function otherConditionCharacters(&$result) {
		return $this->match("[^]:_'\"=!a-zA-Z]+", $result);
	}

}


class SqoolTypeParser extends SqoolParserExtended {

	public function sclass(&$result) {
		$this->save();

		$fields = array();
		while($this->memberDefinition($member)) {
			if(array_key_exists($member['name'], $fields)) {
				$this->reject("'".$member['name']."' defined a second time");
			}
			$fields[$member['name']] = $member['attributes'];
		}

		if( ! $this->end()) {
			return $this->reject("There's some unparsable junk at the end of your member definition");
		}

		$result = array('fieldsInfo'=>$fields/*, 'idField'=>'id'*/);	// add id field only if one is defined
		return $this->accept();
	}

	// returns an array where the only member has a key (which represents the name of the member)
	// 		which points to an array of the form array($mainType[, $subtype][, ...])
	// examples of returned values: array("bogus"=>array("int"))  array("bogus2"=>array("list", "int")
	//		  						array("bogus3"=>array("someobjName")  array("bogus4"=>array("list", "whateverObjectName")
	private function memberDefinition(&$result) {

		$this->save();

		$typeParameters = array();
        while($this->variableKeyWord($type)) {
			$typeParameters[] = $type;
		}


		if( count($typeParameters) === 0) {
			return $this->reject("No type found");

		} else if( ! $this->match(":")) {
			return $this->reject("Expected a colon");

		} else if( ! $this->variableKeyWord($name)) {
			return $this->reject("Expected a variable");

		} else {
			$result = array('name'=>$name, 'attributes'=>array('typeParameters'=>$typeParameters));
			return $this->accept();
		}

	}
}



class SqoolParser {
	public static $html = false;	// false by default

	public $string;
	public $cursor;

	private $peekStack = array();

	function __construct($string) {
		$this->string = $string;
		$this->cursor = new SqoolParserCursor();
	}

	function save() {
		$this->cursor = $this->cursor->save();
	}
	function accept($charactersProccessed=null) {
		$this->cursor->accept($charactersProccessed);
		$this->cursor = $this->cursor->parent;
		return true;
	}
	function reject($message) {
		$substring = substr($this->string, $this->cursor->originalPosition, 15);
		$this->cursor->reject($message." at character ".$this->cursor->originalPosition." starting with \"".$substring."\"");
		$this->cursor = $this->cursor->parent;
		return false;
	}

	function peek() {
		$newCursor = $this->cursor->copy();
		array_push($this->peekStack, $this->cursor);
		$this->cursor = $newCursor;
		return $this;
	}
	function  unpeek() {
		$this->cursor = array_pop($this->peekStack);
		return $this;
	}

	// seek to a spot in the buffer
	function seek($where = null) {
		$this->cursor->curPosition = $where;
		return $this;
	}

	function match($regex, &$result=null, $eatWhitespace = true) {
		$characters = $this->peekMatchEatWhitespace($regex, $result, $eatWhitespace);
		$this->save();
		if($characters !== false) {
			return $this->accept($characters);
		} else {
			return $this->reject("Input did not match the expression: '".$regex."'");
		}
	}

	// match something without consuming it
	// returns length of match if it matches, false otherwise
	private function peekMatchEatWhitespace($regex, &$out = null, $eatWhitespace = true) {
		$length = 0;
		$dummy = null;
		if($eatWhitespace) $length = $this->rawPeek('([ \t\r\n]?)*', $dummy);
		if($length === false) return false;

		return $this->rawPeek($regex, $out, $length);
	}

	function end($eatWhitespace=true) {
		$this->save();
		$dummy = null;
		if($this->match("$", $dummy, $eatWhitespace)) {
			return $this->accept();
		} else {
			return $this->reject("Parser hasn't reached the end yet");
		}
	}

	function failureTrace() {
		$html = self::$html;

		if(count($this->cursor->rejectedSubCursors) === 0) throw new cept('Can\'t print out failure trace for a parser that hasn\'t failed');
		$failureTrace = $this->cursor->failureTrace($html);

		$failureTraceToString = function($failureTrace, $indentation=0) use(&$failureTraceToString, $html) {
			$message = '';
			if($failureTrace['message'] !== null) {
				$message = $failureTrace['message'];
			}

			if(count($failureTrace['list']) === 0) {
				if( ! $html)
					$message = str_repeat("  ", $indentation).$message;
				return $message;

			} else {
				$newIndentation = $indentation+1;
				if($failureTrace['message'] !== null)
					$message .= "\n";
				else if($indentation === 0)
					$newIndentation = 0;

				$traceStringList = array();
				foreach($failureTrace['list'] as $i) {
					$traceStringList[] = $failureTraceToString($i, $newIndentation);
				}

				if($html) {
					return $message.'<ul><li>'.implode('</li><li>', $traceStringList).'</li></ul>';
				} else {
					return str_repeat("  ", $indentation).$message.implode("\n", $traceStringList);
				}
			}
		};

		return $failureTraceToString($failureTrace, 0);
	}


	// match something without consuming it
	// returns length of match if it matches, false otherwise
	private function rawPeek($regex, &$result = null, $extraOffset=0) {
		$r = '/'.$regex.'/As';	// The A in /As means match from the beggining of the string (after the offset tho), and s means . matches newlines
		if($this->cursor === null) {
			throw new cept("Parser's cursor is null, indiciating the code didn't save every time it accepted or rejected parts");
		}

		if(preg_match($r, $this->string, $out, null, $this->cursor->curPosition+$extraOffset) === 1) {
			$length = strlen($out[0]);
			$result = $out[0];
			return $length+$extraOffset;
		} else {
			return false;
		}
	}
}

// intended to be used as basically a save state that can be reverted to if things fail to match
class SqoolParserCursor {
	public $parent = null;

	public $originalPosition=0, $curPosition;
	public $rejectionMessage = null;
	public $rejectedSubCursors = array();

	function __construct() {
		$this->curPosition = 0;
	}

	function copy() {
		$newCursor = new self();
		$newCursor->originalPosition = $this->curPosition;
		$newCursor->curPosition = $this->curPosition;
		return $newCursor;
	}

	function save() {
		$newCursor = $this->copy();
		$newCursor->parent = $this;
		return $newCursor;
	}

	function accept($charactersProccessed = null) {
		if($charactersProccessed === null) {
			$this->parent->curPosition += $this->curPosition - $this->originalPosition;
		} else {
			$this->parent->curPosition += $charactersProccessed;
		}

		return true;
	}
	function reject($message) {
		$this->curPosition = $this->originalPosition;
		if($this->parent !== null) {
			$this->parent->rejectedSubCursors[] = $this;
		}
		$this->rejectionMessage = $message;
		return false;
	}

	function failureTrace($html = false) {
		$listItems = array();
		foreach($this->rejectedSubCursors as $cursor) {
			$listItems[] = $cursor->failureTrace($html);
		}

		return array('message'=>$this->rejectionMessage, 'list'=>$listItems);
	}
}