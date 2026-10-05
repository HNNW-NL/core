<?php

namespace App\Module\AccountCentre\DTO;

// deze dto vangt de notificatie voorkeuren op uit het formulier
// het is losgekoppeld van de entity, zodat het formulier ook velden mag hebben die nog niet in de database staan
class NotificationSettingsDTO
{
    public bool $emailNotificationsEnabled = true;

    // dit veld heeft nog geen kolom in de database, dus het wordt nu nog niet bewaard
    public bool $newsletterEnabled = true;
}
