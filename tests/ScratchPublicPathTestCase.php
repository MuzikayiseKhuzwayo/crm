<?php

namespace VentureDrake\LaravelCrm\Tests;

use Illuminate\Filesystem\Filesystem;

/**
 * A TestCase whose public path is a throwaway directory.
 *
 * The service provider evaluates public_path() when it registers its publish
 * map in boot(), so a test that relocates the path afterwards publishes into
 * testbench's own public/ regardless. The path has to be set before providers
 * boot, which means here rather than in a beforeEach.
 *
 * The directory is deliberately *not* created — a test that wants it present
 * makes it, and one that wants the "the build has not created public/ yet"
 * case simply leaves it alone.
 */
abstract class ScratchPublicPathTestCase extends TestCase
{
    protected string $scratchPublicPath;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->scratchPublicPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm-public-'.uniqid();

        $app->usePublicPath($this->scratchPublicPath);
    }

    protected function tearDown(): void
    {
        if (isset($this->scratchPublicPath) && is_dir($this->scratchPublicPath)) {
            (new Filesystem)->deleteDirectory($this->scratchPublicPath);
        }

        parent::tearDown();
    }
}
