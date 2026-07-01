<?php

// Outside a class (free function): the skip is only converted inside a test method.
function helper()
{
    $this->markTestIncomplete('todo');
}
