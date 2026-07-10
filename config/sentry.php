<?php

declare(strict_types=1);

/**
 * Sentry Configuration.
 *
 * To enable Sentry error tracking:
 * 1. Create a Sentry project at https://sentry.io
 * 2. Copy the DSN from your project settings
 * 3. Add to your .env file:
 *    SENTRY_DSN=https://your-dsn@sentry.io/project-id
 *
 * Sentry is automatically disabled if:
 * - SENTRY_DSN is not set
 * - CAMERAS_ENV is not 'production'
 */

use Sentry\Breadcrumb;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\ClientBuilder;

function initSentry(): void
{
    $dsn = getenv('SENTRY_DSN') ?: '';
    if ($dsn === '' || !isProduction()) {
        return;
    }

    $options = [
        'dsn' => $dsn,
        'environment' => getCameraEnv(),
        'release' => 'sistema-cameras@1.0',
        'attach_stacktrace' => true,
        'send_default_pii' => false,
        'traces_sample_rate' => 0.1,
        'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
            // Filter out common non-errors
            $exception = $event->getThrowable();
            if ($exception instanceof \ErrorException) {
                $message = $exception->getMessage();
                // Ignore common PHP warnings
                if (str_contains($message, 'Undefined array key')
                    || str_contains($message, 'Undefined variable')
                    || str_contains($message, 'Trying to access array offset')) {
                    return null;
                }
            }
            return $event;
        },
    ];

    $client = (new ClientBuilder($options))->getClient();
    Hub::getCurrent()->bindClient($client);

    // Add breadcrumbs for context
    SentrySdk::getClient()->setSdkBreadcrumbRecordHint(function (array $hint): array {
        $hint['internal'] = true;
        return $hint;
    });
}

function captureSentryException(\Throwable $exception, array $context = []): void
{
    if (!isProduction() || empty(getenv('SENTRY_DSN'))) {
        return;
    }

    SentrySdk::configureScope(function (\Sentry\State\Scope $scope) use ($context): void {
        foreach ($context as $key => $value) {
            $scope->setContext($key, is_array($value) ? $value : ['value' => $value]);
        }
    });

    SentrySdk::captureException($exception);
}

function captureSentryMessage(string $message, string $level = 'info', array $context = []): void
{
    if (!isProduction() || empty(getenv('SENTRY_DSN'))) {
        return;
    }

    $breadcrumbLevel = match ($level) {
        'debug' => Breadcrumb::LEVEL_DEBUG,
        'info' => Breadcrumb::LEVEL_INFO,
        'warning' => Breadcrumb::LEVEL_WARNING,
        'error' => Breadcrumb::LEVEL_ERROR,
        default => Breadcrumb::LEVEL_INFO,
    };

    SentrySdk::addBreadcrumb(new Breadcrumb(
        $breadcrumbLevel,
        Breadcrumb::TYPE_DEFAULT,
        'log',
        $message,
        $context
    ));
}

// Auto-initialize on load
initSentry();
