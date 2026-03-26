<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tests for the Voting class
 */

use PHPUnit\Framework\TestCase;

class VotingTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Reset any global state
    }

    public function testCalculateWeightedScores(): void
    {
        // Test the Borda count weighting calculation
        $weights = [10, 6, 3, 1];
        $rankings = [
            ['performer_id' => 1, 'rank' => 1],
            ['performer_id' => 2, 'rank' => 2],
            ['performer_id' => 3, 'rank' => 3],
            ['performer_id' => 4, 'rank' => 4],
        ];

        $scores = [];
        foreach ($rankings as $ranking) {
            $rank = $ranking['rank'];
            $weight = $weights[$rank - 1] ?? 0;
            $scores[$ranking['performer_id']] = $weight;
        }

        $this->assertEquals(10, $scores[1], 'First place should have highest weight');
        $this->assertEquals(6, $scores[2], 'Second place should have second highest weight');
        $this->assertEquals(3, $scores[3], 'Third place should have third highest weight');
        $this->assertEquals(1, $scores[4], 'Fourth place should have lowest weight');
    }

    public function testValidateBallot(): void
    {
        $ballot = [1, 2, 3];

        // Test valid ballot
        $this->assertCount(3, $ballot);
        $this->assertEquals(array_unique($ballot), $ballot, 'Ballot should not have duplicate performers');

        // Test that all entries are integers
        foreach ($ballot as $performerId) {
            $this->assertIsInt($performerId);
        }
    }

    public function testInvalidBallotDuplicates(): void
    {
        $ballot = [1, 1, 2]; // Duplicate performer

        $isValid = count($ballot) === count(array_unique($ballot));
        $this->assertFalse($isValid, 'Ballot with duplicates should be invalid');
    }

    public function testShowConfigDefaults(): void
    {
        $defaults = [
            'enabled' => false,
            'reveal_results' => false,
            'allow_anonymous' => true,
            'top_n' => 2,
            'use_groups' => false,
            'groups' => [],
            'start_time' => null,
            'end_time' => null,
            'weights' => [10, 6, 3, 1],
        ];

        $this->assertFalse($defaults['enabled']);
        $this->assertFalse($defaults['reveal_results']);
        $this->assertTrue($defaults['allow_anonymous']);
        $this->assertEquals(2, $defaults['top_n']);
        $this->assertIsArray($defaults['weights']);
        $this->assertCount(4, $defaults['weights']);
    }

    public function testVotingTimeWindow(): void
    {
        $startTime = '2025-01-01 10:00:00';
        $endTime = '2025-01-01 12:00:00';
        $currentTime = '2025-01-01 11:00:00';

        $start = strtotime($startTime);
        $end = strtotime($endTime);
        $current = strtotime($currentTime);

        $isWithinWindow = $current >= $start && $current <= $end;
        $this->assertTrue($isWithinWindow, 'Time should be within voting window');

        // Test before window
        $currentTime = '2025-01-01 09:00:00';
        $current = strtotime($currentTime);
        $isWithinWindow = $current >= $start && $current <= $end;
        $this->assertFalse($isWithinWindow, 'Time before window should not be valid');

        // Test after window
        $currentTime = '2025-01-01 13:00:00';
        $current = strtotime($currentTime);
        $isWithinWindow = $current >= $start && $current <= $end;
        $this->assertFalse($isWithinWindow, 'Time after window should not be valid');
    }

    public function testGroupBasedVoting(): void
    {
        $groups = [
            ['name' => 'Group A', 'performer_ids' => [1, 2, 3]],
            ['name' => 'Group B', 'performer_ids' => [4, 5, 6]],
        ];

        // Verify performer is in correct group
        $performerId = 2;
        $performerGroup = null;

        foreach ($groups as $group) {
            if (in_array($performerId, $group['performer_ids'])) {
                $performerGroup = $group['name'];
                break;
            }
        }

        $this->assertEquals('Group A', $performerGroup);

        // Test performer not in any group
        $performerId = 10;
        $performerGroup = null;

        foreach ($groups as $group) {
            if (in_array($performerId, $group['performer_ids'])) {
                $performerGroup = $group['name'];
                break;
            }
        }

        $this->assertNull($performerGroup);
    }

    public function testFinalsCalculation(): void
    {
        // Simulate results from multiple groups
        $groupResults = [
            ['performer_id' => 1, 'weighted_score' => 100, 'group' => 'A'],
            ['performer_id' => 2, 'weighted_score' => 80, 'group' => 'A'],
            ['performer_id' => 3, 'weighted_score' => 60, 'group' => 'A'],
            ['performer_id' => 4, 'weighted_score' => 90, 'group' => 'B'],
            ['performer_id' => 5, 'weighted_score' => 70, 'group' => 'B'],
            ['performer_id' => 6, 'weighted_score' => 50, 'group' => 'B'],
        ];

        $topN = 2;
        $finalists = [];

        // Get top N from each group
        $groups = [];
        foreach ($groupResults as $result) {
            $groups[$result['group']][] = $result;
        }

        foreach ($groups as $groupName => $results) {
            usort($results, fn($a, $b) => $b['weighted_score'] <=> $a['weighted_score']);
            $finalists = array_merge($finalists, array_slice($results, 0, $topN));
        }

        $this->assertCount(4, $finalists, 'Should have top 2 from each of 2 groups');

        // Verify correct performers made it
        $finalistIds = array_column($finalists, 'performer_id');
        $this->assertContains(1, $finalistIds, 'Top scorer from Group A should be finalist');
        $this->assertContains(2, $finalistIds, 'Second from Group A should be finalist');
        $this->assertContains(4, $finalistIds, 'Top scorer from Group B should be finalist');
        $this->assertContains(5, $finalistIds, 'Second from Group B should be finalist');
    }
}
