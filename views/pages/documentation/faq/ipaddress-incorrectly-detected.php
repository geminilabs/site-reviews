<div id="faq-ipaddress-incorrectly-detected" class="glsr-card postbox">
    <h3 class="glsr-card-heading">
        <button type="button" class="glsr-accordion-trigger" aria-expanded="false" aria-controls="">
            <span class="title">Why are the IP Addresses being incorrectly detected?</span>
            <span class="icon"></span>
        </button>
    </h3>
    <div class="inside">
        <p>If you are getting identical IP addresses for all your reviews (or if they are "unknown"), then your web server may be behind a CDN, firewall, or reverse proxy.</p>
        <p>If this is the case, you will need to provide Site Reviews with the HTTP request header that contains the real visitor IP address. If you are unsure what this means or how to find out which custom header is needed, please contact your hosting provider, server administrator, or the support team of your CDN or firewall service for assistance.</p>
        <p>Once you know the custom HTTP request header to use, you may add it with the following code snippet:</p>
        <pre><code class="language-php">/**
 * Add a custom HTTP request header to fix IP address detection
 * @param \Geminilabs\Vectorface\Whip $whip
 * @return void
 */
add_action('site-reviews/whip', function ($whip) {
    $customHeader = 'HTTP_X_SUCURI_CLIENTIP'; // change the header as needed
    $whip->addCustomHeader($customHeader);
});</code></pre>
    </div>
</div>
