<?php

use PicoPHP\Services\Env;

$get = function(Env $env) {
    return $env("APP_NAME", "TEST");
};