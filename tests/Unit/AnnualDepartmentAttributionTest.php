<?php

namespace Tests\Unit;

use App\Services\ObjectivePerformerMatcher;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class AnnualDepartmentAttributionTest extends TestCase
{
    public function test_missing_alias_target_returns_unmatched_instead_of_recursing_forever(): void
    {
        $matcher = new ObjectivePerformerMatcher;
        $departments = new Collection([(object) ['id' => 1, 'name' => 'Operation', 'code' => 'OP', 'is_active' => true]]);
        $result = $matcher->match('Engineering', $departments);
        $this->assertSame([], $result['ids']);
        $this->assertSame(['Engineering', 'Construction'], $result['unmatched']);
    }

    public function test_complete_department_name_still_wins_over_splitting_its_conjunction(): void
    {
        $matcher = new ObjectivePerformerMatcher;
        $departments = new Collection([(object) ['id' => 9, 'name' => 'Engineering and Construction', 'code' => 'ENG', 'is_active' => true]]);
        $this->assertSame(['ids' => [9], 'unmatched' => []], $matcher->match('Engineering', $departments));
    }
}
