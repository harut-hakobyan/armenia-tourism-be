<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Setting::query()->orderBy('key')->get()]);
    }

    public function update(Request $request, Setting $setting, AuditLogger $audit): JsonResponse
    {
        $old = $setting->toArray();
        $setting->update($request->validate(['value' => ['nullable', 'string', 'max:10000'], 'is_public' => ['sometimes', 'boolean']]));
        $audit->record($request->user(), 'setting.updated', $setting, $old, $setting->refresh()->toArray(), $request->ip());

        return response()->json(['data' => $setting]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $perPage = (int) ($request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']])['per_page'] ?? 25);

        return response()->json(AuditLog::query()->with('user:id,name')->latest()->paginate($perPage));
    }
}
