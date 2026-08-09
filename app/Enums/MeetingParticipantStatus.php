<?php

namespace App\Enums;

enum MeetingParticipantStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
