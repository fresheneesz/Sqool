<?php	// version 2012 01 08

class cept extends Exception {
	public $trace, $cause;
    public static $html=false;
	
	function cept($msgIn, $codeIn='', $dataIn=0) {
		$this->message = $msgIn;	// error message
		$this->code = $codeIn;		// error code
		$this->data = $dataIn;		// user defined error data
		$this->trace = debug_backtrace();
		$this->cause = null;
	}
	
	function __toString() {
		$html = self::$html;
		$fileNameWrapper = function($fileName) use($html) {
			if($html) {
				return '<span style="color:green;">'.$fileName.'</span>';
			} else {
				return $fileName;
			}
		};
		$lineNumberWrapper = function($lineNumber) use($html) {
			if($html) {
				return '<span style="color:blue;">'.$lineNumber.'</span>';
			} else {
				return $lineNumber;
			}
		};
		$sectionWrapper = function($section) use($html) {
			if($html) {
				return '<div>'.$section.'</div>';
			} else {
				return $section."\n";
			}
		};

		if(self::$html) {
          	$arrow = ' <span style="color:red;"><-</span> ';
        }
        else {
           	$arrow = ' <- ';
        }
    
        $shortTrace = "";
        $count = count($this->trace);
		for($n=0; $n<$count; $n++) {

		    $fileName = $lineNumber = $functionName = $className = "";
		    
		    if($n>0)
				$shortTrace .= $arrow;

		    if(array_key_exists("file", $this->trace[$n]))
		        $fileName = basename($this->trace[$n]["file"]);

		    if(array_key_exists("line",$this->trace[$n]))
		        $lineNumber = $this->trace[$n]["line"];

		    if($n+1<$count && array_key_exists("function",$this->trace[$n+1]))
		        $functionName = " ".$this->trace[$n+1]["function"];

			$shortTrace .= $fileNameWrapper($fileName).$functionName.":".$lineNumberWrapper($lineNumber)." ";
		}
		
		if($this->code === '') {
			$exceptionCodeText = "";
		}else {
			$exceptionCodeText = "Exception code $this->code: ";
		}

		if($this->cause !== null) {
			$causeText = $sectionWrapper('*caused by*').$this->cause;
		} else {
			$causeText = '';
		}

		return  $sectionWrapper("cept - ".$exceptionCodeText."$this->message").   // (for more trace information, catch the thrown 'cept' object)
				$sectionWrapper("Short Trace: ".$shortTrace.$causeText);
	}

	// adds a cause
	// returns $this for convenience
	function causedBy(Exception $e) {
		$this->cause = $e;
		return $this;
	}
}

// sets up errors and notices to throw errors rather than 
function errorHandler($errno, $errstr, $errfile, $errline) {
	if(error_reporting() !== 0) {
		throw new cept($errstr, $errno);
	}
}
set_error_handler('errorHandler');

?>
