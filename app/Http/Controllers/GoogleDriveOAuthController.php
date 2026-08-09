<?php

namespace App\Http\Controllers;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleDriveOAuthController extends Controller
{
    public function redirect()
    {
        $client = $this->client();
        $state = Str::random(64);

        Cache::put('google_drive_oauth_state:' . $state, true, now()->addMinutes(10));

        $client->setState($state);

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request)
    {
        $state = (string) $request->query('state');

        if (! $state || ! Cache::pull('google_drive_oauth_state:' . $state)) {
            abort(419, 'Invalid Google Drive OAuth state.');
        }

        if ($request->filled('error')) {
            return response('Google Drive authorization was cancelled.', 400);
        }

        $code = (string) $request->query('code');

        if ($code === '') {
            return response('Google Drive authorization code is missing.', 400);
        }

        $token = $this->client()->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException($token['error_description'] ?? $token['error']);
        }

        if (empty($token['refresh_token'])) {
            return response(
                'No refresh token received. Revoke this app in Google Account permissions and authorize again.',
                400
            );
        }

        $tokenPath = config('services.google_drive.token_path');
        File::ensureDirectoryExists(dirname($tokenPath));
        File::put($tokenPath, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($tokenPath, 0600);

        return response('Google Drive OAuth configured successfully. You can close this tab.');
    }

    private function client(): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google_drive.client_id'));
        $client->setClientSecret(config('services.google_drive.client_secret'));
        $client->setRedirectUri(config('services.google_drive.redirect'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setScopes([Drive::DRIVE_FILE]);

        return $client;
    }
}
