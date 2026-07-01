<?php

// Outside a class (free function): an assertion is only converted inside a test method.
function helper()
{
    $this->assertSame(3, $result);
}
