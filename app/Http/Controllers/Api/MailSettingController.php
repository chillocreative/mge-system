<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MailSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MailSettingController extends Controller
{
    public function __construct(private MailSettingService $service) {}

    public function show(): JsonResponse
    {
        $setting = $this->service->get();

        return $this->success($setting ? [
            'host' => $setting->host,
            'port' => $setting->port,
            'username' => $setting->username,
            'encryption' => $setting->encryption,
            'from_address' => $setting->from_address,
            'from_name' => $setting->from_name,
            'enabled' => $setting->enabled,
            'has_password' => filled($setting->password),
        ] : null);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'enabled' => ['boolean'],
        ]);

        $setting = $this->service->save($validated);

        return $this->success([
            'host' => $setting->host,
            'port' => $setting->port,
            'username' => $setting->username,
            'encryption' => $setting->encryption,
            'from_address' => $setting->from_address,
            'from_name' => $setting->from_name,
            'enabled' => $setting->enabled,
            'has_password' => filled($setting->password),
        ], 'Mail settings saved.');
    }

    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $to = $data['to'];
        unset($data['to']);

        try {
            $this->service->sendTest($to, $data);

            return $this->success(null, 'Test email sent to '.$to.'.');
        } catch (Throwable $e) {
            return $this->error('Failed to send test email: '.$e->getMessage(), 422);
        }
    }
}
