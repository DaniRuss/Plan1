<?php

namespace Tests\Unit;

use App\Services\ObjectivePerformerMatcher;
use App\Services\ObjectiveWorkbookImport;
use PHPUnit\Framework\TestCase;

class ObjectivePerformerMatcherTest extends TestCase
{
    public function test_it_preserves_workbook_order_and_all_matched_performers(): void
    {
        $departments = collect([
            (object) ['id' => 1, 'name' => 'OCC', 'code' => null],
            (object) ['id' => 2, 'name' => 'Operation', 'code' => null],
            (object) ['id' => 3, 'name' => 'Rolling Stock', 'code' => null],
        ]);

        $result = (new ObjectivePerformerMatcher())->match('Operation, OCC, Rolling Stock', $departments);
        $this->assertSame([2, 1, 3], $result['ids']);
        $this->assertSame([], $result['unmatched']);
    }

    public function test_department_names_containing_and_are_not_split(): void
    {
        $departments = collect([
            (object) ['id' => 4, 'name' => 'Engineering and Construction', 'code' => null],
            (object) ['id' => 5, 'name' => 'Operation', 'code' => null],
        ]);

        $result = (new ObjectivePerformerMatcher())->match('Engineering and Construction, Operation', $departments);
        $this->assertSame([4, 5], $result['ids']);
        $this->assertSame([], $result['unmatched']);
    }

    public function test_all_departments_expands_to_every_existing_department(): void
    {
        $departments = collect([(object) ['id' => 6, 'name' => 'Planning', 'code' => null]]);
        $result = (new ObjectivePerformerMatcher())->match('Planning, All Departments', $departments);
        $this->assertSame([6], $result['ids']);
        $this->assertSame([], $result['unmatched']);
    }

    public function test_it_accepts_minor_punctuation_differences_in_department_names(): void
    {
        $departments = collect([
            (object) ['id' => 7, 'name' => 'H.R', 'code' => null],
            (object) ['id' => 8, 'name' => 'Ethics', 'code' => null],
        ]);
        $result = (new ObjectivePerformerMatcher())->match('HR, and Ethics', $departments);
        $this->assertSame([7, 8], $result['ids']);
        $this->assertSame([], $result['unmatched']);
    }

    public function test_a_department_name_containing_ampersand_remains_one_match(): void
    {
        $departments = collect([(object) ['id' => 9, 'name' => 'Machinery & Truck Administration', 'code' => null]]);
        $result = (new ObjectivePerformerMatcher())->match('Machinery & Truck Administration', $departments);
        $this->assertSame([9], $result['ids']);
        $this->assertSame([], $result['unmatched']);
    }

    public function test_every_performer_name_in_the_reference_workbook_maps_to_current_departments(): void
    {
        $path = 'E:/EDR/Plan and report project/2019 EFY internal Action plan Distributed to Departments (1).xlsx';
        if (!is_file($path)) $this->markTestSkipped('Reference workbook is not available.');
        $names = ['Audit','Capacity Building','Commercial','Communication','Digital Office','Engineering and Construction','Ethics','Executive Management','Federal and Regional Security Forces','Fertilizer Operation','Finance','H.R','HRDA','ICT','Legal','Machinery & Truck Administration','Maintenance','Marketing','OCC','Operation','Passenger','Plan and Evaluation','Planning','Procurement','Research and Innovation','Rolling Stock','Safety and Security','Training Center'];
        $departments = collect(array_map(fn ($index, $name) => (object) ['id' => $index + 1, 'name' => $name, 'code' => null, 'is_active' => true], array_keys($names), $names));
        $unmatched = [];
        foreach ((new ObjectiveWorkbookImport())->parse($path) as $row) {
            $result = (new ObjectivePerformerMatcher())->match($row['performer_text'], $departments);
            $unmatched = array_merge($unmatched, $result['unmatched']);
        }
        $this->assertSame([], array_values(array_unique($unmatched)));
    }
}
