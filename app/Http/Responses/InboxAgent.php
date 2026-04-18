<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\User;

/**
 * Class InboxAgent
 *
 * Represents the structured response for an Inbox Agent.
 *
 * @package App\Http\Responses
 * @property string $name The name of the inbox agent.
 * @property string|null $timezone The agent's timezone (if available).
 * @property string|null $availability The agent's current availability status (active, away, out_of_office).
 * @property array $workingHours The agent's working hours schedule.
 */
class InboxAgent extends DataInterface
{
    /**
     * The ID of the inbox agent.
     *
     * @var int|null
     */
    public int $id;
    /**
     * The name of the inbox agent.
     *
     * @var string
     */
    public ?string $name;

    /**
     * The timezone of the inbox agent.
     *
     * @var string|null
     */
    public ?string $timezone;

    /**
     * The availability status of the inbox agent.
     *
     * @var string|null
     */
    public ?string $availability;

    /**
     * The working hours schedule of the inbox agent.
     *
     * @var array
     */
    public ?array $workingHours;

    /**
     * The assigned at timestamp for the inbox agent.
     *
     * @var string|null
     */
    public ?string $assigned_at;

    public string $email;

    /**
     * Whether the agent's billing is active (based on wallet charge).
     *
     * @var bool
     */
    public bool $is_active;


    /**
     * InboxAgent Response Constructor.
     *
     * @param User $inboxAgent The User model representing an inbox agent.
     */
    public function __construct(User $inboxAgent)
    {
        $this->id = $inboxAgent->id;
        $this->name = $inboxAgent->first_name ?? $inboxAgent->name;
        $this->email = $inboxAgent->email;
        $this->timezone = $inboxAgent->inboxAgentAvailability?->timezone;
        $this->availability = $inboxAgent->inboxAgentAvailability?->availability;
        $this->assigned_at = $inboxAgent?->pivot?->assigned_at ? $inboxAgent->pivot->assigned_at : null;
        $this->workingHours = $inboxAgent->inboxAgentWorkingHours->map(function ($schedule) {
            return [
                'day' => $schedule->day,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
            ];
        })->toArray();

        // 👇 New field for billing status
        $this->is_active = $inboxAgent->isInboxAgentBillingActive();
    }
}
