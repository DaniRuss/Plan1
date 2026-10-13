<?php

namespace Tests\Unit;

use App\Models\StrategicObjective;
use PHPUnit\Framework\TestCase;

class WeeklyAchievementTest extends TestCase
{
    public function test_weekly_achievement_is_not_capped_at_one_hundred_percent(): void
    {
        $objective = new StrategicObjective();

        $this->assertSame(150.0, $objective->calculateWeeklyAchievement(15, 10));
        $this->assertSame(100.0, $objective->calculateWeeklyAchievement(10, 10));
        $this->assertSame(80.0, $objective->calculateWeeklyAchievement(8, 10));
    }

    public function test_weekly_achievement_respects_lower_is_better_direction(): void
    {
        $objective = new StrategicObjective(['goal_direction' => 'decrease']);

        $this->assertSame(125.0, $objective->calculateWeeklyAchievement(8, 10));
        $this->assertSame(50.0, $objective->calculateWeeklyAchievement(20, 10));
    }
}
