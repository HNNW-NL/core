<?php

namespace App\Tests\Module\Main\Service;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WorkPackageWorkflowServiceTest extends KernelTestCase
{
    // canTransition

    public function testWhenStatusIsDraftAndNewStatusIsPlannedShouldReturnTrue(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenStatusIsDraftAndNewStatusIsCancelledShouldReturnTrue(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenStatusIsPlannedAndNewStatusIsOpenShouldReturnTrue(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenStatusIsPlannedAndNewStatusIsCancelledShouldReturnTrue(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenStatusIsOpenAndNewStatusIsAssignedShouldReturnTrue(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenStatusIsOpenAndNewStatusIsCancelledShouldReturnTrue(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenStatusIsAssignedAndNewStatusIsClosedShouldReturnTrue(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenStatusIsAssignedAndNewStatusIsCancelledShouldReturnTrue(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenStatusIsClosedShouldReturnFalse(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenStatusIsCancelledShouldReturnFalse(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // getAllowedTransitions

    public function testWhenTransitionsIsValidTransactionShouldPossibleTransactions(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenTransitionsIsNotValidTransactionShouldReturnEmptyArray(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
