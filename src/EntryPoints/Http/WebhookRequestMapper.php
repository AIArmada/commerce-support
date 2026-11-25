<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\EntryPoints\Http;

use AIArmada\CommerceSupport\Data\WebhookRequestData;
use Illuminate\Http\Request;

final class WebhookRequestMapper
{
    public static function fromRequest(Request $request): WebhookRequestData
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = array_values(array_filter(
                $values,
                static fn (mixed $value): bool => is_string($value),
            ));
        }

        return new WebhookRequestData($request->getContent(), $headers);
    }
}
