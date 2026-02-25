<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Feelback\StoreFeelbackDeviceRequest;
use App\Http\Requests\Feelback\UpdateFeelbackDeviceRequest;
use App\Http\Resources\FeelbackDeviceResource;
use App\Models\FeelbackDevice;
use App\Services\MqttService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeelbackDeviceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = FeelbackDevice::with('site');

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

        return $this->paginatedResponse(FeelbackDeviceResource::collection($devices));
    }

    public function show(string $id): JsonResponse
    {
        $device = FeelbackDevice::with('site')->findOrFail($id);

        return $this->resourceResponse(new FeelbackDeviceResource($device));
    }

    public function store(StoreFeelbackDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['mqtt_topic'] = 'core/feelback/sensor/' . $data['serial_number'] . '/event';

        $device = FeelbackDevice::create($data);

        return $this->resourceResponse(new FeelbackDeviceResource($device), '', 201);
    }

    public function update(UpdateFeelbackDeviceRequest $request, string $id): JsonResponse
    {
        $device = FeelbackDevice::findOrFail($id);
        $device->update($request->validated());

        return $this->resourceResponse(new FeelbackDeviceResource($device));
    }

    public function destroy(string $id): JsonResponse
    {
        $device = FeelbackDevice::findOrFail($id);
        $device->delete();

        return $this->noContentResponse();
    }

    public function restart(string $id, MqttService $mqtt): JsonResponse
    {
        $device = FeelbackDevice::findOrFail($id);

        $responseTopic = $mqtt->getResponseTopic($device->mqtt_topic);
        $command = json_encode([
            'command' => 'RESTART',
            'device_id' => $device->id,
            'timestamp' => now()->toISOString(),
        ]);

        $mqtt->publish($responseTopic, $command);

        return $this->successResponse(
            new FeelbackDeviceResource($device),
            'Redemarrage lance'
        );
    }
}
