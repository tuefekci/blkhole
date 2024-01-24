<?php

// app/Helpers/UrlHelper.php

namespace App\Helpers;

class UrlHelper
{
    public static function cleanUrl($url)
    {
        // Remove http:// and https://
        $url = preg_replace("(^https?://)", "", $url);

        // Remove www.
        $url = preg_replace("(^www\.)", "", $url);

        return $url;
    }
}