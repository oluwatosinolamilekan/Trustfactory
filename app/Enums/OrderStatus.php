<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case REJECTED = 'rejected';
    case COMPLETED = 'completed';

    /**
     * Get all status values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if the order is pending.
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if the order is completed.
     */
    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Check if the order is rejected.
     */
    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    /**
     * Get the display name for the status.
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::REJECTED => 'Rejected',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * Get the color class for the status (useful for UI).
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::REJECTED => 'red',
            self::COMPLETED => 'green',
        };
    }
}

