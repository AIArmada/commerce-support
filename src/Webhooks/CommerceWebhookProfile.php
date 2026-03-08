<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

/**
 * Base webhook profile for commerce webhook handlers.
 *
 * This class provides sensible defaults for webhook validation.
 * Extend this class for package-specific webhook profiles.
 */
class CommerceWebhookProfile implements WebhookProfile
{
    /**
     * Determine if the request should be processed.
     */
    public function shouldProcess(Request $request): bool
    {
        // By default, process all incoming webhooks
        // Override in package-specific profiles for custom logic
        return true;
    }
}
