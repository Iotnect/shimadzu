<?php

namespace Tests\Unit;

use App\Logging\VercelFormatter;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\MissingAppKeyException;
use Monolog\Level;
use Monolog\LogRecord;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class VercelFormatterTest extends TestCase
{
    public function test_missing_application_keys_produce_a_short_actionable_log_line(): void
    {
        $line = $this->formatException(new MissingAppKeyException('No application encryption key has been specified.'));
        $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('SHIMADZU_RUNTIME_ERROR', $record['message']);
        $this->assertSame('APP_KEY is missing in this deployment.', $record['context']['hint']);
        $this->assertSame(MissingAppKeyException::class, $record['context']['exception']);
        $this->assertLessThan(1000, strlen($line));
        $this->assertSame(1, substr_count($line, "\n"));
    }

    public function test_database_errors_keep_the_sqlstate_and_remove_queries_and_credentials(): void
    {
        $cause = new PDOException('password=private-password');
        $cause->errorInfo = ['42P01', 7, 'sensitive connection details'];
        $exception = new QueryException('pgsql', 'select * from sessions where id = ?', ['private-session-id'], $cause);

        $line = $this->formatException($exception);
        $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('42P01', $record['context']['code']);
        $this->assertSame('A required database table is missing. Apply the database migrations.', $record['context']['hint']);
        $this->assertStringNotContainsString('private-password', $line);
        $this->assertStringNotContainsString('private-session-id', $line);
        $this->assertStringNotContainsString('select *', $line);
        $this->assertStringNotContainsString('sensitive connection details', $line);
    }

    public function test_unclassified_exceptions_keep_their_class_without_dumping_their_contents(): void
    {
        $line = $this->formatException(new RuntimeException(str_repeat('secret-token', 1000)));
        $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(RuntimeException::class, $record['context']['exception']);
        $this->assertStringNotContainsString('secret-token', $line);
        $this->assertLessThan(1000, strlen($line));
    }

    private function formatException(Throwable $exception): string
    {
        return (new VercelFormatter)->format(new LogRecord(
            datetime: new DateTimeImmutable('2026-10-26T10:00:00+08:00'),
            channel: 'production',
            level: Level::Error,
            message: $exception->getMessage(),
            context: ['exception' => $exception, 'password' => 'private-context'],
            extra: ['token' => 'private-extra'],
        ));
    }
}
