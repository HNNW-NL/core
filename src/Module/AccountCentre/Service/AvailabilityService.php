<?php

namespace App\Module\AccountCentre\Service;

class AvailabilityService
{
    public function encodeDaySchedule(array $daysInput): string
    {
        $schedule = [];

        foreach (range(0, 6) as $dayNum) {
            $dayConfig = $daysInput[$dayNum] ?? [];

            if (!isset($dayConfig['enabled'])) {
                continue;
            }

            $schedule[$dayNum] = [
                'start' => $dayConfig['start'] ?? '08:00',
                'end' => $dayConfig['end'] ?? '17:00',
            ];
        }

        return json_encode($schedule, JSON_THROW_ON_ERROR);
    }

    public function decodeDaySchedule(?string $note): array
    {
        if (empty($note)) {
            return [];
        }

        try {
            $schedule = json_decode($note, true, 512, JSON_THROW_ON_ERROR);

            return is_array($schedule) ? $schedule : [];
        } catch (\JsonException $e) {
            return [];
        }
    }
}