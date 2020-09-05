<?php


namespace Nomess\Helpers;


trait ReportHelper
{
    
    /**
     * @param string $message
     * @param string $filename
     */
    protected function report(string $message, string $filename = ROOT . 'var/log/error.log'): void
    {
        file_put_contents($filename, $message . "\n---------------------------------------------------------\n", FILE_APPEND);
    }
}
