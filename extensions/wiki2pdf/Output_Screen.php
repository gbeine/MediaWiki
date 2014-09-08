<?php
/**
 * Output.php 
 * 
 * @author  Gerrit Beine
 * @date    24.07.2006
 */

class Output
{
	public $_file;
	private $_verbose;
	
	function __construct( $file, $verbose = false ) {
		$this->_file = $file;
		$this->_verbose = $verbose;
		if ( $this->_verbose ) {
			echo $this->_file . "\n";		
		}
	}
	
	public function append ( $string ) {
		echo $string;
	}
}

?>
