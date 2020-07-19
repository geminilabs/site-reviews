<?php

namespace GeminiLabs\SiteReviews\Commands;

use GeminiLabs\SiteReviews\Contracts\CommandContract as Contract;
use GeminiLabs\SiteReviews\Database;
use GeminiLabs\SiteReviews\Database\Query;
use GeminiLabs\SiteReviews\Defaults\RatingDefaults;
use GeminiLabs\SiteReviews\Helpers\Arr;
use GeminiLabs\SiteReviews\Helpers\Cast;
use GeminiLabs\SiteReviews\Modules\Migrate;

class ImportRatings implements Contract
{
    protected $exportKey;
    protected $limit;

    public function __construct($exportKey)
    {
        $this->exportKey = $exportKey;
        $this->limit = 250;
    }

    /**
     * @return void
     */
    public function handle()
    {
        $this->import();
        $this->cleanup();
    }

    /**
     * @return void
     */
    protected function cleanup()
    {
        glsr(Database::class)->deleteMeta($this->exportKey);
        glsr(Migrate::class)->reset();
    }

    protected function import()
    {
        $offset = 0;
        while (true) {
            $values = glsr(Query::class)->import($offset, $this->limit, $this->exportKey);
            if (empty($values)) {
                break;
            }
            $this->importRatings($values);
            $this->importAssignedPosts($values);
            $this->importAssignedTerms($values);
            $this->importAssignedUsers($values);
            $offset += $this->limit;
        }
    }

    /**
     * @return array
     */
    protected function importAssignedPosts(array $values)
    {
        if ($values = $this->prepareAssignedValues($values, 'post')) {
            glsr(Database::class)->insertBulk('assigned_posts', $values, [
                'rating_id',
                'post_id',
                'is_published',
            ]);
        }
    }

    /**
     * @return array
     */
    protected function importAssignedTerms(array $values)
    {
        if ($values = $this->prepareAssignedValues($values, 'term')) {
            glsr(Database::class)->insertBulk('assigned_terms', $values, [
                'rating_id',
                'term_id',
            ]);
        }
    }

    /**
     * @return array
     */
    protected function importAssignedUsers(array $values)
    {
        if ($values = $this->prepareAssignedValues($values, 'user')) {
            glsr(Database::class)->insertBulk('assigned_users', $values, [
                'rating_id',
                'user_id',
            ]);
        }
    }

    /**
     * @return array
     */
    protected function importRatings(array $values)
    {
        array_walk($values, [$this, 'prepareRating']);
        $fields = array_keys(glsr(RatingDefaults::class)->defaults());
        glsr(Database::class)->insertBulk('ratings', $values, $fields);
    }

    /**
     * @param string $key
     * @return array
     */
    protected function prepareAssignedValues(array $results, $key)
    {
        $assignedKey = $key.'_id';
        $values = [];
        foreach ($results as $result) {
            $meta = maybe_unserialize($result['meta_value']);
            if (!$assignedIds = Arr::uniqueInt(Arr::get($meta, $key.'_ids'))) {
                continue;
            }
            foreach ($assignedIds as $assignedId) {
                $value = [
                    'review_id' => $result['post_id'],
                    $assignedKey => $assignedId,
                ];
                if ('post' === $key) {
                    $value['is_published'] = Cast::toBool(Arr::get($meta, 'is_approved'));
                }
                $values[] = $value;
            }
        }
        return $values;
    }

    /**
     * @return void
     */
    protected function prepareRating(array &$result)
    {
        $values = maybe_unserialize($result['meta_value']);
        $values['review_id'] = $result['post_id'];
        $result = glsr(RatingDefaults::class)->restrict($values);
    }
}
