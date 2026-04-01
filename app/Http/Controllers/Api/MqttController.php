<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Mqtt\SendMqttCommandRequest;
use App\Http\Requests\Mqtt\TestMqttRequest;
use App\Models\BiometricDevice;
use App\Models\RfidDevice;
use App\Services\MqttService;
use Illuminate\Http\JsonResponse;

class MqttController extends BaseApiController
{
    public function testConnection(TestMqttRequest $request, MqttService $mqtt): JsonResponse
    {
        $data = $request->validated();

        try {
            $mqtt->publish($data['topic'], json_encode([
                'type' => 'test',
                'message' => 'Test de connexion MQTT',
                'timestamp' => now()->toISOString(),
            ]));

            return $this->successResponse(null, 'Connexion MQTT reussie');
        } catch (\Exception $e) {
            return $this->errorResponse('Echec de connexion MQTT: ' . $e->getMessage(), 500);
        }
    }

    public function sendCommand(SendMqttCommandRequest $request, MqttService $mqtt): JsonResponse
    {
        $data = $request->validated();

        $deviceId   = $data['device_id'];
        $deviceType = $data['device_type'];

        if ($deviceType === 'biometric') {
            $device = BiometricDevice::findOrFail($deviceId);
        } else {
            $device = RfidDevice::findOrFail($deviceId);
        }

        $commandCode = config("mqtt.command_codes.{$deviceType}.{$data['command']}", $data['command']);

        // Reconstruire le topic /response depuis serial_number si mqtt_topic absent
        if (!empty($device->mqtt_topic)) {
            $responseTopic = $mqtt->getResponseTopic($device->mqtt_topic);
        } else {
            $prefix = config("mqtt.topics.{$deviceType}");
            $responseTopic = "{$prefix}/{$device->serial_number}/response";
        }

        try {
            // Publier le code de commande brut — le firmware compare message == CMD_SCAN etc.
            $mqtt->publish($responseTopic, $commandCode);

            return $this->successResponse([
                'topic'   => $responseTopic,
                'command' => $commandCode,
            ], 'Commande envoyee avec succes');
        } catch (\Exception $e) {
            return $this->errorResponse('Echec d\'envoi de la commande: ' . $e->getMessage(), 500);
        }
    }
}
