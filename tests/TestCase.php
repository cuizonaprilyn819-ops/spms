<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Test rendering must not share compiled views with the running application
        // or another test process, which can leave a response temporarily blank.
        $compiledViews = storage_path('framework/testing/views/'.getmypid());
        \Illuminate\Support\Facades\File::ensureDirectoryExists($compiledViews);
        config(['view.compiled' => $compiledViews]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
    }
}
