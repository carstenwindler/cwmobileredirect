<?php

use CarstenWindler\Cwmobileredirect\Main as Cwmobileredirect;
use TYPO3\CMS\Core\Tests\UnitTestCase;

/**
 * @covers \CarstenWindler\Cwmobileredirect\Main
 */
class RendererTest extends UnitTestCase
{
    /**
     * @var array
     */
    protected $defaultConfiguration;

    protected function setUp()
    {
        $this->defaultConfiguration = array(
        );
    }

    protected function tearDown()
    {
        unset($this->defaultConfiguration);
    }

    /**
     * @test
     */
    public function instanceIsCreated()
    {
        // The purpose of this test is to make sure that no upper/lower case issues happen
        // (developing on Windows makes you cautious)
        $instance = Cwmobileredirect::getInstance();

        $this->assertInstanceOf(Cwmobileredirect::class, $instance);
    }
}
