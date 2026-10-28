<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a ballot cannot be accepted. Messages are generic and
 * safe to show to students; they never reveal ballot choices.
 */
class VotingException extends RuntimeException
{
    public const NOT_OPEN = 'not_open';

    public const NOT_ELIGIBLE = 'not_eligible';

    public const ALREADY_VOTED = 'already_voted';

    public const INVALID_BALLOT = 'invalid_ballot';

    public function __construct(public readonly string $reason, ?string $message = null)
    {
        parent::__construct($message ?? self::defaultMessage($reason));
    }

    public static function defaultMessage(string $reason): string
    {
        return match ($reason) {
            self::NOT_OPEN => 'Voting is not open for this election right now.',
            self::NOT_ELIGIBLE => 'You are not on the voter list for this election. Please see the election committee.',
            self::ALREADY_VOTED => 'Our records show you have already voted in this election.',
            self::INVALID_BALLOT => 'Your ballot could not be accepted. Please review your selections and try again.',
            default => 'Your ballot could not be submitted. Please try again.',
        };
    }
}
