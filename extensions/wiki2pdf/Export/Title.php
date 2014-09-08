<?php
/**
 * Latex.php 
 * 
 * @author  Gerrit Beine
 * @date    23.07.2006
 */

class Export_Title
{
	private $_tree;
	private $_output;
	private $_finished = false;

	function __construct( $tree, $output ) {
		$this->_tree = $tree;
		$this->_output = $output;
	}

	public function parse( ) {
		$node = $this->_tree;
		$this->parseNode ( $node );
	}
	
	private function parseNode ( $node ) {
		if ( $this->_finished ) {
			return;
		}
		if ( 'tag' == $node['type'] ) {
			switch ( $node['name'] ) {
				case 'SPACE':
					$this->_output->append( " " );
					break;
				case 'TABLE':
					$this->parseTable( $node );
					break;
				default:
					$this->parseChildren( $node );
					break;
			}
		} elseif ( 'data' == $node['type'] ) {
			$this->_output->append( $this->parseContent( $node['content'] ) );
		} elseif ( 'root' == $node['type'] ) {
			$this->parseChildren( $node );
		}
	}
	
	private function parseChildren( $node ) {
		for ( $i = 0; $i < count( $node['children'] ); $i++ ) {
			$child = $node['children'][$i];
			$this->parseNode( $child );
		}
	}

	private function parseContent ( $content ) {
		
		$content = preg_replace( "/\\\/", "$\\backslash$", $content );

		$content = preg_replace ( "/\\\$\\\\backslash\\\$/", "_____BS____", $content);
		$content = preg_replace ( "/\\\$/", "\\\\\$", $content);
		$content = preg_replace ( "/_____BS____/", "\$\\\\backslash\$", $content );

		$content = preg_replace( "/_/", "\\_", $content );
		$content = preg_replace( "/#/", "\\#", $content );
		$content = preg_replace( "/€/", "\\texteuro", $content );

		$content = preg_replace( "/>/", "$>$", $content );
		$content = preg_replace( "/</", "$<$", $content );

		if ( 'ampersand' == end( $this->_markupstack ) && preg_match( "/^(gt|lt|amp);/", $content ) ) {
			$content = preg_replace( "/^gt;/", "$>$", $content );
			$content = preg_replace( "/^lt;/", "$<$", $content );
			$content = preg_replace( "/^amp;/", "\\&", $content );
			$content = preg_replace( "/^bsp;/", " ", $content );
			array_pop( $this->_markupstack );
		}

// Wiki Markup bold
		if ( preg_match( "/'''(.*)'''/", $content, $matches ) ) {
			$content = preg_replace( "/'''(.*)'''/", "{\\bf $1}", $content );
		} elseif ( preg_match( "/'''/", $content ) ) {
			if ( 'bold' == end( $this->_markupstack ) ) {
				$content = preg_replace ( "/'''/", "}", $content );
				array_pop( $this->_markupstack );
			} else {
				$content = preg_replace ( "/'''/", "{\\bf ", $content );
				array_push( $this->_markupstack, 'bold' );
			}
		}

// Wiki Markup italic
		if ( preg_match( "/''(.*)''/", $content, $matches ) ) {
			$content = preg_replace( "/''(.*)''/", "{\\it $1}", $content );
		} elseif ( preg_match( "/''/", $content ) ) {
			if ( 'italics' == end( $this->_markupstack ) ) {
				$content = preg_replace ( "/''/", "}", $content );
				array_pop( $this->_markupstack );
			} else {
				$content = preg_replace ( "/''/", "{\\it ", $content );
				array_push( $this->_markupstack, 'italics' );
			}
		}

		if ( preg_match( "/&$/", $content ) ) {
			array_push( $this->_markupstack, 'ampersand' );
			$content = preg_replace( "/&$/", "", $content );
		}
				
		if ( "\"" == $content ) {
			if  ( $this->_quoted ) { 
				$content = "''";
				$this->_quoted = false;
			} else {
				$content = "``";
				$this->_quoted = true;
			}
		}
		return $content;
	}
	
	private function parseTable ( $node ) {
		$this->_output->append( "\\begin{titlepage}\n\n");
		$this->_output->append( "\\vspace{\fill}{\scalebox{0.25}[0.25]{\includegraphics{logo.pdf}}}\n\n");
		$i = 0;
		if ( $this->title( $node['children'][0] ) ) {
			$this->parseTitle ( $node['children'][0] );
			$i++;
		}
		if ( $this->title( $node['children'][1] ) ) {
			$this->parseTitle ( $node['children'][1] );
			$i++;
		}
		$this->_output->append( "\\vspace*{\\fill}\n\n" );
		$this->_output->append( "{\\parbox{\\textwidth}{\n" );
		$this->_output->append( "\\begin{tabbing}\n" );
		$this->tabbing( $node, $i );
		for ( $j = $i; $j < count( $node['children'] ); $j++ ) {
			$this->parseRow ( $node['children'][$j] );
		}
		$this->_output->append( "{\\bf Datum:} \\> \\today\\\\\n" );
		$this->_output->append( "\\end{tabbing}\n}}\n\n" );
		$this->_output->append( "\\end{titlepage}\n\n");
		$this->_finished = true;
	}
	
	private function parseTitle ( $node ) {
		$this->_output->append( "\\vspace*{\\fill}{{\\Huge " );
		$this->parseChildren( $node['children'][0] );
		$this->_output->append( "}}\n\n" );
	}
	
	private function parseRow ( $node ) {
		$this->_output->append( "{\\bf " );
		$this->parseChildren( $node['children'][0] );
		$this->_output->append( ":} \\> " );
		$this->parseChildren( $node['children'][1] );
		$this->_output->append( "\\\\\n" );
	}
	
	private function title ( $node ) {
		return isset ( $node['children'][0]['attributes']['COLSPAN'] ) &&
               2 == $node['children'][0]['attributes']['COLSPAN'];
	}
	
	private function tabbing( $node, $i ) {
		$length = 0;
		$content = "";
		for ( $j = $i; $j < count( $node['children'] ); $j++ ) {
			if ( strlen( $node['children'][$j]['children'][0]['children'][0]['content'] ) >= $length ) {
				$length = strlen( $node['children'][$j]['children'][0]['children'][0]['content'] );
				$content = $node['children'][$j]['children'][0]['children'][0]['content'];
			}
		}
		$this->_output->append( "{\\bf " );
		$this->_output->append( $content );
		$this->_output->append( ":} \\qquad\\=\\kill\n" );
	}
}

?>
