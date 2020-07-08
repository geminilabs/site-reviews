<?php

namespace GeminiLabs\SiteReviews\Shortcodes;

use GeminiLabs\SiteReviews\Contracts\ShortcodeContract;
use GeminiLabs\SiteReviews\Helper;
use GeminiLabs\SiteReviews\Helpers\Arr;
use GeminiLabs\SiteReviews\Helpers\Cast;
use GeminiLabs\SiteReviews\Helpers\Str;
use GeminiLabs\SiteReviews\Modules\Html\Builder;
use GeminiLabs\SiteReviews\Modules\Html\Partial;
use GeminiLabs\SiteReviews\Modules\Rating;
use GeminiLabs\SiteReviews\Modules\Style;
use ReflectionClass;

abstract class Shortcode implements ShortcodeContract
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var string
     */
    protected $partialName;

    /**
     * @var string
     */
    protected $shortcodeName;

    public function __construct()
    {
        $this->partialName = $this->getShortcodePartialName();
        $this->shortcodeName = $this->getShortcodeName();
    }

    /**
     * @param string|array $atts
     * @param string $type
     * @return string
     */
    public function build($atts, array $args = [], $type = 'shortcode')
    {
        $args = $this->normalizeArgs($args, $type);
        $atts = $this->normalizeAtts($atts, $type);
        $partial = glsr(Partial::class)->build($this->partialName, $atts);
        if (!empty($atts['title'])) {
            $atts = Arr::set($atts, 'title', $args['before_title'].$atts['title'].$args['after_title']);
        }
        $html = glsr(Builder::class)->div((string) $partial, wp_parse_args($this->data, [
            'class' => 'glsr glsr-'.glsr(Style::class)->get(),
            'data-'.$type => '',
        ]));
        return $args['before_widget'].$atts['title'].$html.$args['after_widget'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildBlock($atts = [])
    {
        return $this->build($atts, [], 'block');
    }

    /**
     * {@inheritdoc}
     */
    public function buildShortcode($atts = [])
    {
        return $this->build($atts, [], 'shortcode');
    }

    /**
     * @return array
     */
    public function getDefaults(array $atts)
    {
        return glsr($this->getShortcodeDefaultsClassName())->unguardedRestrict($atts);
    }

    /**
     * @return array
     */
    public function getHideOptions()
    {
        $options = $this->hideOptions();
        return glsr()->filterArray('shortcode/hide-options', $options, $this->shortcodeName);
    }

    /**
     * @return string
     */
    public function getShortClassName($replace = '', $search = 'Shortcode')
    {
        return str_replace($search, $replace, (new ReflectionClass($this))->getShortName());
    }

    /**
     * @return string
     */
    public function getShortcodeDefaultsClassName()
    {
        $className = Str::replaceLast('Shortcode', 'Defaults', get_class($this));
        return str_replace('Shortcodes', 'Defaults', $className);
    }

    /**
     * @return string
     */
    public function getShortcodeName()
    {
        return Str::snakeCase($this->getShortClassName());
    }

    /**
     * @return string
     */
    public function getShortcodePartialName()
    {
        return Str::dashCase($this->getShortClassName());
    }

    /**
     * @param array|string $args
     * @param string $type
     * @return array
     */
    public function normalizeArgs($args, $type = 'shortcode')
    {
        $args = wp_parse_args($args, [
            'before_widget' => '',
            'after_widget' => '',
            'before_title' => '<h2 class="glsr-title">',
            'after_title' => '</h2>',
        ]);
        return glsr()->filterArray('shortcode/args', $args, $type, $this->partialName);
    }

    /**
     * @param array|string $atts
     * @param string $type
     * @return array
     */
    public function normalizeAtts($atts, $type = 'shortcode')
    {
        $atts = glsr()->filterArray('shortcode/atts', $atts, $type, $this->partialName);
        $atts = $this->getDefaults(wp_parse_args($atts));
        foreach ($atts as $key => &$value) {
            $methodName = Helper::buildMethodName($key, 'normalize');
            if (method_exists($this, $methodName)) {
                $value = $this->$methodName($value, $atts);
            }
        }
        $this->setData($atts);
        $this->setId($atts);
        return $atts;
    }

    /**
     * @return array
     */
    abstract protected function hideOptions();

    /**
     * @param string $postId
     * @return int|string
     */
    protected function normalizeAssignedTo($postId, array $atts)
    {
        if ('parent_id' == $postId) {
            $postId = intval(wp_get_post_parent_id(intval(get_the_ID())));
        } elseif ('post_id' == $postId) {
            $postId = intval(get_the_ID());
        } elseif ('custom' == $postId) {
            $customId = Arr::get($atts, 'assigned_to_custom');
            $customId = str_replace('custom', '', $customId); // prevent a possible infinite loop
            $postId = $this->normalizeAssignedTo($customId, $atts);
        }
        return $postId;
    }

    /**
     * @param string $postId
     * @return int|string
     */
    protected function normalizeAssignTo($postId, array $atts)
    {
        return $this->normalizeAssignedTo($postId, $atts);
    }

    /**
     * @param string|array $hide
     * @return array
     */
    protected function normalizeHide($hide)
    {
        if (is_string($hide)) {
            $hide = explode(',', $hide);
        }
        $hideKeys = array_keys($this->getHideOptions());
        return array_filter(array_map('trim', $hide), function ($value) use ($hideKeys) {
            return in_array($value, $hideKeys);
        });
    }

    /**
     * @param string $id
     * @return string
     */
    protected function normalizeId($id)
    {
        return sanitize_title($id);
    }

    /**
     * @param string $labels
     * @return array
     */
    protected function normalizeLabels($labels)
    {
        $defaults = [
            __('Excellent', 'site-reviews'),
            __('Very good', 'site-reviews'),
            __('Average', 'site-reviews'),
            __('Poor', 'site-reviews'),
            __('Terrible', 'site-reviews'),
        ];
        $maxRating = (int) glsr()->constant('MAX_RATING', Rating::class);
        $defaults = array_pad(array_slice($defaults, 0, $maxRating), $maxRating, '');
        $labels = array_map('trim', explode(',', $labels));
        foreach ($defaults as $i => $label) {
            if (empty($labels[$i])) {
                continue;
            }
            $defaults[$i] = $labels[$i];
        }
        return array_combine(range($maxRating, 1), $defaults);
    }

    /**
     * @param string $schema
     * @return bool
     */
    protected function normalizeSchema($schema)
    {
        return Cast::toBool($schema);
    }

    /**
     * @param string $text
     * @return string
     */
    protected function normalizeText($text)
    {
        return trim($text);
    }

    /**
     * @return void
     */
    protected function setData(array $atts)
    {
        unset($atts['assign_to_custom']);
        unset($atts['assigned_to_custom']);
        $this->data = glsr($this->getShortcodeDefaultsClassName())->dataAttributes($atts);
    }

    /**
     * @return void
     */
    protected function setId(array &$atts)
    {
        if (empty($atts['id'])) {
            $atts['id'] = glsr()->prefix.substr(md5(serialize($atts)), 0, 8);
        }
    }
}
