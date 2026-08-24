<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Post\CreatedVia;
use App\Enums\SocialAccount\Status;
use App\Jobs\Ai\GenerateAndPublishSnay3iPost;
use App\Models\Post;
use App\Models\SocialAccount;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSnay3iPosts extends Command
{
    protected $signature = 'snay3i:generate-post {--force : Rebuild the automation queue even when upcoming posts already exist}';

    protected $description = 'Generate and queue the next five AI-powered Snay3i.ma Facebook posts';

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

        $now = Carbon::now('Africa/Casablanca');
        $existing = Post::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('created_via', CreatedVia::Automation)
            ->whereIn('status', ['scheduled', 'publishing'])
            ->where('scheduled_at', '>', $now->copy()->utc())
            ->count();

        if ($existing >= 5 && ! $this->option('force')) {
            $this->info('Snay3i already has five upcoming automated posts scheduled.');
            return self::SUCCESS;
        }

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

        $slots = $this->nextSlots($now, 5);
        $queued = 0;

        foreach ($slots as $index => $slot) {
            if ($queued + $existing >= 5) {
                break;
            }

            $slotNumber = $index + 1;
            $theme = $themes[((int) $now->isoWeekday() + $slotNumber - 2) % count($themes)];
            $scheduledAt = $slot->copy()->utc();

            $prompt = <<<PROMPT
You are the social media content director for Snay3i.ma, a Moroccan platform that helps skilled workers and customers connect.

Create one high-quality Facebook post for Moroccan audiences.

Theme: {$theme}
Scheduled slot: {$scheduledAt->setTimezone('Africa/Casablanca')->format('Y-m-d H:i')} Morocco time

Voice and language:
- Sound unmistakably Moroccan and human, never like generic corporate copy.
- Use natural Moroccan Darija as the primary voice when it fits the idea, with French blended in naturally because many Moroccan audiences use Darija + French together.
- Do NOT force Darija or translate every sentence. Mix Darija and French the way a real Moroccan social-media creator would.
- Arabic script Darija is welcome when it makes the post feel more authentic; Latin Darija is also acceptable.
- Keep the message immediately understandable to Moroccan customers and artisans across Morocco.
- Avoid awkward literal translations, fake slang, or exaggerated street language.

Goals:
- attract Moroccan customers who need trusted local professionals;
- attract artisans and skilled workers who need more visibility and job opportunities;
- make Snay3i.ma feel useful, trustworthy, local, and human;
- never invent statistics, testimonials, customers, prices, awards, or partnerships;
- avoid generic corporate marketing language and repetitive hooks;
- end with one clear CTA to discover Snay3i.ma or create a professional profile;
- keep hashtags focused and useful (maximum 6);
- vary the hook, structure, CTA, and vocabulary from recent automated posts;
- prefer useful, educational, relatable, or community-driven content over repeated promotion.

The image should visually fit the post and be suitable for Morocco. Prefer realistic people, real trades, tools, homes, workshops, and local context over generic office stock imagery.
PROMPT;

            GenerateAndPublishSnay3iPost::dispatch(
                userId: $account->workspace->user_id,
                workspaceId: $account->workspace_id,
                socialAccountId: $account->id,
                prompt: $prompt,
                creationId: 'snay3i-'.$scheduledAt->format('YmdHi').'-'.$slotNumber.'-'.Str::lower(Str::random(8)),
                scheduledAt: $scheduledAt->toIso8601String(),
            );

            $queued++;
        }

        $this->info("Queued {$queued} AI-generated Snay3i posts for the upcoming schedule.");
        return self::SUCCESS;
    }

    /** @return array<int, Carbon> */
    private function nextSlots(Carbon $now, int $count): array
    {
        $preferredHours = [9, 12, 15, 18, 21];
        $slots = [];
        $day = $now->copy()->startOfDay();

        for ($dayOffset = 0; count($slots) < $count && $dayOffset < 3; $dayOffset++) {
            $candidateDay = $day->copy()->addDays($dayOffset);

            foreach ($preferredHours as $hour) {
                $candidate = $candidateDay->copy()->setTime($hour, 0);
                if ($candidate->lte($now->copy()->addMinutes(5))) {
                    continue;
                }
                $slots[] = $candidate;
                if (count($slots) >= $count) {
                    break;
                }
            }
        }

        return $slots;
    }
}
