<?php

namespace GeminiLabs\SiteReviews\Controllers;

use GeminiLabs\SiteReviews\Commands\CreateReview;
use GeminiLabs\SiteReviews\Commands\EnqueuePublicAssets;
use GeminiLabs\SiteReviews\Modules\Html\Builder;
use GeminiLabs\SiteReviews\Modules\Schema;
use GeminiLabs\SiteReviews\Modules\Style;
use GeminiLabs\SiteReviews\Request;

class PublicController extends Controller
{
    /**
     * @return void
     * @action wp_enqueue_scripts
     */
    public function enqueueAssets()
    {
        $this->execute(new EnqueuePublicAssets());
    }

    /**
     * @param string $tag
     * @param string $handle
     * @return string
     * @filter script_loader_tag
     */
    public function filterEnqueuedScriptTags($tag, $handle)
    {
        $scripts = [glsr()->id.'/google-recaptcha'];
        if (in_array($handle, glsr()->filterArray('async-scripts', $scripts))) {
            $tag = str_replace(' src=', ' async src=', $tag);
        }
        if (in_array($handle, glsr()->filterArray('defer-scripts', $scripts))) {
            $tag = str_replace(' src=', ' defer src=', $tag);
        }
        return $tag;
    }

    /**
     * @return array
     * @filter site-reviews/config/forms/submission-form
     */
    public function filterFieldOrder(array $config)
    {
        $order = array_keys($config);
        $order = glsr()->filterArray('submission-form/order', $order);
        return array_intersect_key(array_merge(array_flip($order), $config), $config);
    }

    /**
     * @param string $view
     * @return string
     * @filter site-reviews/render/view
     */
    public function filterRenderView($view)
    {
        return glsr(Style::class)->filterView($view);
    }

    /**
     * @return void
     * @action site-reviews/builder
     */
    public function modifyBuilder(Builder $instance)
    {
        call_user_func_array([glsr(Style::class), 'modifyField'], [$instance]);
    }

    /**
     * @return void
     * @action wp_footer
     */
    public function renderSchema()
    {
        glsr(Schema::class)->render();
    }

    /**
     * @return CreateReview
     */
    public function routerSubmitReview(Request $request)
    {
        $command = $this->execute(new CreateReview($request));
        if ($command->success()) {
            wp_safe_redirect($command->referer()); // @todo add review ID to referer
            exit;
        }
    }
}
