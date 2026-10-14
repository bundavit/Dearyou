<?php

namespace App\Support;

use App\Models\Feedback;

class FeedbackSpamDetector
{
    private const LOOKBACK_HOURS = 24;

    private const RECENT_RECORD_LIMIT = 500;

    /**
     * Flag only clear combinations of abuse signals. The record remains available
     * for an administrator to review and restore; this is not a deletion rule.
     *
     * @return array{flagged: bool, score: int, reasons: array<int, string>}
     */
    public function inspect(string $message, ?int $excludeFeedbackId = null): array
    {
        $normalized = $this->normalize($message);
        $score = 0;
        $reasons = [];

        preg_match_all('~\b(?:https?://|www\.)[^\s<>]+~iu', $normalized, $links);
        $distinctLinks = array_unique(array_map(
            fn (string $link) => rtrim($link, '.,;:!?)]}'),
            $links[0],
        ));

        // A link by itself is common in a useful bug report. Require several
        // links and at least two explicit solicitation phrases together.
        $solicitations = [
            '/\bbuy now\b/u',
            '/\blimited[ -]time (?:offer|deal)\b/u',
            '/\b(?:cheap|discount|buy|sell) (?:backlinks|seo services)\b/u',
            '/\bguaranteed (?:income|profit|returns)\b/u',
            '/\b(?:casino bonus|free spins)\b/u',
            '/\b(?:payday loans?|forex signals|crypto investment)\b/u',
        ];
        $solicitationCount = 0;
        foreach ($solicitations as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                $solicitationCount++;
            }
        }
        if (count($distinctLinks) >= 2 && $solicitationCount >= 2) {
            $score += 80;
            $reasons[] = 'Multiple links combined with repeated promotional solicitations.';
        }

        // One-link income scams need a separate signal. Keep the earning claim,
        // large monetary amount and recurring frequency close together; a price
        // inquiry, currency amount or link alone is not a spam signal.
        if ($distinctLinks !== [] && $this->hasLargeRecurringIncomeClaim($normalized)) {
            $score += 80;
            $reasons[] = 'A link promotes unusually large recurring earnings or guaranteed income.';
        }

        $compact = preg_replace('/\s+/u', '', $normalized) ?? $normalized;
        if (mb_strlen($compact) >= 40 && preg_match('/^(.{1,12})\1{5,}$/us', $compact) === 1) {
            $score += 80;
            $reasons[] = 'The message consists almost entirely of a repeated short pattern.';
        }

        if (mb_strlen($normalized) >= 30) {
            $recent = Feedback::query()
                ->where('created_at', '>=', now()->subHours(self::LOOKBACK_HOURS))
                ->when($excludeFeedbackId !== null, fn ($query) => $query->whereKeyNot($excludeFeedbackId))
                ->latest('id')
                ->limit(self::RECENT_RECORD_LIMIT)
                ->pluck('message');

            $duplicates = 0;
            foreach ($recent as $previous) {
                if ($this->normalize((string) $previous) === $normalized) {
                    $duplicates++;
                    if ($duplicates >= 2) {
                        $score += 80;
                        $reasons[] = 'The same normalized message was already submitted at least twice within 24 hours.';
                        break;
                    }
                }
            }
        }

        return ['flagged' => $score >= 80, 'score' => min(100, $score), 'reasons' => $reasons];
    }

    private function hasLargeRecurringIncomeClaim(string $message): bool
    {
        preg_match_all(
            '/\b(?:earn(?:ing)?|guaranteed (?:income|profits?|returns))\b[^.!?]{0,48}?'
            .'(?:[$€£¥₹৳]|(?:usd|eur|gbp|inr|bdt|thb)\b)\s*'
            .'(?<amount>\d(?:[\d,]*\d)?(?:\.\d{1,2})?)(?![\d,.])[^.!?]{0,24}?'
            .'\b(?:(?:per|every|a)\s+(?:day|week|month)|daily|weekly|monthly)\b/u',
            $message,
            $matches,
        );

        foreach ($matches['amount'] as $amount) {
            if ((float) str_replace(',', '', $amount) >= 10000) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $message): string
    {
        // Keep language, punctuation, and URLs intact. Only case and whitespace
        // differ between otherwise identical messages, regardless of sender IP.
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($message, 'UTF-8')) ?? $message);
    }
}
