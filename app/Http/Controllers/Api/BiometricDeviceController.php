<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Biometric\StoreDeviceRequest;
use App\Http\Resources\BiometricDeviceResource;
use App\Models\BiometricAuditLog;
use App\Models\BiometricDevice;
use App\Models\TechnicienActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiometricDeviceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = BiometricDevice::with('site')->withCount(['enrollments as enrolled_count' => function ($q) {
            $q->where('status', 'enrolled');
        }]);

        $this->scopeByCompany($query);

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        if ($request->has('is_online')) {
            $query->where('is_online', filter_var($request->is_online, FILTER_VALIDATE_BOOLEAN));
        }

        $devices = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(BiometricDeviceResource::collection($devices));
    }

    public function show(string $id): JsonResponse
    {
        $device = BiometricDevice::with(['site', 'enrollments'])->withCount(['enrollments as enrolled_count' => function ($q) {
            $q->where('status', 'enrolled');
        }])->findOrFail($id);

        return $this->resourceResponse(new BiometricDeviceResource($device));
    }

    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $data['mqtt_topic'] = 'core/biometric/sensor/' . $data['serial_number'] . '/event';

        $device = BiometricDevice::create($data);

        BiometricAuditLog::create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'device_created',
            'target' => $device->serial_number,
            'details' => 'Appareil biometrique cree: ' . $device->name,
        ]);

        TechnicienActivityLog::record('create', 'biometric_device', (string) $device->id, $device->name . ' (' . $device->serial_number . ')');

        return $this->resourceResponse(new BiometricDeviceResource($device), '', 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $device = BiometricDevice::findOrFail($id);

        BiometricAuditLog::create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'device_deleted',
            'target' => $device->serial_number,
            'details' => 'Appareil biometrique supprime: ' . $device->name,
        ]);

        TechnicienActivityLog::record('delete', 'biometric_device', (string) $device->id, $device->name . ' (' . $device->serial_number . ')');

        $device->delete();

        return $this->noContentResponse();
    }

    public function setOnline(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'is_online' => ['required', 'boolean'],
        ]);

        $device = BiometricDevice::findOrFail($id);
        $device->update([
            'is_online' => $request->boolean('is_online'),
            'last_sync_at' => now(),
        ]);

        BiometricAuditLog::create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => $request->boolean('is_online') ? 'device_set_online' : 'device_set_offline',
            'target' => $device->serial_number,
            'details' => 'Statut manuel: ' . $device->name . ' -> ' . ($request->boolean('is_online') ? 'en ligne' : 'hors ligne'),
        ]);

        return $this->resourceResponse(new BiometricDeviceResource($device->fresh()), 'Statut mis a jour');
    }

    public function sync(Request $request, string $id): JsonResponse
    {
        $device = BiometricDevice::findOrFail($id);
        $device->update(['last_sync_at' => now()]);

        BiometricAuditLog::create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'device_sync',
            'target' => $device->serial_number,
            'details' => 'Synchronisation lancee pour: ' . $device->name,
        ]);

        return $this->successResponse(
            new BiometricDeviceResource($device->fresh()),
            'Synchronisation lancee'
        );
    }
}
