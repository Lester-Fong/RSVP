<?php

namespace App\Services;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Throwable;

class RedactInvitationTokens
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
            message: self::text($record->message),
            context: $this->clean($record->context),
            extra: $this->clean($record->extra),
        ));
    }

    public static function text(string $value): string
    {
        $value = preg_replace('~(/(?:invite|rsvp)/)[^\\s/?#<>"\\\']+~i', '$1[redacted]', $value);

        return preg_replace('/[a-f0-9]{64,}/i', '[redacted]', $value);
    }

    private function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::text($value);
        }
        if ($value instanceof Throwable) {
            return ['type' => $value::class, 'message' => self::text($value->getMessage()), 'trace' => self::text($value->getTraceAsString())];
        }
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[is_string($key) ? self::text($key) : $key] = $this->clean($item);
            }

            return $clean;
        }
        if (is_object($value)) {
            return '[object '.$value::class.']';
        }

        return $value;
    }
}
