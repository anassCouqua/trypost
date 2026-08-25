<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class GenerateAndPublishSnay3iPost implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 1800;

    public function __construct(
        public string $userId,
        public string $workspaceId,
        public string $socialAccountId,
        public string $prompt,
        public string $creationId,
        public ?string $scheduledAt = null,
    ) {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return $this->workspaceId.':'.$this->socialAccountId.':'.$this->creationId;
    }

    public function handle(): void
    {
        $startedAt = now();
        $account = SocialAccount::findOrFail($this->socialAccountId);
        $workspace = Workspace::findOrFail($this->workspaceId);
        $scheduledAt = $this->scheduledAt ? Carbon::parse($this->scheduledAt)->utc() : null;

        $darijaBrief = <<<'DARIJA'

SNAY3I.MA MOROCCAN DARIJA VOICE - NON-NEGOTIABLE:
You are writing as a Moroccan social media creator who naturally speaks Moroccan Darija (الدارجة المغربية), not Modern Standard Arabic, Gulf Arabic, Algerian Arabic, or translated Arabic.

Write the post DIRECTLY in authentic Moroccan Darija. Do not think in English or French and translate it.

Default script:
- Use Arabic script for Darija.
- Use French words only where a Moroccan speaker would naturally code-switch.
- Keep common Moroccan digital words such as service, client, devis, chantier, WhatsApp, prix, rendez-vous when they sound natural.
- Do not overuse French. The sentence must still sound Moroccan when read aloud by a Moroccan in Casablanca/Rabat/Marrakesh.

Naturalness rules:
- Prefer everyday spoken Moroccan phrasing, short sentences, contractions and familiar expressions.
- Sound like a real Moroccan person posting on Facebook, not a company brochure.
- No MSA vocabulary just because it is formally correct.
- No literal translation from English/French.
- No Gulf expressions.
- No Algerian-specific vocabulary.
- No robotic motivational language.
- No fake Moroccan slang added just to sound local.
- Avoid "كنسعاو", "نقدموا", "يعتبر", "منصة رائدة", "فرصة لا تعوض" and similar corporate/translated phrasing unless the context genuinely requires it.
- Use Moroccan agreement and sentence structure naturally.

Snay3i context:
- Audience: Moroccan workers, maâlems, craftsmen, technicians, service providers and Moroccan customers.
- Core words/ideas can include: المعلم، الصانع، الحرفي، الخدمة، الزبون، الثمن، الموعد، الورش، chantier، devis، خدمة مزيانة، الثقة، الوقت، واتساب.
- The brand should feel useful, Moroccan, practical and street-smart, not corporate.

Before returning the JSON, read the post aloud mentally as a Moroccan from Casablanca would say it.
If a phrase sounds translated, formal, unnatural or not something a Moroccan would actually post, rewrite it.
DARIJA;

        StreamPostCreation::dispatchSync(
            userId: $this->userId,
            creationId: $this->creationId,
            workspaceId: $this->workspaceId,
            format: ContentType::FacebookPost->value,
            socialAccountId: $account->id,
            imageCount: 1,
            prompt: $darijaBrief."\nOriginal content brief:\n".$this->prompt,
            date: $scheduledAt?->toIso8601String(),
            template: 'image_card',
            applyBrandVisuals: true,
        );

        $post = Post::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $this->userId)
            ->where('created_at', '>=', $startedAt)
            ->with('postPlatforms')
            ->latest('created_at')
            ->first();

        if (! $post) {
            throw new \RuntimeException('AI generation completed without creating a post.');
        }

        $post->update([
            'created_via' => CreatedVia::Automation,
            'status' => $scheduledAt && $scheduledAt->isFuture()
                ? PostStatus::Scheduled
                : PostStatus::Draft,
            'scheduled_at' => $scheduledAt,
        ]);

        $platform = $post->postPlatforms()
            ->where('social_account_id', $account->id)
            ->first();

        if (! $platform) {
            throw new \RuntimeException('Generated Snay3i post has no Facebook platform record.');
        }

        $platform->update([
            'content_type' => ContentType::FacebookPost->value,
            'enabled' => true,
        ]);

        if (! $scheduledAt || $scheduledAt->isPast()) {
            PublishPost::dispatch($post->fresh());
        }

        Log::info('Automated Snay3i post generated', [
            'post_id' => $post->id,
            'workspace_id' => $workspace->id,
            'social_account_id' => $account->id,
            'creation_id' => $this->creationId,
            'scheduled_at' => $scheduledAt?->toIso8601String(),
            'status' => $post->status->value,
        ]);
    }
}
