<?php

namespace App\Logging;

use Illuminate\Database\QueryException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use Throwable;

class VercelFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $exception = $record->context['exception'] ?? null;

        if ($exception instanceof Throwable) {
            $cause = $exception->getPrevious() ?? $exception;
            $code = (string) ($exception instanceof QueryException ? ($exception->errorInfo[0] ?? $cause->getCode()) : $cause->getCode());

            $hint = match (true) {
                $exception instanceof MissingAppKeyException => 'APP_KEY is missing in this deployment.',
                $exception instanceof ViteManifestNotFoundException => 'The frontend build manifest is missing.',
                $exception instanceof QueryException => match ($code) {
                    '42P01' => 'A required database table is missing. Apply the database migrations.',
                    '28P01' => 'PostgreSQL rejected the database credentials.',
                    '3D000' => 'The configured PostgreSQL database does not exist.',
                    default => 'Database request failed. Check DB_URL, connectivity, and migrations.',
                },
                default => 'Check the exception class and source location.',
            };

            $record = $record->with(
                message: 'SHIMADZU_RUNTIME_ERROR',
                context: [
                    'exception' => $exception::class,
                    'cause' => $cause::class,
                    'code' => $code,
                    'hint' => $hint,
                    'location' => basename($exception->getFile()).':'.$exception->getLine(),
                ],
                extra: [],
            );
        }

        return parent::format($record);
    }
}
