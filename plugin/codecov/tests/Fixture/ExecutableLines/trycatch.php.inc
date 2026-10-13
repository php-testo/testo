<?php
function attempt(callable $fn): ?string
{
    try {
        $fn();
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    } catch (\LogicException | \TypeError) {
        return null;
    } finally {
        $done = true;
    }
    return 'ok';
}
