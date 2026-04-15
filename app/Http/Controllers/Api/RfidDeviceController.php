<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Rfid\StoreRfidDeviceRequest;
use App\Http\Requests\Rfid\UpdateRfidDeviceRequest;
use App\Http\Resources\RfidDeviceResource;
use App\Models\RfidDevice;
use App\Models\TechnicienActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfidDeviceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = RfidDevice::with('site');

        $this->scopeByCompany($query);

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        if ($request->has('is_online')) {
            $query->where('is_online', filter_var($request->is_online, FILTER_VALIDATE_BOOLEAN));
        }

        $devices = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(RfidDeviceResource::collection($devices));
    }

    public function show(string $id): JsonResponse
    {
        $device = RfidDevice::with('site')->findOrFail($id);

        return $this->resourceResponse(new RfidDeviceResource($device));
    }

    public function store(StoreRfidDeviceRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $data['mqtt_topic'] = 'core/rfid/sensor/' . $data['serial_number'] . '/event';

        $device = RfidDevice::create($data);

        TechnicienActivityLog::record('create', 'rfid_device', (string) $device->id, $device->name . ' (' . $device->serial_number . ')');

        return $this->resourceResponse(new RfidDeviceResource($device), '', 201);
    }

    public function update(UpdateRfidDeviceRequest $request, string $id): JsonResponse
    {
        $device = RfidDevice::findOrFail($id);
        $device->update($request->validated());

        TechnicienActivityLog::record('update', 'rfid_device', (string) $device->id, $device->name . ' (' . $device->serial_number . ')');

        return $this->resourceResponse(new RfidDeviceResource($device));
    }

    public function destroy(string $id): JsonResponse
    {
        $device = RfidDevice::findOrFail($id);
        TechnicienActivityLog::record('delete', 'rfid_device', (string) $device->id, $device->name . ' (' . $device->serial_number . ')');
        $device->delete();

        return $this->noContentResponse();
    }
}
