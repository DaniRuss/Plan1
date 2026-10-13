<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;

class CalendarHelper
{
    /**
     * Get the standardized range of years for the application.
     * Starts from 2017 (system start) to Current Year + 5.
     */
    public static function getAppYears(): array
    {
        $startYear = 2017;
        $endYear = (int) date('Y') + 5;

        return range($startYear, $endYear);
    }

    /**
     * Map a fiscal month name to the Gregorian month number.
     * Fiscal year: July=1st month, June=12th month.
     */
    public static function monthNameToNumber(string $month): int
    {
        $map = [
            'july' => 7, 'august' => 8, 'september' => 9,
            'october' => 10, 'november' => 11, 'december' => 12,
            'january' => 1, 'february' => 2, 'march' => 3,
            'april' => 4, 'may' => 5, 'june' => 6,
        ];
        return $map[strtolower($month)] ?? 1;
    }

    /**
     * Get the real calendar year for a fiscal month given a fiscal year.
     * Months Jan–Jun belong to (fiscalYear + 1), Jul–Dec belong to fiscalYear.
     * Note: The system uses a July–June fiscal year.
     */
    public static function getGregorianYear(string $month, int $fiscalYear): int
    {
        $lateMonths = ['january', 'february', 'march', 'april', 'may', 'june'];
        return in_array(strtolower($month), $lateMonths) ? $fiscalYear + 1 : $fiscalYear;
    }

    /**
     * Calculate fixed Saturday-Friday reporting weeks. A cross-month week is
     * assigned to the month containing its Friday (the period end date).
     *
     * @return array [1 => ['start' => 1, 'end' => 5, 'label' => 'Jul 01 - Jul 05'], ...]
     */
    public static function getMonthWeeks(string $month, int $fiscalYear): array
    {
        $monthNum = self::monthNameToNumber($month);
        $calYear  = self::getGregorianYear($month, $fiscalYear);
        
        $startOfMonth = Carbon::createFromDate($calYear, $monthNum, 1, config('app.timezone'))->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $firstFriday = $startOfMonth->copy();
        if (!$firstFriday->isFriday()) {
            $firstFriday->next(Carbon::FRIDAY);
        }

        $weeks = [];
        $weekEnd = $firstFriday;
        $weekNum = 1;

        while ($weekEnd->lte($endOfMonth)) {
            $weekStart = $weekEnd->copy()->subDays(6)->startOfDay();
            $dueAt = $weekEnd->copy()
                ->addDays((int) Setting::get('weekly_submission_due_days', 3))
                ->setTimeFromTimeString((string) Setting::get('weekly_submission_due_time', '17:00'));

            $weeks[$weekNum] = [
                'start' => $weekStart->day,
                'end' => $weekEnd->day,
                'start_full' => $weekStart->toDateString(),
                'end_full' => $weekEnd->toDateString(),
                'due_at' => $dueAt->toDateTimeString(),
                'label' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
                'days' => self::getWeekDays($weekStart, $weekEnd)
            ];
            $weekEnd = $weekEnd->copy()->addWeek();
            $weekNum++;
        }

        return $weeks;
    }

    /**
     * Helper to get day details for a week.
     */
    private static function getWeekDays(\Carbon\Carbon $start, \Carbon\Carbon $end): array
    {
        $days = [];
        for ($i = 1; $i <= 7; $i++) {
            $dayDate = $start->copy()->addDays($i - 1);

            $days[$i] = [
                'date' => $dayDate->toDateString(),
                'day_name' => $dayDate->format('D'),
                'day_num' => $dayDate->day,
                'is_in_month' => true,
            ];
        }
        return $days;
    }
}
