<?php

namespace VentureDrake\LaravelCrm\Exceptions;

use Exception;

class HandoffGateIncompleteException extends Exception
{
    /**
     * @var array
     */
    protected array $pendingGates;

    public function __construct(array $pendingGates, string $message = 'Cannot transition deal to Won: Operational clearance gates are incomplete.')
    {
        $this->pendingGates = $pendingGates;
        $detailedMessage = $message.' Pending gates: '.implode(', ', $pendingGates);
        parent::__construct($detailedMessage, 422);
    }

    public function getPendingGates(): array
    {
        return $this->pendingGates;
    }
}
