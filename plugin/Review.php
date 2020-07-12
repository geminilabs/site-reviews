<?php

namespace GeminiLabs\SiteReviews;

use GeminiLabs\SiteReviews\Database\OptionManager;
use GeminiLabs\SiteReviews\Database\Query;
use GeminiLabs\SiteReviews\Defaults\CreateReviewDefaults;
use GeminiLabs\SiteReviews\Defaults\SiteReviewsDefaults;
use GeminiLabs\SiteReviews\Helpers\Arr;
use GeminiLabs\SiteReviews\Helpers\Cast;
use GeminiLabs\SiteReviews\Modules\Avatar;
use GeminiLabs\SiteReviews\Modules\Html\Builder;
use GeminiLabs\SiteReviews\Modules\Html\Partials\SiteReviews as SiteReviewsPartial;
use GeminiLabs\SiteReviews\Modules\Html\ReviewHtml;

/**
 * @property array $assigned_posts
 * @property array $assigned_terms
 * @property array $assigned_users
 * @property string $author
 * @property int $author_id
 * @property string $avatar;
 * @property string $content
 * @property Arguments $custom
 * @property string $date
 * @property string $email
 * @property int $ID
 * @property string $ip_address
 * @property bool $is_approved
 * @property bool $is_modified
 * @property bool $is_pinned
 * @property int $rating
 * @property int $rating_id
 * @property string $response
 * @property string $status
 * @property string $title
 * @property string $type
 * @property string $url
 */
class Review extends Arguments
{
    /**
     * @var Arguments
     */
    protected $_meta;

    /**
     * @var \WP_Post
     */
    protected $_post;

    /**
     * @var object
     */
    protected $_review;

    /**
     * @var bool
     */
    protected $has_checked_revisions;

    /**
     * @var int
     */
    protected $id;

    /**
     * @param array|object $values
     */
    public function __construct($values)
    {
        $values = glsr()->args($values);
        $this->id = Cast::toInt($values->review_id);
        $args = [];
        $args['assigned_posts'] = Arr::uniqueInt($values->post_ids);
        $args['assigned_terms'] = Arr::uniqueInt($values->term_ids);
        $args['assigned_users'] = Arr::uniqueInt($values->user_ids);
        $args['author'] = $values->name;
        $args['author_id'] = Cast::toInt($values->author_id);
        $args['avatar'] = $values->avatar;
        $args['content'] = $values->content;
        $args['custom'] = new Arguments($this->meta()->custom);
        $args['date'] = $values->date;
        $args['email'] = $values->email;
        $args['ID'] = $this->id;
        $args['ip_address'] = $values->ip_address;
        $args['is_approved'] = Cast::toBool($values->is_approved);
        $args['is_modified'] = false;
        $args['is_pinned'] = Cast::toBool($values->is_pinned);
        $args['rating'] = Cast::toInt($values->rating);
        $args['rating_id'] = Cast::toInt($values->ID);
        $args['response'] = $this->meta()->response;
        $args['status'] = $values->status;
        $args['title'] = $values->title;
        $args['type'] = $values->type;
        $args['url'] = $values->url;
        parent::__construct($args);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->build();
    }

    /**
     * @param int $size
     * @return string
     */
    public function avatar($size = null)
    {
        return glsr(Avatar::class)->img($this->get('avatar'), $size);
    }

    /**
     * @return ReviewHtml
     */
    public function build(array $args = [])
    {
        return new ReviewHtml($this, $args);
    }

    /**
     * @return Arguments
     */
    public function custom()
    {
        // @todo
    }

    /**
     * @return string
     */
    public function date($format = 'F j, Y')
    {
        return get_date_from_gmt($this->get('date'), $format);
    }

    /**
     * @param int|\WP_Post $post
     * @return bool
     */
    public static function isEditable($post)
    {
        $post = get_post($post);
        return static::isReview($post)
            && post_type_supports(glsr()->post_type, 'title')
            && 'local' === glsr(Query::class)->review($post->ID)->type;
    }

    /**
     * @param \WP_Post|int|false $post
     * @return bool
     */
    public static function isReview($post)
    {
        return glsr()->post_type === get_post_type($post);
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return !empty($this->id) && !empty($this->get('rating_id'));
    }

    /**
     * @return Arguments
     */
    public function meta()
    {
        if (!$this->_meta instanceof Arguments) {
            $meta = Arr::consolidate(get_post_meta($this->id));
            $meta = array_map('array_shift', array_filter($meta));
            $meta = Arr::unprefixKeys(array_filter($meta, 'strlen'));
            $meta = array_map('maybe_unserialize', $meta);
            $meta = glsr(CreateReviewDefaults::class)->restrict($meta);
            $this->_meta = new Arguments($meta);
        }
        return $this->_meta;
    }

    /**
     * @param mixed $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return parent::offsetExists($key) || !is_null($this->custom->$key);
    }

    /**
     * @param mixed $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        $alternateKeys = [
            'approved' => 'is_approved',
            'has_revisions' => 'is_modified',
            'modified' => 'is_modified',
            'name' => 'author',
            'pinned' => 'is_pinned',
            'user_id' => 'author_id',
        ];
        if (array_key_exists($key, $alternateKeys)) {
            return $this->offsetGet($alternateKeys[$key]);
        }
        if ('is_modified' === $key) {
            return $this->hasRevisions();
        }
        if (is_null($value = parent::offsetGet($key))) {
            return $this->custom->$key;
        }
        return $value;
    }

    /**
     * @param mixed $key
     * @return void
     */
    public function offsetSet($key, $value)
    {
        // This class is read-only
    }

    /**
     * @param mixed $key
     * @return void
     */
    public function offsetUnset($key)
    {
        // This class is read-only
    }

    /**
     * @return \WP_Post|null
     */
    public function post()
    {
        if (!$this->_post instanceof \WP_Post) {
            $this->_post = get_post($this->id);
        }
        return $this->_post;
    }

    /**
     * @return void
     */
    public function render()
    {
        echo $this->build();
    }

    /**
     * @return string
     */
    public function rating()
    {
        return glsr_star_rating($this->get('rating'));
    }

    /**
     * @return string
     */
    public function type()
    {
        $type = $this->get('type');
        return array_key_exists($type, glsr()->reviewTypes)
            ? glsr()->reviewTypes[$type]
            : _x('Unknown', 'admin-text', 'site-reviews');
    }

    /**
     * @return bool
     */
    protected function hasRevisions()
    {
        if (!$this->has_checked_revisions) {
            $modified = glsr(Query::class)->hasRevisions($this->ID);
            $this->set('is_modified', $modified);
            $this->has_checked_revisions = true;
        }
        return $this->get('is_modified');
    }
}
