<?php

// Outside a class (free function): the expectation run is only folded inside a test method.
function helper()
{
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('boom');
}
