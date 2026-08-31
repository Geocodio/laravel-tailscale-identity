<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity\Exceptions;

/** Infrastructure failure resolving identity. Callers must fail closed (503) — never anonymous access. */
final class IdentityLookupException extends \RuntimeException {}
