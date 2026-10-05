<?php

namespace App\Module\AccountCentre\DTO;

// deze dto vangt de notificatie voorkeuren op uit het formulier
// het nieuwsbrief vinkje is weggehaald, dat schreef naar dezelfde kolom als dit vinkje
class NotificationSettingsDTO
{
    public bool $emailNotificationsEnabled = true;
}
