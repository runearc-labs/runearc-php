<?php

declare(strict_types=1);

namespace Runearc\Exception;

/** Any other 4xx: the request was refused (400, 402, 408, 422 and so on). */
class InvalidRequestException extends ApiException
{
}
