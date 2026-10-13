<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ObjectivePerformerMatcher
{
    private const ALIASES = [
        'digital' => ['Digital Office'],
        'engineering' => ['Engineering and Construction'],
        'machinarytruckadministration' => ['Machinery & Truck Administration'],
        'operationmaintenance' => ['Operation', 'Maintenance'],
        'operationfinance' => ['Operation', 'Finance'],
        'research' => ['Research and Innovation'],
    ];

    /**
     * Match department names in workbook order. A complete name wins before
     * splitting conjunctions (for example, "Engineering and Construction").
     */
    public function match(string $performerText, Collection $departments): array
    {
        $matched = [];
        $unmatched = [];

        foreach (preg_split('/\s*[,;]\s*/u', $performerText) ?: [] as $part) {
            $part = preg_replace('/^and\s+/iu', '', trim($part));
            if ($part === '') continue;
            $this->matchPart($part, $departments, $matched, $unmatched);
        }

        return ['ids' => array_values($matched), 'unmatched' => array_values(array_unique($unmatched))];
    }

    private function matchPart(string $part, Collection $departments, array &$matched, array &$unmatched): void
    {
        $key = $this->key($part);
        if ($key === 'alldepartments') {
            foreach ($departments as $candidate) {
                if ($candidate->is_active ?? true) $matched[(int) $candidate->id] = (int) $candidate->id;
            }
            return;
        }

        if (isset(self::ALIASES[$key])) {
            foreach (self::ALIASES[$key] as $name) $this->matchPart($name, $departments, $matched, $unmatched);
            return;
        }

        $department = $departments->first(fn ($candidate) =>
            $this->key($candidate->name) === $key
            || ($candidate->code && $this->key($candidate->code) === $key)
        );
        if ($department) {
            $matched[(int) $department->id] = (int) $department->id;
            return;
        }

        $parts = preg_split('/\s+(?:and|&)\s+/iu', $part);
        if (count($parts) > 1) {
            foreach ($parts as $piece) $this->matchPart(trim($piece), $departments, $matched, $unmatched);
            return;
        }

        $unmatched[] = $part;
    }

    private function key(string $name): string
    {
        return mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', $name));
    }
}
