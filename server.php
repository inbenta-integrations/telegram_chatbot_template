<?php

require "vendor/autoload.php";

use Inbenta\TelegramConnector\TelegramConnector;

//Instance new TelegramConnector
$appPath = rtrim(__DIR__, '/') . '/';
$app = new TelegramConnector($appPath);

//Handle the incoming request
$app->handleRequest();
