<?php
// Script to remove the debug marker and clean up the form section
$file = 'd:\xampp\htdocs\locumlancer\templates\provider\profile\profile.html.twig';
$content = file_get_contents($file);

// The debug block to remove
$search = <<<'SEARCH'
                <div style="border: 2px solid red; padding: 10px; margin: 10px 0;">
                    <strong>DEBUG: Form Section</strong>
                    {# Manual Hidden Form for CV Upload #}
                    <form name="provider_cv" method="post" enctype="multipart/form-data" style="display:none">
                        <input type="hidden" name="provider_cv[_token]" value="{{ csrf_token('provider_cv') }}">
                        <input type="file" name="provider_cv[cv]" id="provider_cv_cv">
                    </form>
                </div>
SEARCH;

// The clean replacement
$replace = <<<'REPLACE'
                {# Manual Hidden Form for CV Upload #}
                <form name="provider_cv" method="post" enctype="multipart/form-data" style="display:none">
                    <input type="hidden" name="provider_cv[_token]" value="{{ csrf_token('provider_cv') }}">
                    <input type="file" name="provider_cv[cv]" id="provider_cv_cv">
                </form>
REPLACE;

// Try exact match first
$content = str_replace($search, $replace, $content, $count);

if ($count === 0) {
    // If exact match fails, try regex or partial match
    echo "Exact match failed. Trying to find the debug div...\n";
    
    // Regex to match the div with border red
    $pattern = '/<div style="border: 2px solid red;.*?<\/div>/s';
    $content = preg_replace($pattern, $replace, $content, 1, $count);
}

if ($count > 0) {
    file_put_contents($file, $content);
    echo "Successfully removed debug marker ($count replacement)\n";
} else {
    echo "Could not find debug marker to remove\n";
}
