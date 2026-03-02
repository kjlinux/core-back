<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Rfid\StoreRfidDeviceRequest;
use App\Http\Requests\Rfid\UpdateRfidDeviceRequest;
use App\Http\Resources\RfidDeviceResource;
use App\Models\RfidDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfidDeviceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = RfidDevice::with('site');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

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
        $data = $request->validated();
        $data['mqtt_topic'] = 'core/rfid/sensor/' . $data['serial_number'] . '/event';

        $device = RfidDevice::create($data);

        return $this->resourceResponse(new RfidDeviceResource($device), '', 201);
    }

    public function update(UpdateRfidDeviceRequest $request, string $id): JsonResponse
    {
        $device = RfidDevice::findOrFail($id);
        $device->update($request->validated());

        return $this->resourceResponse(new RfidDeviceResource($device));
    }

    public function destroy(string $id): JsonResponse
    {
        $device = RfidDevice::findOrFail($id);
        $device->delete();

        return $this->noContentResponse();
    }
}
