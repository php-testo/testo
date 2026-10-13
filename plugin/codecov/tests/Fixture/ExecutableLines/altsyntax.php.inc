<?php
function alt(array $items): void
{
    if ($items):
        foreach ($items as $item):
            echo $item;
        endforeach;
    elseif ($items === null):
        echo 'none';
    else:
        echo 'empty';
    endif;
}
