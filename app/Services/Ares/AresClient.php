<?php

namespace App\Services\Ares;

use App\Rules\Ico;
use App\Services\Ares\Exceptions\AresSubjectNotFoundException;
use App\Services\Ares\Exceptions\AresUnavailableException;
use App\Services\Ares\Exceptions\InvalidIcoException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Klient ARES (REST „ekonomické subjekty“). Úspěšné odpovědi se 24 hodin cachují. */
class AresClient
{
    public const BASE_URL = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty';

    public const CACHE_TTL_HOURS = 24;

    public const TIMEOUT_SECONDS = 5;

    /**
     * @throws InvalidIcoException
     * @throws AresSubjectNotFoundException
     * @throws AresUnavailableException
     */
    public function find(string $ico, bool $fresh = false): AresSubject
    {
        $ico = Ico::normalize($ico);

        if (! Ico::isValid($ico)) {
            throw InvalidIcoException::for($ico);
        }

        $key = "ares.subject.{$ico}";

        if ($fresh) {
            Cache::forget($key);
        }

        /** @var array<string, string|null> $data */
        $data = Cache::remember(
            $key,
            now()->addHours(self::CACHE_TTL_HOURS),
            fn (): array => $this->fetch($ico)->toArray(),
        );

        return AresSubject::fromArray($data);
    }

    private function fetch(string $ico): AresSubject
    {
        try {
            $response = Http::acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                // Jeden opakovaný pokus, jen při výpadku spojení nebo chybě serveru.
                ->retry(2, 200, fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()), throw: false)
                ->get(self::BASE_URL.'/'.$ico);
        } catch (ConnectionException $e) {
            throw AresUnavailableException::because('spojení se nezdařilo nebo vypršel čas.');
        }

        return $this->map($response, $ico);
    }

    private function map(Response $response, string $ico): AresSubject
    {
        if ($response->status() === 404) {
            throw AresSubjectNotFoundException::for($ico);
        }

        if ($response->status() === 400) {
            throw InvalidIcoException::for($ico);
        }

        if (! $response->successful()) {
            throw AresUnavailableException::because("odpověď {$response->status()}.");
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['ico'], $data['obchodniJmeno'])) {
            throw AresUnavailableException::because('neočekávaný tvar odpovědi.');
        }

        return AresSubject::fromResponse($data);
    }
}
