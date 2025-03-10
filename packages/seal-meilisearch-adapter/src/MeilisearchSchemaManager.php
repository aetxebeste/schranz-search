<?php

declare(strict_types=1);

/*
 * This file is part of the Schranz Search package.
 *
 * (c) Alexander Schranz <alexander@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Schranz\Search\SEAL\Adapter\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Schranz\Search\SEAL\Adapter\SchemaManagerInterface;
use Schranz\Search\SEAL\Schema\Index;
use Schranz\Search\SEAL\Task\AsyncTask;
use Schranz\Search\SEAL\Task\TaskInterface;

final class MeilisearchSchemaManager implements SchemaManagerInterface
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function existIndex(Index $index): bool
    {
        try {
            $this->client->getRawIndex($index->name);
        } catch (ApiException $e) {
            if (404 !== $e->httpStatus) {
                throw $e;
            }

            return false;
        }

        return true;
    }

    public function dropIndex(Index $index, array $options = []): ?TaskInterface
    {
        $deleteIndexResponse = $this->client->deleteIndex($index->name);

        if (!($options['return_slow_promise_result'] ?? false)) {
            return null;
        }

        return new AsyncTask(function () use ($deleteIndexResponse) {
            $this->client->waitForTask($deleteIndexResponse['taskUid']);
        });
    }

    public function createIndex(Index $index, array $options = []): ?TaskInterface
    {
        $this->client->createIndex(
            $index->name,
            [
                'primaryKey' => $index->getIdentifierField()->name,
            ],
        );

        $attributes = [
            'searchableAttributes' => $index->searchableFields,
            'filterableAttributes' => $index->filterableFields,
            'sortableAttributes' => $index->sortableFields,
        ];

        $updateIndexResponse = $this->client->index($index->name)
            ->updateSettings($attributes);

        if (!($options['return_slow_promise_result'] ?? false)) {
            return null;
        }

        return new AsyncTask(function () use ($updateIndexResponse) {
            $this->client->waitForTask($updateIndexResponse['taskUid']);
        });
    }
}
