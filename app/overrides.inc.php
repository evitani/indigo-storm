<?php

function targetOverrides(string $tool, bool $always = false): bool {

    $tool = str_replace('\\', '/', str_replace('()', '',$tool));

    if(substr($tool, 0, 5) === 'Core/') {
        $tool = 'Target' . substr($tool, 4);
    } elseif (substr($tool, 0, 12) !== 'Target/') {
        $tool = 'Target/' . $tool;
    }

    return
        file_exists(
            'app/' .
            str_replace('\\', '/', str_replace('()', '',$tool))
            . '.class.php')
        && (!_DEVMODE_ || $always);
}
