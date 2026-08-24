<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Post\CreatedVia;
use App\Enums\SocialAccount\Status;
use App\Jobs\Ai\GenerateAndPublishSnay3iPost;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSnay3iPosts extends Command
{
    protected $signature = 'snay3i:generate-post {--force : Generate even if the daily limit has been reached}';

    protected $description = 'Generate and publish AI-powered Snay3i.ma Facebook content';

    public function handle(): int
    {
        $account = SocialAccount::query()
            ->where('platform', 'facebook')
            ->where('display_name', 'like', 'Snay3i.ma%')
            ->where('status', Status::Connected)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            $this->error('No active Snay3i.ma Facebook connection is available.');

            return self::FAILURE;
        }

        $today = now('Africa/Casablanca');
        $dailyCount = Post::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('created_via', CreatedVia::Automation)
            ->whereDate('created_at', $today->toDateString())
            ->count();

        if ($dailyCount >= 5 && ! $this->option('force')) {
            $this->info('Snay3i daily automation limit reached (5 posts).');

            return self::SUCCESS;
        }

        $lastAutomation = Post::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('created_via', CreatedVia::Automation)
            ->latest('created_at')
            ->first();

        if ($lastAutomation && $lastAutomation->created_at->gt(now()->subHours(2)) && ! $this->option('force')) {
            $this->info('Snay3i automation cooldown is active; next post will be generated later.');

            return self::SUCCESS;
        }

        $slot = $dailyCount + 1;
        $themes = [
            'success stories and the human side of Moroccan skilled trades',
            'a practical tip that helps customers choose a reliable artisan',
            'how artisans can build trust with a strong profile, portfolio, and reviews',
            'a spotlight on a Moroccan trade such as plumbing, painting, electrical work, masonry, carpentry, or tiling',
            'a customer problem and the right skilled professional who can solve it',
            'the value of local craftsmanship and giving Moroccan artisans more visibility',
            'a motivational message for people who have a skill but are struggling to find work',
            'how a clear service description helps customers choose the right professional',
            'why reviews, photos, and a complete profile build trust before the first call',
            'a simple home-maintenance tip that helps Moroccan homeowners prevent bigger problems',
        ];

        $theme = $themes[((int) $today->isoWeekday() + $slot - 2) % count($themes)];

        $prompt = <<<PROMPT
You are the social media content director for Snay3i.ma, a Moroccan platform that helps skilled workers and customers connect.

Create one high-quality Facebook post for Moroccan audiences.

Today's theme: {$theme}
Post slot: {$slot} of 5 today
Date: {$today->toDateString()}

Goals:
- attract Moroccan customers who need trusted local professionals;
- attract artisans and skilled workers who need more visibility and job opportunities;
- make Snay3i.ma feel useful, trustworthy, local, and human;
- never invent statistics, testimonials, customers, prices, awards, or partnerships;
- avoid generic corporate marketing language and repetitive hooks;
- use a natural Moroccan tone: primarily French, with a light Darija touch when it genuinely improves the post;
- end with one clear CTA to discover Snay3i.ma or create a professional profile;
- keep hashtags focused and useful (maximum 6);
- vary the hook, structure, CTA, and vocabulary from recent automated posts;
- prefer useful, educational, relatable, or community-driven content over repeated promotion.

The image should visually fit the post and be suitable for a Moroccan audience. Prefer realistic people, real trades, tools, homes, workshops, and local context over generic office stock imagery.
PROMPT;

        GenerateAndPublishSnay3iPost::dispatch(
            userId: $account->workspace->user_id,
            workspaceId: $account->workspace_id,
            socialAccountId: $account->id,
            prompt: $prompt,
            creationId: 'snay3i-'.$today->format('Ymd').'-'.$slot.'-'.Str::lower(Str::random(8)),
        );

        $this->info("Queued Snay3i AI post #{$slot} for generation and publishing.");

        return self::SUCCESS;
    }
}
