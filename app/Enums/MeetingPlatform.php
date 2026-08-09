<?php

namespace App\Enums;

enum MeetingPlatform: string
{
    case GoogleMeet = 'google_meet';
    case Zoom = 'zoom';
    case MicrosoftTeams = 'microsoft_teams';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GoogleMeet => 'Google Meet',
            self::Zoom => 'Zoom',
            self::MicrosoftTeams => 'Microsoft Teams',
            self::Other => 'Other',
        };
    }
}
