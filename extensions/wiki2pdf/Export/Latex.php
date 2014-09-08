<?php
/**
 * Latex.php 
 * 
 * @author  Gerrit Beine
 * @date    23.07.2006
 */
 
class Export_Latex
{
	private $_secret = false;
	private $_internal = false;
	private $_div = false;

   /**
	* Ein Stack um verschachtelte Listenumgebungen zu zählen :) 
	*/
	private $_list = array ();
	
	
	private $_tree;
	private $_output;
	private $_quoted = false;
	private $_depth = 0;
	private $_markupstack = array ();
	
	function __construct( $tree, $output, $internal, $secret, $depth = 0 ) {
		$this->_tree = $tree;
		$this->_output = $output;
		$this->_internal = true == $internal ? true : false ;
		$this->_secret = true == $secret ? true : false ;
		$this->_depth = $depth;
	}
	
	public function parse( ) {
		$node = $this->_tree;
		$this->parseNode( $node );
	}
	
	private function parseNode ( $node, $last = false ) {
		if ( 'tag' == $node['type'] ) {
			switch ( $node['name'] ) {
// Document structure
				case 'ARTICLE':
					$this->parseChildren( $node );
					break;
				case 'HEADING':
					$this->parseHeading( $node );
					break;
				case 'PARAGRAPH':
					$this->parseParagraph( $node );
					break;
				case 'LINK':
					$this->parseLink( $node );
					break;
// Lists
				case 'LIST':
					$this->parseList( $node );
					break;
				case 'LISTITEM':
					$this->parseListItem( $node );
					break;
// Pre
				case 'PREBLOCK':
					$this->parsePreBlock( $node );
					break;
				case 'PRELINE':
					$this->parsePreLine( $node );
					break;
// Tables
				case 'TABLE':
					$this->parseTable( $node );
					break;
/*				case 'TABLECAPTION': // IGNORED, DONE IN parseTable()
					break; */
				case 'TABLEROW':
					$this->parseTableRow( $node, $last );
					break;
				case 'TABLEHEAD':
					$this->parseTableHead( $node, $last );
					break;
				case 'TABLECELL':
					$this->parseTableCell( $node, $last );
					break;
// HTML tags
				case 'XHTML:DIV':
					$this->parseDiv( $node );
					break;
				case 'XHTML:PRE':
					$this->parsePre( $node );
					break;
				case 'XHTML:SPAN':
					$this->parseChildren ( $node );
					break;
				case 'XHTML:BR':
					$this->_output->append( " " );
					break;
// Font formating
				case 'BOLD':
                                        $this->parseBold( $node );
					break;
				case 'ITALICS':
                                        $this->parseItalics( $node );
					break;
// Special Tags
				case 'EXTENSION':
					$this->parseExtension( $node );
					break;
				case 'SPACE':
					$this->_output->append( " " );
					break;
				default: // IGNORED
					break;
			}
		} elseif ( 'data' == $node['type'] ) {
			$this->_output->append( $this->parseContent( $node['content'] ) );
		} elseif ( 'root' == $node['type'] ) {
			$this->parseChildren( $node );
		}
	}

   /**
    * Verarbeitet die Kinder eines Knoten
    * 
    * @param	Node
    */
	private function parseChildren( $node ) {
		for ( $i = 0; $i < count( $node['children'] ); $i++ ) {
			$child =& $node['children'][$i];
			$last = $child === end( $node['children'] );
			$this->parseNode( $child, $last );
/*			if ( true == $last ) {
				$this->newline( $child, $node['children'][$i+1] );
			}*/
		}
	}
	
	private function parseContent ( $content ) {
		
		$content = preg_replace( "/\\\/", "$\\backslash$", $content );

		$content = preg_replace ( "/\\\$\\\\backslash\\\$/", "_____BS____", $content);
		$content = preg_replace ( "/\\\$/", "\\\\\$", $content);
		$content = preg_replace ( "/_____BS____/", "\$\\\\backslash\$", $content );

		$content = preg_replace( "/_/", "\\_", $content );
		$content = preg_replace( "/%/", "\\%", $content );
		$content = preg_replace( "/#/", "\\#", $content );
		$content = preg_replace( "/€/", "\\texteuro", $content );

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

// Document structure
	
	private function parseHeading ( $node ) {
		switch ( $node['attributes']['LEVEL'] + $this->_depth ) {
			case 1:
			    $this->_output->append( "\\section{" );
				$this->parseChildren ( $node );
			    $this->_output->append( "}\n\n" );
			    break;
			case 2:
			    $this->_output->append( "\\subsection{" );
				$this->parseChildren ( $node );
			    $this->_output->append( "}\n\n" );
			    break;
			case 3:
			    $this->_output->append( "\\subsubsection{" );
				$this->parseChildren ( $node );
			    $this->_output->append( "}\n\n" );
			    break;
			case 4:
			    $this->_output->append( "{\bf " );
				$this->parseChildren ( $node );
			    $this->_output->append( "}\n\\newline\n" );
			    break;
		}
	}
	
	private function parseParagraph( $node ) {
		$this->parseChildren( $node );
		$this->_output->append( "\n\n" );
	}

// Special tags

    private function parseExtension ( $node ) {
    	switch ( $node['attributes']['EXTENSION_NAME'] ) {
    		case 'strong':
    			$this->parseBold( $node );
    			break;
    		case 'nowiki':
    		    $this->parseChildren( $node );
    			break;
    	}
    } 

       /**
        * Parst einen Link
	*
	* @param	Node
	*/
	private function parseLink( $node ) {
		if ( 0 < count ( $node['attributes'] ) &&
		     isset ( $node['attributes']['TYPE'] ) &&
		     'external' == $node['attributes']['TYPE'] ) { // externen LINK behandeln
			$this->parseLinkExternal( $node );
		} else { // internen LINK behandeln
			$this->parseLinkInternal( $node );
		}
	}
	
   /**
	* Schreibt einen externen Link
	*
	* @param	Node
	* @todo		parseContent für den Text bei \href
	* @latex	\href{URL}{Text}
	* @latex	\url{URL}
	*/
	private function parseLinkExternal ( $node ) {
		if ( 0 < count ( $node['children'] ) &&
	             isset ( $node['children'][0]['type'] ) &&
		     'data' == $node['children'][0]['type'] ) { // Text vorhanden; URL verstecken
			$this->_output->append ( "\\href{" . $node['attributes']['HREF'] . "}{" . $node['children'][0]['content'] . "}" );
		} else { // kein Text vorhanden; nur die URL ausgeben
			$this->_output->append ( "\\url{" . $node['attributes']['HREF'] . ")" );
		}

	}

   /**
	* Schreibt einen internen Link
	*
	* @param	Node
	* @latex	\hyperref[Marke]{Text}
	* @latex	\includegraphics{Bild}
	*/
	private function parseLinkInternal ( $node ) {
		$target = $node['children'][0]; // TARGET holen
		$part = null;
		if ( isset ( $node['children'][1]['name'] ) && 'PART' == $node['children'][1]['name'] ) {
			$part = $node['children'][1]; // PART holen, wenn vorhanden
		}

		if ( preg_match( "/(Image|Bild):(.*)/", $target['children'][0]['content'], $matches ) ) { // Bilder
			if ( ! isset ($images[$matches[2]]) ) {
				return;
			}
			$image = $images[$matches[2]];
			if ( $image['width'] > 585 || $image['height'] > 500) {
				$this->_output->append( "\\includegraphics[width=\\textwidth]{" );
			} else {
				$this->_output->append( "\\includegraphics{" );			
			}
			$this->_output->append( $image['path'] );
			$this->_output->append( "}\n\n" );
		
		} elseif ( ! preg_match( "/#/", $target['children'][0]['content'] ) ) { // Referenzen im Dokument
			if ( null != $part ) {
				$this->_output->append( "\\hyperref[sec:" );
				$this->_output->append( $target['children'][0]['content'] );
				$this->_output->append( "]{" );
				$this->parseChildren( $part );
				$this->_output->append( "}" );	
			} else {
				// Was ist hier zu tun?		    
			}
		} elseif ( null != $part ) {
			$this->parseChildren( $part );
		}
	
	}

// Lists

       /**
        * Schreibt eine Liste
	*
	* @param	Node
	* @latex	\begin{itemize}
	* @latex	\begin{enumerate}
	* @latex	\end{itemize}
	* @latex	\end{enumerate}
	*/
	private function parseList( $node ) {
		if ( 0 < count( $this->_list ) ) { // Wenn eine Unterliste erstellt wird, diese etwas absetzen
			$this->_output->append("\n\n");			
		}
		
		switch ( $node['attributes']['TYPE'] ) {
			case 'bullet':
				$this->_output->append( "\\begin{itemize}\n" );
				break;
			case 'numbered':
				$this->_output->append( "\\begin{enumerate}\n" );
				break;
		}
		
		array_push( $this->_list, 1 );
		
		$this->parseChildren( $node );

		array_pop( $this->_list );		

		switch ( $node['attributes']['TYPE'] ) {
			case 'bullet':
				$this->_output->append( "\\end{itemize}\n" );
				break;
			case 'numbered':
				$this->_output->append( "\\end{enumerate}\n" );
				break;
		}

		if ( 0 == count( $this->_list ) ) {
			$this->_output->append("\n");
		}

	}

       /**
        * Schreibt eine Listenelement
	*
	* @param	Node
	* @latex	\item
	*/
	private function parseListItem( $node ) {
		$this->_output->append( "\\item " );
		$this->parseChildren( $node );
		$this->_output->append( "\n" );
	}

// Pre

   /**
	* Schreibt eine Umgebung mit dicktengleicher Schrift
	*
	* @param	Node
	* @latex	\begin{verbatim}
	* @latex	\end{verbatim}
	*/
	private function parsePreBlock( $node ) {
		$this->_pre = true;
		$this->_output->append( "\\begin{verbatim}\n" );
		$this->parseChildren( $node );
		$this->_output->append( "\\end{verbatim}\n" );
		$this->_pre = false;
	}

	private function parsePreLine( $node ) {
		foreach ( $node['children'] as $child ) {
			$text .= $child['content'];
		}
		$this->writePreformatted( $text . "\n" );
	}

   /**
	* Schreibt eine Umgebung mit dicktengleicher Schrift
	*
	* Wichtig: parsePre ist dabei nur für die HTML-Tags <pre> zuständig  
	* 
	* @param	Node
	* @latex	\begin{verbatim}
	* @latex	\end{verbatim}
	*/
	private function parsePre( $node ) {
		$this->_pre = true;
		print_r($node);
		$this->_output->append( "\\begin{verbatim}\n" );
		foreach ( $node['children'] as $child ) {
			$text .= $child['content'];
		}
		$lines = preg_split( "/(\n|\r\n)/", $text );
		if ( 0 == strlen( $lines[0] ) ) {
		    array_shift( $lines );
		}
		if ( 0 == strlen( end( $lines ) ) ) {
		    array_pop( $lines );
		}
		foreach ( $lines as $line ) {
    		    $this->writePreformatted( $line );
		}
		$this->_output->append( "\\end{verbatim}\n" );
		$this->_pre = false;
	}

	
// Tables

       /**
        * Schreibt eine Tabelle
	*
	* @param	Node
	*/
	private function parseTable( $node ) {
		$columns = $this->columns( $node );
		$rows = count( $node['children'] );
		$this->parseLtxtable ( $node, $columns );
/*		if ( $rows < 20 ) { 
			$this->parseTableX( $node, $columns );
		} else {
			#$this->parseSupertable( $node, $columns );
			$this->parseLongtable( $node, $columns );
		}*/
	}

	private function parseLtxtable( $node, $columns ) {
		$this->_output->append( "{\\huge LTXTABLE $columns}\n\n" );
	}
	
/*	private function parseTableX( $node, $columns ) {
		$this->_output->append( "\\begin{tabularx}{\columnwidth}{" );
		
		if ( $node['attributes']['COLUMNS'] ) {
			$this->_output->append( $node['attributes']['COLUMNS'] );
		} else {
			for ($i = 0; $i < $columns; $i++ ) {
				if ( $i+1 < $columns ) {
					$this->_output->append( "l " );
				} else {
					$this->_output->append( "X" );
			}
		}
		}
		$this->_output->append( "}\n" );
		if ( 'TABLECAPTION' == $node['children'][0]['name'] ) {
			$this->parseTableCaption( $node['children'][0], $columns );
		}
		$this->_output->append( "\\hline\n" );
		$this->parseChildren( $node );
		$this->_output->append( "\\end{tabularx}\n" );
	}

	private function parseSupertable( $node, $columns ) {
		$this->_output->append( "\\begin{supertabular*}{\columnwidth}{" );
		
		for ($i = 0; $i < $columns; $i++ ) {
			if ( $i+1 < $columns ) {
				$this->_output->append( "l " );
			} else {
				$this->_output->append( "l" );
			}
		}

		$this->_output->append( "}\n" );
		if ( 'TABLECAPTION' == $node['children'][0]['name'] ) {
			$caption = array_shift( $node['children'] );
		}
		if ( 'TABLEHEAD' == $node['children'][0]['children'][0]['name'] ) {
			$row = array_shift ( $node['children'] );
		}
		if ( is_array ( $row ) ) {
			$this->_output->append( "\\tablehead{" );
			$this->parseTableRow( $row );
			$this->_output->append( "}\n" );		
		}
		$this->_output->append( "\\tabletail{" );
		$this->_output->append( "\\multicolumn{" . $columns. "}{r}{Fortsetzung auf der nächsten Seite} \\\\\n" );
		$this->_output->append( "}\n" );
		$this->_output->append( "\\tablelasttail{}\n" );
		$this->parseChildren( $node );
		$this->_output->append( "\\end{supertabular*}\n" );
	}
			
	private function parseLongtable( $node, $columns ) {
		$this->_output->append( "\\begin{longtable}{" );
		
		for ($i = 0; $i < $columns; $i++ ) {
			if ( $i+1 < $columns ) {
				$this->_output->append( "l " );
			} else {
				$this->_output->append( "l" );
			}
		}

		$this->_output->append( "}\n" );
		if ( 'TABLECAPTION' == $node['children'][0]['name'] ) {
			$caption = array_shift( $node['children'] );
		}
		if ( 'TABLEHEAD' == $node['children'][0]['children'][0]['name'] ) {
			$row = array_shift ( $node['children'] );
		}
		if ( is_array( $caption ) &&  0 != count( $node['children'] ) ) {
			$this->parseTableCaption( $caption, $columns );
			$this->_output->append( "\\hline\n" );
			if ( is_array ( $row ) ) {
				$this->parseTableRow( $row );
			}
			$this->_output->append( "\\endfirsthead\n" );
		}
		
		if ( is_array ( $row ) ) {
			$this->parseTableRow( $row );
			$this->_output->append( "\\endhead\n" );		
		}
		$this->_output->append( "\\multicolumn{" . $columns. "}{r}{Fortsetzung auf der nächsten Seite} \\\\\n" );
		$this->_output->append( "\\endfoot\n" );
		$this->_output->append( "\\endlastfoot\n" );
		$this->parseChildren( $node );
		$this->_output->append( "\\end{longtable}\n" );
		}
	
	private function parseTableCaption( $node, $columns ) {
		if ( 0 == count( $node['children'] ) ) {
			return;
		}
		$this->_output->append( "\\multicolumn{" . $columns ."}{c}{");
		$this->parseChildren( $node );
		$this->_output->append( "} \\\\\n");
	}

	private function parseTableRow( $node, $last = false ) {
		$this->parseChildren( $node );
		$this->_output->append( "\\hline\n" );
	}

	private function parseTableHead( $node, $last = false ) {
		$this->_output->append( "{\\bf " );
		if ( isset ($node['children'][0]) && 
			'tag' == $node['children'][0]['type'] &&
		    'LIST' == $node['children'][0]['name'] ) {
			$this->parseSpecialTableCellOrHead( $node );
		} else {
			$this->parseChildren( $node );
		}
		$this->_output->append( "}" );
		$append = $last ? " \\\\\n" : " & ";
		$this->_output->append( $append );
	}

	private function parseTableCell( $node, $last = false ) {
		if ( isset ($node['children'][0]) && 
			'tag' == $node['children'][0]['type'] &&
		    'LIST' == $node['children'][0]['name'] ) {
			$this->parseSpecialTableCellOrHead( $node );
		} else {
			$this->parseChildren( $node );
		}
		$append = $last ? " \\\\\n" : " & ";
		$this->_output->append( $append );
	}
	
	private function parseSpecialTableCellOrHead( $node ) {
		if ( 'LISTITEM' == $node['name'] ) {
			$this->parseChildren( $node );
		} else {
			$this->parseSpecialTableCellOrHead( $node['children'][0] );
		}
	}*/
	
// HTML Tags
	
       /**
        * Parst DIV Container
	*
	* Der Exporter wird informiert, daß er soeben einen DIV parst 
	*
	* @param	Node
	* @todo		Verschachtelte DIVs unterstützen
	*/
	private function parseDiv( $node ) {
		$this->_div = true;
		switch ( $node['attributes']['CLASS'] ) {
			case 'intern': // intern war deutsch für internal ;-)
			case 'internal':
				if ( $this->_internal ) {
					$this->parseChildren( $node );
				}
				break;
			case 'secret': // geheime Informationen
				if ( $this->_secret ) {
					$this->parseChildren( $node );
				}
				break;
			case 'content': // Inhaltsverzeichnisse nicht ausgeben, das kann LaTeX besser
				break;
			default: // bei nichtklassifizierten DIVs alle Kindelemente ausgeben
				$this->parseChildren( $node );
				break;
		}
		$this->_div = false;
	}

// Font formatting

   /**
	* Schreibt Text fett
	*
	* @param	Node
	* @latex	{\bf }
	*/
	private function parseBold( $node ) {
		$this->_output->append( "{\\bf " );
		$this->parseChildren( $node );
		$this->_output->append( "}" );
	}

   /**
	* Schreibt Text kursiv
	*
	* @param	Node
	* @latex	{\it }
	*/
	private function parseItalics( $node ) {
		$this->_output->append( "{\\it " );
		$this->parseChildren( $node );
		$this->_output->append( "}" );
	}

// Helper functions

   /**
	* Berechnet die Anzahl der Spalten einer Tabelle
	* 
	* @param	Node 
	*/
	private function columns( $node ) {
		if ( 'TABLECAPTION' == $node['children'][0]['name'] ) {
			return count( $node['children'][1]['children'] );
		} else {
			return count( $node['children'][0]['children'] );
		}
	}

	private function newline( $node, $next  ) {
		$newline = $this->requireNewlineAfter( $node ) && $this->requireNewlineBefore( $next );
		if ( $newline ) {
			$this->_output->append( "\\newline\n" );
		}
#		if ( 'TABLE' == $node['name'] &&
#		 	 20 > count( $node['children'] ) &&
#		     'PARAGRAPH' == $next['name'] ) {
#			$this->_output->append( "\\newline\n\n" );
#		}
/*		if ( 'TABLE' == $node['name'] &&
		 	 20 > count( $node['children'] ) &&
		     ( 'TABLE' == $next['name'] || 'PARAGRAPH' == $next['name'] ||
		      ( 'HEADING' == $next['name'] && 4 == $next['attributes']['LEVEL'] ) ) ) {
#			$this->_output->append( "\\vspace{10mm}\n\n" );
			return true;
		}
		elseif ( $newline && 'PARAGRAPH' == $next['name'] &&
		     'XHTML:DIV' == $next['children'][0]['name'] &&
		     true == $this->_internal &&
		     'internal' == $next['children'][0]['attributes']['CLASS'] &&
		     'data' == $next['children'][0]['children'][0]['type'] ) {
#			$this->_output->append( "\n\\newline\n" );
			return true;
		}
		elseif ( $newline && 'PARAGRAPH' == $next['name'] &&
		     'XHTML:DIV' == $next['children'][0]['name'] &&
		     true == $this->_secret &&
		     'secret' == $next['children'][0]['attributes']['CLASS'] &&
		     'data' == $next['children'][0]['children'][0]['type'] ) {
#			$this->_output->append( "\n\\newline\n" );
			return true;
		}
		elseif ( $newline && 'PARAGRAPH' == $next['name'] &&
		     'XHTML:DIV' != $next['children'][0]['name'] ) {
#			$this->_output->append( "\n\\newline\n" );
			return true;
		}
		elseif ( $newline && 'TABLE' == $next['name'] ) {
#			$this->_output->append( "\n\\newline\n" );
			return true;
		}
		elseif ( $newline && 'HEADING' == $next['name'] && 4 == $next['attributes']['LEVEL'] ) {
#			$this->_output->append( "\n\\newline\n" );
			return true;
		}*/
	}

   /**
    * Prüft, ob ein Newline nach der aktuellen Node benötigt wird.
    * 
    * @param	Node
    */
	private function requireNewlineAfter( $node ) {
		if ( 'PARAGRAPH' == $node['name'] ) {
			$lastchild = end( $node['children'] );
			if ( 'data' == $lastchild['type'] ) {
				return true;
			}
			if ( in_array( $lastchild['name'] , array ( 'BOLD' , 'ITALICS', 'XHTML:DIV' ) ) ) {
				return true;
			}
		}
		return false;
	}

   /**
    * Prüft, ob ein Newline vor der nächsten Node benötigt wird.
    * 
    * @param	Node
    */
	private function requireNewlineBefore( $node ) {
		if ( 'PARAGRAPH' == $node['name'] ) {
			$lastchild = end( $node['children'] );
			if ( 'data' == $lastchild['type'] ) {
				return true;
			}
			if ( in_array( $lastchild['name'] , array ( 'BOLD' , 'ITALICS', 'XHTML:DIV' ) ) ) {
				return true;
			}
		}
		return false;
	}
	
	private function writePreformatted( $text ) {
/*			foreach ( $t_note as $t_line ) {
			    $i = 0; $cont = true;
			    if ( strlen($t_line) > 70 ) {
			        $i = 0;
				while ( $i + 70 < strlen( $t_line ) ) {
				    $t_sub = substr ( $t_line, $i, 70 );
				    $t_pos = strrpos ( $t_sub, ' ' );
				    $t_str = substr ( $t_line, $i, $t_pos ); 
				    $i += $t_pos;
				    array_push( $t_lines, $t_str );
				}
				$t_str = substr ( $t_line, $i, $t_pos ); 
				array_push( $t_lines, $t_str );
			    } else {
				array_push( $t_lines, $t_line );
			    }
			}*/ 
		if ( 68 > strlen( $text ) ) {
			$this->_output->append( $text ."\n" );
		} else {
			$lines = str_split( $text, 64 );
			foreach ( $lines as $line ) {
				$this->_output->append( $line );
				if ( $line != end( $lines ) ) {
					$this->_output->append( "  \\\n" );
				}
		    }
		}
	}
}

?>
