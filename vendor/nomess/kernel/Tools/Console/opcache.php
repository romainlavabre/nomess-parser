<?php

try {
    if(!opcache_reset()) {
        echo 'Sorry, opcache is disabled';
    }
}catch(Throwable $e){}
