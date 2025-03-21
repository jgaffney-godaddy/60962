<?php
/**
 * Copyright © 2019-2025 Rhubarb Tech Inc. All Rights Reserved.
 *
 * The Object Cache Pro Software and its related materials are property and confidential
 * information of Rhubarb Tech Inc. Any reproduction, use, distribution, or exploitation
 * of the Object Cache Pro Software and its related materials, in whole or in part,
 * is strictly forbidden unless prior permission is obtained from Rhubarb Tech Inc.
 *
 * In addition, any reproduction, use, distribution, or exploitation of the Object Cache Pro
 * Software and its related materials, in whole or in part, is subject to the End-User License
 * Agreement accessible in the included `LICENSE` file, or at: https://objectcache.pro/eula
 */

declare(strict_types=1);

namespace RedisCachePro\Metrics;

use RedisCachePro\Connections\RelayConnection;

class RelayMetrics
{
    /**
     * Number of successful key lookups.
     *
     * @var int
     */
    public $hits;

    /**
     * Number of failed key lookups.
     *
     * @var int
     */
    public $misses;

    /**
     * The hits-to-misses ratio.
     *
     * @var float
     */
    public $hitRatio;

    /**
     * Number of commands processed per second.
     *
     * @var int
     */
    public $opsPerSec;

    /**
     * The number of keys in Relay for the current database.
     *
     * @var int|null
     */
    public $keys;

    /**
     * The amount of memory pointing to live objects or metadata.
     *
     * @var float
     */
    public $memoryUsed;

    /**
     * The total number of bytes allocated by Relay.
     *
     * @var int
     */
    public $memoryTotal;

    /**
     * The ratio of total memory allocated by Relay compared to
     * the amount of memory pointing to live objects or metadata.
     *
     * @var float
     */
    public $memoryRatio;

    /**
     * Creates a new instance from given connection.
     *
     * @param  \RedisCachePro\Connections\RelayConnection  $connection
     * @return void
     */
    public function __construct(RelayConnection $connection)
    {
        $stats = $connection->memoize('stats');
        $keys = $connection->keysInMemory();
        $total = intval($stats['stats']['hits'] + $stats['stats']['misses']);

        $this->hits = $stats['stats']['hits'];
        $this->misses = $stats['stats']['misses'];
        $this->hitRatio = $total > 0 ? round($this->hits / ($total / 100), 2) : 100;
        $this->opsPerSec = $stats['stats']['ops_per_sec'];
        $this->keys = is_null($keys) ? null : (int) $keys;
        $this->memoryTotal = $stats['memory']['total'];
        $this->memoryUsed = $stats['memory']['used'];
        $this->memoryRatio = round(($this->memoryUsed / $this->memoryTotal) * 100, 2);
    }

    /**
     * Returns the Relay metrics as array.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit-ratio' => $this->hitRatio,
            'ops-per-sec' => $this->opsPerSec,
            'keys' => $this->keys,
            'memory-used' => $this->memoryUsed,
            'memory-total' => $this->memoryTotal,
            'memory-ratio' => $this->memoryRatio,
        ];
    }

    /**
     * Returns the Relay metrics in string format.
     *
     * @return string
     */
    public function __toString()
    {
        $metrics = $this->toArray();

        return implode(' ', array_map(static function ($metric, $value) {
            return "sample#relay-{$metric}={$value}";
        }, array_keys($metrics), $metrics));
    }

    /**
     * Returns the schema for the Relay metrics.
     *
     * @return array<string, array<string, string>>
     */
    public static function schema()
    {
        $metrics = [
            'relay-hits' => [
                'title' => 'Hits',
                'description' => 'Number of successful key lookups.',
                'type' => 'integer',
            ],
            'relay-misses' => [
                'title' => 'Misses',
                'description' => 'Number of failed key lookups.',
                'type' => 'integer',
            ],
            'relay-hit-ratio' => [
                'title' => 'Hit ratio',
                'description' => 'The hits-to-misses ratio.',
                'type' => 'ratio',
            ],
            'relay-ops-per-sec' => [
                'title' => 'Throughput',
                'description' => 'Number of commands processed per second.',
                'type' => 'throughput',
            ],
            'relay-keys' => [
                'title' => 'Keys',
                'description' => 'The number of keys in Relay for the current database.',
                'type' => 'integer',
            ],
            'relay-memory-used' => [
                'title' => 'Used memory',
                'description' => 'The amount of bytes pointing to live objects including metadata.',
                'type' => 'bytes',
            ],
            'relay-memory-total' => [
                'title' => 'Total memory',
                'description' => 'The total number of bytes of allocated memory by Relay.',
                'type' => 'bytes',
            ],
            'relay-memory-ratio' => [
                'title' => 'Memory ratio',
                'description' => 'The ratio of bytes of allocated memory by Relay compared to the total amount of memory mapped into the allocator.',
                'type' => 'ratio',
            ],
        ];

        return array_map(static function ($metric) {
            $metric['group'] = 'relay';

            return $metric;
        }, $metrics);
    }
}
