<?php

// Outside a class (free function): a typed assertion is only converted inside a test method.
function helper()
{
    $this->assertGreaterThan(0, $n);
    $this->assertArrayHasKey('id', $row);
}
