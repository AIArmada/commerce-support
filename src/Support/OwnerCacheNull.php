<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

/**
 * Marker stored in place of `null` cache values.
 *
 * Cache repositories map a stored `null` to the caller's default, so a plain
 * miss sentinel cannot distinguish a cached `null` from a miss. Storing this
 * marker keeps that distinction intact across drivers (it survives
 * serialization) and lets `OwnerCache::remember()` return a cached `null`
 * without re-running or locking.
 *
 * @internal Not part of the public API.
 */
final class OwnerCacheNull {}
