<?php

namespace Microsoft\QnAMaker;

class Exception extends \Exception
{
    public static $codeStr2Num = [
        'ExtractionFailed' => 4001,
    ];
}
