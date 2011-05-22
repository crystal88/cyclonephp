<?php

function log($class, $level, $message, $code = NULL) {
    Log::for_class($class)->add_entry($level, $message, $code);
}

function log_debug($class, $message, $code) {
    Log::for_class($class)->add_debug($message, $code);
}

function log_info($class, $message, $code) {
    Log::for_class($class)->add_info($message, $code);
}

function log_warning($class, $message, $code) {
    Log::for_class($class)->add_warning($message, $code);
}

function log_error($class, $message, $code) {
    Log::for_class($class)->add_error($message, $code);
}
