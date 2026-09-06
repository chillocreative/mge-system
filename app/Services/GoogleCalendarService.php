<?php

namespace App\Services;

use App\Models\CompanyEvent;
use App\Models\GoogleCalendarToken;

/**
 * Skeleton Google Calendar integration.
 *
 * This service degrades gracefully: it compiles and runs without the
 * google/apiclient package installed. Once GOOGLE_CLIENT_ID/SECRET are set in
 * config('services.google.*') AND `composer require google/apiclient` has been
 * run, the guarded \Google\Client blocks below become active.
 */
class GoogleCalendarService
{
    /**
     * True only when credentials are present AND the Google client library is installed.
     */
    public function isConfigured(): bool
    {
        return (bool) config('services.google.client_id')
            && (bool) config('services.google.client_secret')
            && class_exists(\Google\Client::class);
    }

    /**
     * Lightweight status for the frontend "Connect Google Calendar" button.
     *
     * @return array{configured:bool,connected:bool}
     */
    public function status(?int $userId = null): array
    {
        $connected = false;

        if ($userId) {
            $connected = GoogleCalendarToken::where('user_id', $userId)
                ->whereNotNull('access_token')
                ->exists();
        }

        return [
            'configured' => $this->isConfigured(),
            'connected' => $connected,
        ];
    }

    /**
     * Build the OAuth consent URL.
     */
    public function authUrl(): string
    {
        $this->ensureConfigured();

        if (class_exists(\Google\Client::class)) {
            $client = $this->makeClient();

            return $client->createAuthUrl();
        }

        // Unreachable when not configured (ensureConfigured throws above).
        return '';
    }

    /**
     * Exchange the OAuth code for tokens and persist them for the user.
     */
    public function handleCallback(string $code, int $userId): GoogleCalendarToken
    {
        $this->ensureConfigured();

        if (class_exists(\Google\Client::class)) {
            $client = $this->makeClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            return GoogleCalendarToken::updateOrCreate(
                ['user_id' => $userId],
                [
                    'access_token' => $token['access_token'] ?? null,
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'expires_at' => isset($token['expires_in'])
                        ? now()->addSeconds((int) $token['expires_in'])
                        : null,
                ]
            );
        }

        throw new \RuntimeException('Google Calendar is not configured. Set GOOGLE_CLIENT_ID/SECRET and install google/apiclient.');
    }

    /**
     * Pull events from Google into company_events for the given user.
     */
    public function syncPull(int $userId): int
    {
        $this->ensureConfigured();

        if (class_exists(\Google\Client::class)) {
            $client = $this->makeClient();
            $token = GoogleCalendarToken::where('user_id', $userId)->first();

            if (! $token || ! $token->access_token) {
                throw new \RuntimeException('Google Calendar is not connected for this user.');
            }

            $client->setAccessToken([
                'access_token' => $token->access_token,
                'refresh_token' => $token->refresh_token,
            ]);

            $service = new \Google\Service\Calendar($client);
            $results = $service->events->listEvents('primary', [
                'timeMin' => now()->subMonth()->toRfc3339String(),
                'timeMax' => now()->addMonths(3)->toRfc3339String(),
                'singleEvents' => true,
            ]);

            $count = 0;
            foreach ($results->getItems() as $item) {
                $start = $item->getStart();
                $end = $item->getEnd();

                CompanyEvent::updateOrCreate(
                    ['google_event_id' => $item->getId(), 'source' => 'google'],
                    [
                        'title' => $item->getSummary() ?: '(no title)',
                        'description' => $item->getDescription(),
                        'type' => 'other',
                        'start_datetime' => $start->getDateTime() ?: $start->getDate(),
                        'end_datetime' => $end ? ($end->getDateTime() ?: $end->getDate()) : null,
                        'all_day' => (bool) ($start->getDate() && ! $start->getDateTime()),
                        'location' => $item->getLocation(),
                        'created_by' => $userId,
                    ]
                );
                $count++;
            }

            return $count;
        }

        throw new \RuntimeException('Google Calendar is not configured. Set GOOGLE_CLIENT_ID/SECRET and install google/apiclient.');
    }

    /**
     * Push a single app event up to Google Calendar.
     */
    public function pushEvent(CompanyEvent $event): void
    {
        $this->ensureConfigured();

        if (class_exists(\Google\Client::class)) {
            $client = $this->makeClient();
            $token = GoogleCalendarToken::where('user_id', $event->created_by)->first();

            if (! $token || ! $token->access_token) {
                throw new \RuntimeException('Google Calendar is not connected for this user.');
            }

            $client->setAccessToken([
                'access_token' => $token->access_token,
                'refresh_token' => $token->refresh_token,
            ]);

            $service = new \Google\Service\Calendar($client);
            $googleEvent = new \Google\Service\Calendar\Event([
                'summary' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'start' => ['dateTime' => optional($event->start_datetime)->toRfc3339String()],
                'end' => ['dateTime' => optional($event->end_datetime ?? $event->start_datetime)->toRfc3339String()],
            ]);

            $created = $service->events->insert('primary', $googleEvent);
            $event->update(['google_event_id' => $created->getId()]);

            return;
        }

        throw new \RuntimeException('Google Calendar is not configured. Set GOOGLE_CLIENT_ID/SECRET and install google/apiclient.');
    }

    // ── Helpers ──

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Google Calendar is not configured. Set GOOGLE_CLIENT_ID/SECRET and install google/apiclient.');
        }
    }

    private function makeClient(): \Google\Client
    {
        $client = new \Google\Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([\Google\Service\Calendar::CALENDAR]);

        return $client;
    }
}
