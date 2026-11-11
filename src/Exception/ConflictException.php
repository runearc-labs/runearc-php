<?php

declare(strict_types=1);

namespace Runearc\Exception;

/** 409: the resource is in the wrong state, or an idempotency key was reused with another body. */
class ConflictException extends ApiException
{
}
