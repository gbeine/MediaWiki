<?php

require_once( 'PDFCreator.php' );

function get_form ( ) {
    global $wpdf ;

    return "<form method='post'>
<h2>Export as PDF</h2>
<table>
<tr>
<td>Start with article:</td><td><input type='text' name='start_article' value='' size='40'/></td>
</tr>
<tr>
<td>Traversion depth:</td><td><input type='text' name='depth' value='{$wpdf['depth']}' size='40'/></td>
</tr>
<tr>
<td>No titlepage:</td><td><input type='checkbox' name='title' value='1'></td>
</tr>
<tr>
<td>No table of contents:</td><td><input type='checkbox' name='toc' value='1'></td>
</tr>
<tr>
<td>Include internals:</td><td><input type='checkbox' name='internal' value='1'></td>
</tr>
<tr>
<td>Include secrets:</td><td><input type='checkbox' name='secret' value='1'></td>
</tr>
<tr>
<td>Verbose:</td><td><input type='checkbox' name='verbose' value='1'></td>
</tr>
<tr>
<td colspan='2'><input type='submit' name='convert' value='Start'></td>
</tr>
</table>
</form>";
}

if ( $_POST['convert'] && 0 < strlen( $_POST['start_article'] ) ) {
    header("Content-type: text/plain");
    $prop = array( );
    $prop['article'] = $_POST['start_article'];
    $prop['internal'] = $_POST['internal'];
    $prop['secret'] = $_POST['secret'];
    $prop['depth'] = $_POST['depth'];
    $prop['verbose'] = $_POST['verbose'];
    $prop['title'] = $_POST['title'];
    $prop['toc'] = $_POST['toc'];

	$pdfc = new PDFCreator();

	$pdfc->create( $prop );
	$out = $pdfc->getLink();
} else {
    $out = get_form ( ) ;
}

?>
