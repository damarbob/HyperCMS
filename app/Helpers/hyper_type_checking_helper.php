<?php
// hyper_type_checking_helper.php

if (!function_exists('isIntegerString')) {
    function isIntegerString($str) {
        return filter_var($str, FILTER_VALIDATE_INT) !== false;
    }
}