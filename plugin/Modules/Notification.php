<?php

namespace GeminiLabs\SiteReviews\Modules;

use GeminiLabs\SiteReviews\Database\OptionManager;
use GeminiLabs\SiteReviews\Helpers\Str;
use GeminiLabs\SiteReviews\Review;
use WP_Post;

class Notification
{
    /**
     * @var bool
     */
    protected $email;

    /**
     * @var Review
     */
    protected $review;

    /**
     * @var bool
     */
    protected $slack;

    /**
     * @var array
     */
    protected $types;

    public function __construct()
    {
        $types = glsr(OptionManager::class)->get('settings.general.notifications', []);
        $this->email = count(array_intersect(['admin', 'custom'], $types)) > 0;
        $this->slack = in_array('slack', $types);
        $this->types = $types;
    }

    /**
     * @return void
     */
    public function send(Review $review)
    {
        if (empty($this->types)) {
            return;
        }
        $this->review = $review;
        $args = [
            'link' => $this->getLink(),
            'title' => $this->getTitle(),
        ];
        $this->sendToEmail($args);
        $this->sendToSlack($args);
    }

    /**
     * @return Email
     */
    protected function buildEmail(array $args)
    {
        $data = [
            'args' => $args,
            'review' => $this->review,
        ];
        return glsr(Email::class)->compose([
            'to' => $this->getEmailAddresses(),
            'subject' => $args['title'],
            'template' => 'email-notification',
            'template-tags' => [
                'review_author' => $this->review->author ?: __('Anonymous', 'site-reviews'),
                'review_content' => $this->review->content,
                'review_email' => $this->review->email,
                'review_ip' => $this->review->ip_address,
                'review_link' => sprintf('<a href="%1$s">%1$s</a>', $args['link']),
                'review_rating' => $this->review->rating,
                'review_title' => $this->review->title,
            ],
        ], $data);
    }

    /**
     * @return Slack
     */
    protected function buildSlackNotification(array $args)
    {
        return glsr(Slack::class)->compose($this->review, [
            'button_url' => $args['link'],
            'fallback' => $this->buildEmail($args)->read('plaintext'),
            'pretext' => $args['title'],
        ]);
    }

    /**
     * @return array
     */
    protected function getEmailAddresses()
    {
        $emails = [];
        if (in_array('admin', $this->types)) {
            $emails[] = glsr(OptionManager::class)->getWP('admin_email');
        }
        if (in_array('author', $this->types)) {
            foreach ($this->review->assigned_post_ids as $postId) {
                $post = get_post($postId);
                if ($post instanceof WP_Post) {
                    $this->email = true;
                    $emails[] = get_the_author_meta('user_email', intval($post->post_author));
                }
            }
        }
        if (in_array('custom', $this->types)) {
            $customEmails = glsr(OptionManager::class)->get('settings.general.notification_email');
            $customEmails = str_replace([' ', ',', ';'], ',', $customEmails);
            $customEmails = explode(',', $customEmails);
            $emails = array_merge($emails, $customEmails);
        }
        $emails = array_filter(array_keys(array_flip($emails)));
        return glsr()->filterArray('notification/emails', $emails, $this->review);
    }

    /**
     * @return string
     */
    protected function getLink()
    {
        return admin_url('post.php?post='.$this->review->ID.'&action=edit');
    }

    /**
     * @return string
     */
    protected function getTitle()
    {
        $titles = [];
        foreach ($this->review->assigned_post_ids as $postId) {
            $titles[] = get_the_title($postId);
        }
        $titles = array_filter($titles);
        $pageTitles = Str::naturalJoin($titles);
        $title = _nx(
            'New %s-star review',
            'New %s-star review of %s',
            count($titles),
            'This string differs depending on whether or not the review has been assigned to a post.',
            'site-reviews'
        );
        $title = sprintf('[%s] %s',
            wp_specialchars_decode(glsr(OptionManager::class)->getWP('blogname'), ENT_QUOTES),
            sprintf($title, $this->review->rating, $pageTitles)
        );
        return glsr()->filterString('notification/title', $title, $this->review);
    }

    /**
     * @return void
     */
    protected function sendToEmail(array $args)
    {
        $email = $this->buildEmail($args);
        if (empty($email->to)) {
            glsr_log()->error('Email notification was not sent (missing email address)');
            return;
        }
        $email->send();
    }

    /**
     * @return void
     */
    protected function sendToSlack(array $args)
    {
        if (!$this->slack) {
            return;
        }
        $notification = $this->buildSlackNotification($args);
        $result = $notification->send();
        if (is_wp_error($result)) {
            $notification->review = null;
            glsr_log()->error($result->get_error_message())->debug($notification);
        }
    }
}
