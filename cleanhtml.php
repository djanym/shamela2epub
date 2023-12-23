<?php

require 'vendor/autoload.php';

$file_path = 'book_src/1';
$file_name = '001.htm';

// Read the content of the HTML file
$html_content = file_get_contents( $file_path . '/' . $file_name );

// Extract content within <body> tags
if ( preg_match( '/<body.*?>(.*?)<\/body>/si', $html_content, $matches ) ) {
    $body_content = $matches[1];
} else {
    $body_content = ''; // Handle the case where <body> tags are not found
}

// Remove <div> elements with class="PageHead"
$body_content = preg_replace( '/<div\s+class\s*=[\'"]PageHead[\'"][^>]*>.*?<\/div>/i', '', $body_content );

// Remove <span> elements with class="symbol"
$body_content = preg_replace( '/<span\s+class=[\'"]symbol[\'"][^>]*>(\s*)<\/span>/im', '$1', $body_content );
$body_content = preg_replace( '/<span\s+class=[\'"]symbol[\'"][^>]*>(.*?)<\/span>/ism', '$1', $body_content );

// Replaces some artificial stuff.
//$body_content = preg_replace('/<font[^>]*>([،:\[\]\.,"\'\(\)\*\/\-=]{1,2})<\/font>/ium', '$1', $body_content);
$body_content = preg_replace( '/<font[^>]*>(.{1,2}?)<\/font>/ium', '$1', $body_content );

// Replace <p> and </p> with <br/>
$body_content = preg_replace( '/<p>/im', '<br/>', $body_content );
$body_content = preg_replace( '/<\/p>/im', '<br/>', $body_content );

// Replaces white spaces.
$body_content = preg_replace( '/&nbsp;/im', ' ', $body_content );
$body_content = preg_replace( '/[ ]+/im', ' ', $body_content );

// Replaces deprecated <font> with span.
$body_content = preg_replace( '/<font[^>]*color=[\'"]?[^\'"]+[\'"]?[^>]*>(.+)<\/font>/iUm', '<span class="highlighted">$1</span>', $body_content );

// Remove <hr>.
$body_content = preg_replace( '/<hr[^>]*>/im', '', $body_content );

echo $body_content;
echo "<br>============== FOOTNOTES REPLACED ================<br>\n\n";

// Regular expression to parse each page separately
$body_content = preg_replace_callback( '/<div class=[\'"]?PageText[\'"]?[^>]*>(.+?)<\/div>/us', 'replaceFootnotes', $body_content );

// Remove all divs.
$body_content = preg_replace( '/<div[^>]*>/', '', $body_content );
$body_content = preg_replace( '/<\/div>/', '', $body_content );
echo $body_content;

$html_content = '<!DOCTYPE html><html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" type="text/css" href="../css/styles.css">
</head><body>' . $body_content . '</body></html>';

// Save the modified content to a new file
file_put_contents( $file_path . '/_result.html', $html_content );
//file_put_contents( $file_path . '/result-copy-' . time() . '.html', $html_content );

// Output a message indicating success
echo "Changes applied and saved";

function replaceFootnotes( $html ) {
    global $footnotes;
    $out = $html[0];
    $out = preg_replace_callback( '/<div class=[\'"]?footnote[\'"]?[^>]*>(.+?)<\/div>/us', 'collectFootnotes', $out );
    $out = preg_replace_callback( '/<sup[^>]*><span[^>]*>\((\d+)\)<\/span><\/sup>/u', 'replaceFootnotesInText', $out );

    return $out;
}

/**
 * Collects footnotes from the bottom of each page.
 *
 * @param        $html
 *
 * @return string
 * @global array $footnotes
 */
function collectFootnotes( $html ) : string {
    global $footnotes;
    $footnotes_html = $html[0];
    // Initialize an array to store the footnotes
    $footnotes = [];

    // Find all matches
    preg_match_all( '/<span class="highlighted">\((\d+)\)<\/span>(.*?)(?:<br\/>|$)/isum', $footnotes_html, $matches, PREG_SET_ORDER );

    // Iterate through matches and populate the $footnotes array
    foreach ( $matches as $match ) {
        $fn_number               = arab2latin( $match[1] );
        $fn_text                 = trim( strip_tags( $match[2] ), " \n\r\t\v\0." );
        $footnotes[ $fn_number ] = $fn_text;
    }

    return '';
}

/**
 * Replaces footnote references in the text with the actual footnotes.
 * Actual footnotes collected from bottom of each page.
 *
 * @param        $html
 *
 * @return string
 * @global array $footnotes
 */
function replaceFootnotesInText( $html ) : string {
    global $footnotes;

    $fn_num = arab2latin( $html[1] );

    if ( isset( $footnotes[ $fn_num ] ) ) {
        return ' <span class="footnote-inline">(' . $footnotes[ $fn_num ] . ')</span> ';
    }

    // Return as it was.
    return $html[0];
}

function arab2latin( $input ) {
    $arabicNumbers = [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ];
    $latinNumbers  = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ];

    // Replace Arabic numbers with Latin numbers.
    return str_replace( $arabicNumbers, $latinNumbers, $input );
}
