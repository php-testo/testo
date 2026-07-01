<?php

// Outside a class (free function): a skip is only converted inside a test method.
function helper()
{
    $this->markTestSkipped('x');
}
