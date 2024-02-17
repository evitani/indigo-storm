<?php

if($_SERVER['REQUEST_URI'] === '/favicon.ico'){
    header("Content-Type: image/x-icon");
    readfile('app/favicon.ico');
    exit;
}
