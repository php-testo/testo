<?php

$service = new Service();

test('uses captured variable', function () use ($service) {
    expect($service->run())->toBeTrue();
});
