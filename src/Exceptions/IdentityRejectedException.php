<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity\Exceptions;

/** The peer was positively rejected: tagged node, subnet router, address mismatch, unusable login, or missing capability (403). */
final class IdentityRejectedException extends \RuntimeException {}
