<?php

declare(strict_types=1);

namespace Runearc\Exception;

/** 429: rate limited. `retryAfter` says how long to wait. */
class RateLimitException extends ApiException
{
}
