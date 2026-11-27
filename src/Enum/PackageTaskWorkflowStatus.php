<?php

namespace App\Enum;

enum PackageTaskWorkflowStatus: string
{
    public const string STATUS_SCOPE = 'PackageTask';

    case OPEN = 'Open';
    case ASSIGNED = 'Assigned';
    case IN_PROGRESS = 'In progress';
    case SUBMITTED = 'Submitted';
    case CHANGES_REQUESTED = 'Changes requested';
    case ACCEPTED = 'Accepted';

    public static function fromStoredName(?string $name): ?self
    {
        $normalized = strtolower(trim((string) $name));

        foreach (self::cases() as $status) {
            if (strtolower($status->value) === $normalized) {
                return $status;
            }
        }

        return match ($normalized) {
            'active' => self::OPEN,
            'toegewezen' => self::ASSIGNED,
            'in-progress', 'in behandeling', 'bezig' => self::IN_PROGRESS,
            default => null,
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN => [self::ASSIGNED],
            self::ASSIGNED => [self::IN_PROGRESS],
            self::IN_PROGRESS => [self::SUBMITTED],
            self::SUBMITTED => [self::CHANGES_REQUESTED, self::ACCEPTED],
            self::CHANGES_REQUESTED => [self::IN_PROGRESS],
            self::ACCEPTED => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
