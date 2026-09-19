<?php

namespace App\Overrides;

use Mews\Captcha\Captcha as MewsCaptcha;

/**
 * Bound to the "captcha" container key in AppServiceProvider.
 *
 * mews/captcha 3.5 already casts the text position to int (PHP 8.1+) and supports
 * Intervention Image 3 and 4, so the old text() override is gone: it called
 * Font::valign(), which does not exist in Intervention Image 4 and made every
 * captcha image request fail with a 500. Keep this class as the place to customise the captcha.
 */
class Captcha extends MewsCaptcha
{
    //
}
