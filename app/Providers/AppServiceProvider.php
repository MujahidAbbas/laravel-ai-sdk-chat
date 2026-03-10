<?php

namespace App\Providers;

use GuzzleHttp\Psr7\CachingStream;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Message\ResponseInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->preserveStreamingHttpBodies();
    }

    /**
     * Ensure streaming HTTP response bodies survive any watchers that read them.
     *
     * Herd's HerdDumper HttpClientWatcher listens for ResponseReceived events
     * and calls $response->body(), which exhausts non-seekable streaming bodies.
     * This wraps them in a CachingStream and rewinds after watchers finish.
     */
    protected function preserveStreamingHttpBodies(): void
    {
        Http::globalResponseMiddleware(function (ResponseInterface $response): ResponseInterface {
            $body = $response->getBody();

            if (! $body->isSeekable() && $body->isReadable()) {
                return $response->withBody(new CachingStream($body));
            }

            return $response;
        });

        Event::listen(ResponseReceived::class, function (ResponseReceived $event): void {
            $body = $event->response->getBody();

            if ($body->isSeekable()) {
                $body->seek(0);
            }
        });
    }
}
