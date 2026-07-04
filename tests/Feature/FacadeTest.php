<?php

use Pirabyte\ERecht24Laravel\ERecht24 as ERecht24Service;
use Pirabyte\ERecht24Laravel\Facades\ERecht24 as ERecht24Facade;
use Pirabyte\ERecht24Laravel\Tests\TestCase;

uses(TestCase::class);

it('resolves the same service binding as the container', function () {
    expect(ERecht24Facade::getFacadeRoot())
        ->toBe($this->app->make('erecht24'))
        ->toBe($this->app->make(ERecht24Service::class));
});
