<?php

class Pipe
{
    protected $type;

    function __construct($type)
    {
        $this->type = $type;

    }

    function render()
    {
        if ($this->type === 'up') {

        } else {
            
        }
    }
}