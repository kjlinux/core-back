<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Feelback\StoreFeelbackDeviceRequest;
use App\Http\Requests\Feelback\UpdateFeelbackDeviceRequest;
use App\Http\Resources\FeelbackDeviceResource;
use App\Models\FeelbackDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeelbackDeviceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = FeelbackDevice::with('site');

        $this->scopeByCompany($query);

        $query->when($request->filled('company_id'), function ($q) use ($request) {
            $q->where('company_id', $request->input('company_id'));
        });

        $query->when($request->filled('site_id'), function ($q) use ($request) {
            $q->where('site_id', $request->input('site_id'));
        });

        $query->when($request->has('is_online'), function ($q) use ($request) {
            $q->where('is_online', filter_var($request->input('is_online'), FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('serial_number', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhereHas('site', function ($siteQuery) use ($search) {
                        $siteQuery->where('name', 'LIKE', "%{$search}%");
                    });
            });
        });

        $devices = $query->paginate((int) $request->input('per_page', 15));

        return $this->paginatedResponse(FeelbackDeviceResource::collection($devices));
    }

    public function show(string $id): JsonResponse
    {
        $device = FeelbackDevice::with('site')->findOrFail($id);

        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $device->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        return $this->resourceResponse(new FeelbackDeviceResource($device));
    }

    public function store(StoreFeelbackDeviceRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $data['mqtt_topic'] = 'core/feelback/sensor/'.$data['serial_number'].'/event';

        $device = FeelbackDevice::create($data);

        return $this->resourceResponse(new FeelbackDeviceResource($device), '', 201);
    }

    public function update(UpdateFeelbackDeviceRequest $request, string $id): JsonResponse
    {
        $device = FeelbackDevice::findOrFail($id);

        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $device->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        $device->update($request->validated());

        return $this->resourceResponse(new FeelbackDeviceResource($device));
    }

    public function destroy(string $id): JsonResponse
    {
        $device = FeelbackDevice::findOrFail($id);

        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $device->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        $device->delete();

        return $this->noContentResponse();
    }
}
