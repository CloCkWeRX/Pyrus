<?php
if (function_exists('PEAR2_Autoload')) {
    return;
} else {
    function PEAR2_Autoload($class)
    {
        $fp = @fopen(str_replace('_', '/', $class) . '.php', 'r', true);
        if ($fp) {
            fclose($fp);
            require str_replace('_', '/', $class) . '.php';
            return true;
        }
        return false;
    }

    function PEAR2_Autoload_strict($class)
    {
        if (substr($class, 0, 6) !== 'PEAR2_') {
            return false;
        }
        $fp = @fopen(str_replace('_', '/', $class) . '.php', 'r', true);
        if ($fp) {
            fclose($fp);
            require str_replace('_', '/', $class) . '.php';
            return true;
        }
        return false;
    }
}