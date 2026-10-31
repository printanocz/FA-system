<?php

namespace App\Domain\Backup;

use App\Models\Backup;
use Illuminate\Support\Carbon;

/** Stav záloh pro banner, nastavení a /_health. */
final class BackupStatus
{
    public static function lastSuccess(): ?Backup
    {
        return Backup::query()->where('status', Backup::STATUS_OK)->latest('created_at')->latest('id')->first();
    }

    /** Stáří poslední úspěšné zálohy v hodinách (zaokrouhleno dolů), `null` když žádná není. */
    public static function ageHours(?Carbon $now = null): ?int
    {
        $hours = self::exactAgeHours($now);

        return $hours === null ? null : (int) floor($hours);
    }

    private static function exactAgeHours(?Carbon $now): ?float
    {
        $last = self::lastSuccess();

        return $last === null ? null : (float) $last->created_at->diffInHours($now ?? Carbon::now(), true);
    }

    /**
     * Varování do banneru: poslední úspěšná záloha je starší než nastavený limit (48 h) nebo žádná není.
     *
     * @return array{message: string, age_hours: int|null}|null
     */
    public static function warning(?Carbon $now = null): ?array
    {
        $exact = self::exactAgeHours($now);

        if ($exact !== null && $exact <= (int) config('backup.stale_after_hours')) {
            return null;
        }

        $age = $exact === null ? null : (int) floor($exact);

        return [
            'age_hours' => $age,
            'message' => $age === null
                ? 'Záloha dat zatím neproběhla.'
                : "Poslední úspěšná záloha dat je stará {$age} hodin.",
        ];
    }
}
