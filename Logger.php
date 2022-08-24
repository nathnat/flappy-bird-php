<?php

class Logger
{
    protected $logFile;

    public function __construct()
    {
        $this->logFile = fopen('log', 'a+');
    }

    public function log($string)
    {
        fwrite($this->logFile, $string . "\n");
    }
}
