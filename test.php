<?php

$log = fopen('log', 'a+');

fwrite($log, 'caca');

sleep(5);

fwrite($log, 'pipi');