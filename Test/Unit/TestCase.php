<?php

namespace EinsUndEins\TransactionMailExtender\Test\Unit;

use Magento\Framework\App\ObjectManager;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
    /**
     * Create ObjectManager stub. You can define which classes should return which object with $mockClasses.
     *
     * @param array $mockClasses
     */
    protected function mockObjectManager(array $mockClasses)
    {
        $objectManagerStub = $this->createMock(ObjectManager::class);
        $objectManagerStub->method('get')
            ->will($this->returnValueMap(
                $mockClasses
            ));
        ObjectManager::setInstance($objectManagerStub);
    }
}
