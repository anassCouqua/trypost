<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Public Privacy Policy required for Meta/Facebook app configuration.
Route::get('/privacy-policy', static function () {
    return response(<<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Privacy Policy — TryPost</title>
<style>
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.65;max-width:860px;margin:0 auto;padding:48px 24px;color:#171717;background:#fff}
h1{line-height:1.2} h2{margin-top:32px}.muted{color:#666}
</style>
</head>
<body>
<h1>Privacy Policy</h1>
<p class="muted">Last updated: August 24, 2026</p>
<p>TryPost is a social media management platform that helps users create, schedule and publish content to connected social media accounts. This Privacy Policy explains what information TryPost may collect, how it is used, and the choices available to users.</p>
<h2>1. Information we collect</h2>
<p>We may collect account information such as your name, email address and authentication details needed to operate your TryPost account.</p>
<p>When you connect a social network, we may receive information made available by that platform, such as your platform account identifier, display name, profile information, Pages or other managed assets, access tokens and permissions required to perform the actions you request.</p>
<p>We may also collect content you choose to upload or create, including text, images and scheduled posts, together with basic technical information needed to operate and secure the service.</p>
<h2>2. How we use information</h2>
<p>We use information to provide, maintain and improve TryPost; authenticate users; connect and manage supported social accounts; create, schedule and publish content at the user's direction; provide support; prevent abuse and unauthorized access; and maintain service security and reliability.</p>
<h2>3. Social platform data</h2>
<p>TryPost uses official APIs and authorization flows provided by supported social networks. We only request permissions needed for the features you choose to use. We use received social-platform data only to provide those requested features and in accordance with the applicable platform terms and policies.</p>
<h2>4. Sharing of information</h2>
<p>We do not sell personal information. We may share information with service providers that help us host, secure, monitor or operate TryPost, and when required by law or necessary to protect the service, our users or others.</p>
<h2>5. Data retention and deletion</h2>
<p>We retain information for as long as reasonably necessary to provide the service, maintain security, comply with legal obligations and resolve disputes. Users may request deletion of their TryPost account and associated personal data, subject to information we are legally required to retain.</p>
<p>To request deletion or ask a privacy question, contact the TryPost support team through the TryPost service.</p>
<h2>6. Security</h2>
<p>We use reasonable technical and organizational measures to protect information against unauthorized access, alteration, disclosure or destruction. No internet service can guarantee absolute security.</p>
<h2>7. Third-party services</h2>
<p>TryPost integrates with third-party platforms. Their own privacy policies and terms apply to information they process directly.</p>
<h2>8. Changes to this policy</h2>
<p>We may update this Privacy Policy from time to time. The updated version will be published on this page with a revised effective date.</p>
<h2>9. Contact</h2>
<p>For privacy questions or data deletion requests, contact the TryPost support team through the TryPost service.</p>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
});

// Keep the public entry point outside the authenticated application group.
// This prevents Render/browser requests to / from booting the auth/session
// middleware before a user has logged in.
Route::get('/', static function () {
    return redirect()->route('login');
});
